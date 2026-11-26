<?php

use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\User;
use App\Services\DueBills;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;

function compraNoCartao(User $u, Card $card, string $mes, int $cents = 30000): void
{
    makeEntry($u, ['card_id' => $card->id, 'month' => $mes.'-01', 'amount_cents' => $cents, 'name' => 'Compra '.$mes]);
}

function contas(User $u, string $hoje, string $visao = 'nos'): array
{
    test()->actingAs($u);

    return app(DueBills::class)->upcoming(CarbonImmutable::parse($hoje), ViewContext::for($u, $visao));
}

it('a fatura de setembro vence no dia cadastrado de outubro e aparece 7 dias antes', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel);
    $card->update(['due_day' => 10]);
    compraNoCartao($eliel, $card, '2026-09');
    compraNoCartao($eliel, $card, '2026-09', 30000);

    $lista = contas($eliel, '2026-10-05');

    expect($lista)->toHaveCount(1)
        ->and($lista[0])->toMatchArray(['type' => 'invoice', 'name' => 'Fatura Bradesco (setembro)', 'due' => '2026-10-10', 'amount' => 60000, 'days_left' => 5, 'month' => '2026-09']);
});

it('não aparece antes de 7 dias, depois do vencimento, sem "vence dia" ou com fatura paga', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel);
    $card->update(['due_day' => 10]);
    compraNoCartao($eliel, $card, '2026-09');

    expect(contas($eliel, '2026-10-01'))->toBeEmpty()   // faltam 9 dias
        ->and(contas($eliel, '2026-10-11'))->toBeEmpty(); // já venceu

    $card->update(['due_day' => null]);
    expect(contas($eliel, '2026-10-05'))->toBeEmpty();

    $card->update(['due_day' => 10]);
    $invoice = new CardInvoice(['card_id' => $card->id, 'month' => '2026-09-01', 'paid' => true]);
    $invoice->couple_id = $eliel->couple_id;
    $invoice->save();
    expect(contas($eliel, '2026-10-05'))->toBeEmpty();
});

it('compras do cartão não aparecem uma a uma: só a fatura', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel);
    $card->update(['due_day' => 8]);
    compraNoCartao($eliel, $card, '2026-09', 10000);
    makeEntry($eliel, ['card_id' => $card->id, 'month' => '2026-09-01', 'due_on' => '2026-10-06', 'name' => 'Compra com data']);

    $lista = contas($eliel, '2026-10-05');

    expect(array_column($lista, 'name'))->toBe(['Fatura Bradesco (setembro)']);
});

it('lançamentos sem cartão com data de vencimento entram; pagos, entradas e fora da janela não', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['name' => 'Luz', 'due_on' => '2026-10-07', 'amount_cents' => 15000]);
    makeEntry($eliel, ['name' => 'Hoje', 'due_on' => '2026-10-05']);
    makeEntry($eliel, ['name' => 'Em 7 dias', 'due_on' => '2026-10-12']);
    makeEntry($eliel, ['name' => 'Em 8 dias', 'due_on' => '2026-10-13']);
    makeEntry($eliel, ['name' => 'Paga', 'due_on' => '2026-10-06', 'paid' => true]);
    makeEntry($eliel, ['name' => 'Ontem', 'due_on' => '2026-10-04']);
    makeEntry($eliel, ['name' => 'Salário', 'kind' => 'entrada', 'due_on' => '2026-10-06', 'category_id' => categoryOf($eliel->couple_id, 'Salário')->id]);
    makeEntry($eliel, ['name' => 'Sem data']);

    $lista = contas($eliel, '2026-10-05');

    expect(array_column($lista, 'name'))->toBe(['Hoje', 'Luz', 'Em 7 dias'])
        ->and(array_column($lista, 'days_left'))->toBe([0, 2, 7]);
});

it('a visão "Eliel" só traz o que é do Eliel', function () {
    [$eliel, $ana] = makeCouple();
    makeEntry($eliel, ['name' => 'Do Eliel', 'due_on' => '2026-10-06']);
    makeEntry($ana, ['name' => 'Da Ana', 'due_on' => '2026-10-06']);

    expect(array_column(contas($eliel, '2026-10-05', 'eu'), 'name'))->toBe(['Do Eliel'])
        ->and(array_column(contas($eliel, '2026-10-05', 'par'), 'name'))->toBe(['Da Ana'])
        ->and(count(contas($eliel, '2026-10-05', 'nos')))->toBe(2);
});

it('dia 31 em mês curto vence no último dia do mês', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel);
    $card->update(['due_day' => 31]);
    compraNoCartao($eliel, $card, '2026-01');

    expect(contas($eliel, '2026-02-25')[0]['due'])->toBe('2026-02-28');
});

it('forMonth: fatura do mês anterior vence no dia do cartão do mês e mostra paga ou não', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel);
    $card->update(['due_day' => 10]);
    compraNoCartao($eliel, $card, '2026-09');
    test()->actingAs($eliel);

    $out = app(DueBills::class)->forMonth(CarbonImmutable::parse('2026-10-01'), ViewContext::for($eliel, 'nos'), CarbonImmutable::parse('2026-10-05'));

    expect($out)->toHaveCount(1)->and($out[0])->toMatchArray(['name' => 'Fatura Bradesco (setembro)', 'due' => '2026-10-10', 'paid' => false, 'overdue' => false, 'days_left' => 5]);

    $out = app(DueBills::class)->forMonth(CarbonImmutable::parse('2026-10-01'), ViewContext::for($eliel, 'nos'), CarbonImmutable::parse('2026-10-12'));
    expect($out[0]['overdue'])->toBeTrue()->and($out[0]['days_left'])->toBe(-2);

    // outro mês: sem "dias restantes"
    $nov = app(DueBills::class)->forMonth(CarbonImmutable::parse('2026-10-01'), ViewContext::for($eliel, 'nos'), CarbonImmutable::parse('2026-08-01'));
    expect($nov[0]['days_left'])->toBeNull();
});

it('forMonth: respeita a visão Eu/Par e ignora compras de cartão e entradas', function () {
    [$eliel, $ana] = makeCouple();
    makeEntry($eliel, ['name' => 'Do Eliel', 'month' => '2026-10-01', 'due_on' => '2026-10-20']);
    makeEntry($ana, ['name' => 'Da Ana', 'month' => '2026-10-01', 'due_on' => '2026-10-21']);
    makeEntry($eliel, ['name' => 'Salário', 'kind' => 'entrada', 'month' => '2026-10-01', 'due_on' => '2026-10-22', 'category_id' => categoryOf($eliel->couple_id, 'Salário')->id]);
    test()->actingAs($eliel);
    $mes = CarbonImmutable::parse('2026-10-01');

    expect(array_column(app(DueBills::class)->forMonth($mes, ViewContext::for($eliel, 'nos')), 'name'))->toBe(['Do Eliel', 'Da Ana'])
        ->and(array_column(app(DueBills::class)->forMonth($mes, ViewContext::for($eliel, 'eu')), 'name'))->toBe(['Do Eliel'])
        ->and(array_column(app(DueBills::class)->forMonth($mes, ViewContext::for($eliel, 'par')), 'name'))->toBe(['Da Ana']);
});
