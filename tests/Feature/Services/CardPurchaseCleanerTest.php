<?php

use App\Models\Card;
use App\Models\Entry;
use App\Models\Recurrence;
use App\Models\User;
use App\Services\CardPurchaseCleaner;
use App\Services\OpenMonthService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

function cleaner(): CardPurchaseCleaner
{
    return app(CardPurchaseCleaner::class);
}

function compras(User $u, Card $card, string $mes, int $n, int $cents = 1000): void
{
    foreach (range(1, $n) as $i) {
        makeEntry($u, ['card_id' => $card->id, 'month' => $mes.'-01', 'name' => "Compra {$mes} {$i}", 'amount_cents' => $cents]);
    }
}

it('prévia: conta só as compras suas do cartão no escopo e as do parceiro à parte', function () {
    [$eliel, $ana] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    $outro = makeCard($eliel, 'Pagbank');
    compras($eliel, $card, '2026-09', 3);
    compras($eliel, $card, '2026-10', 2, 500);
    compras($ana, $card, '2026-09', 4);
    compras($eliel, $outro, '2026-09', 5);
    $this->actingAs($eliel);

    expect(cleaner()->preview($eliel, $card, CarbonImmutable::parse('2026-09-01')))->toMatchArray(['count' => 3, 'total' => 3000, 'others' => 4])
        ->and(cleaner()->preview($eliel, $card, null))->toMatchArray(['count' => 5, 'total' => 4000, 'others' => 4]);
});

it('exclui só do mês: do cartão certo, do dono certo, sem tocar nos outros meses', function () {
    [$eliel, $ana] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    $outro = makeCard($eliel, 'Pagbank');
    compras($eliel, $card, '2026-09', 3);
    compras($eliel, $card, '2026-10', 2);
    compras($ana, $card, '2026-09', 4);
    compras($eliel, $outro, '2026-09', 5);
    makeEntry($eliel, ['name' => 'Conta sem cartão', 'month' => '2026-09-01']);
    $this->actingAs($eliel);

    $r = cleaner()->delete($eliel, $card, CarbonImmutable::parse('2026-09-01'), false);

    expect($r['deleted'])->toBe(3)
        ->and(Entry::where('card_id', $card->id)->where('owner_id', $eliel->id)->where('month', '2026-09-01')->count())->toBe(0)
        ->and(Entry::where('card_id', $card->id)->where('owner_id', $eliel->id)->where('month', '2026-10-01')->count())->toBe(2) // outro mês fica
        ->and(Entry::where('card_id', $card->id)->where('owner_id', $ana->id)->count())->toBe(4)   // do parceiro fica
        ->and(Entry::where('card_id', $outro->id)->count())->toBe(5)                                // outro cartão fica
        ->and(Entry::where('name', 'Conta sem cartão')->count())->toBe(1);                           // conta sem cartão fica
});

it('exclui de todos os meses', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    compras($eliel, $card, '2026-09', 2);
    compras($eliel, $card, '2026-10', 3);
    $this->actingAs($eliel);

    expect(cleaner()->delete($eliel, $card, null, false)['deleted'])->toBe(5)->and(Entry::count())->toBe(0);
});

it('parceladas/fixas: SEM parar a regra, a próxima parcela volta; COM parar, não volta', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
    $card = makeCard($eliel, 'Nubank');
    $primeira = fixo($eliel, ['frequency' => 'installments', 'installments_total' => 6, 'charge_day' => 10], 15000, '2026-09-01');
    $primeira->update(['card_id' => $card->id]);
    Entry::where('recurrence_id', $primeira->recurrence_id)->update(['card_id' => $card->id]);
    Recurrence::whereKey($primeira->recurrence_id)->update(['card_id' => $card->id]);
    expect(Entry::count())->toBe(6);

    // só deste mês, sem parar: as outras 5 parcelas continuam
    cleaner()->delete($eliel, $card, CarbonImmutable::parse('2026-09-01'), false);
    expect(Entry::count())->toBe(5);

    // parando a regra a partir de outubro: tudo de outubro em diante some e nada é gerado de novo
    $r = cleaner()->delete($eliel, $card, CarbonImmutable::parse('2026-10-01'), true);
    expect($r['deleted'])->toBe(5)->and(Entry::count())->toBe(0);

    app(OpenMonthService::class)->openRange($eliel->couple_id, CarbonImmutable::parse('2026-09-01'), 12);
    expect(Entry::count())->toBe(0); // a regra parou: nada volta
});

it('fixa "todo mês": excluir de todos os meses parando a regra não deixa nada voltar nos meses futuros', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
    $card = makeCard($eliel, 'Nubank');
    $primeira = fixo($eliel, ['frequency' => 'monthly', 'charge_day' => 5], 3000, '2026-09-01');
    Entry::where('recurrence_id', $primeira->recurrence_id)->update(['card_id' => $card->id]);
    Recurrence::whereKey($primeira->recurrence_id)->update(['card_id' => $card->id]);

    cleaner()->delete($eliel, $card, null, true);
    $this->travelTo(CarbonImmutable::parse('2027-12-01 10:00'));
    app(OpenMonthService::class)->openRange($eliel->couple_id, CarbonImmutable::parse('2027-12-01'), 12);

    expect(Entry::count())->toBe(0);
});

it('desfazer restaura as compras e a regra', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
    $card = makeCard($eliel, 'Nubank');
    $primeira = fixo($eliel, ['frequency' => 'installments', 'installments_total' => 4, 'charge_day' => 10], 15000, '2026-09-01');
    Entry::where('recurrence_id', $primeira->recurrence_id)->update(['card_id' => $card->id]);
    Recurrence::whereKey($primeira->recurrence_id)->update(['card_id' => $card->id]);
    compras($eliel, $card, '2026-09', 2);

    $r = cleaner()->delete($eliel, $card, null, true);
    expect(Entry::count())->toBe(0);

    expect(cleaner()->undo($eliel, $r['undo']))->toBe(6)->and(Entry::count())->toBe(6)
        ->and(Recurrence::first()->ends_on)->toBeNull();
});

it('desfazer não restaura compra de outro dono', function () {
    [$eliel, $ana] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    compras($ana, $card, '2026-09', 2);
    $this->actingAs($ana);
    $r = cleaner()->delete($ana, $card, null, false);

    $this->actingAs($eliel);
    expect(cleaner()->undo($eliel, $r['undo']))->toBe(0)->and(Entry::count())->toBe(0);
});

it('cartão de outro casal não é acessível (ninguém apaga o que não é do casal)', function () {
    [$eliel] = makeCouple();
    [$estranho] = makeCouple('Outro', 'Casal');
    $alheio = makeCard($estranho, 'Alheio');
    compras($estranho, $alheio, '2026-09', 2);

    $this->actingAs($eliel);
    expect(cleaner()->preview($eliel, $alheio, null)['count'])->toBe(0);
    expect(fn () => cleaner()->delete($eliel, $alheio, null, false))->toThrow(AuthorizationException::class);
    expect(Entry::withoutGlobalScopes()->count())->toBe(2);
});

it('todos os cartões: prévia por cartão e exclusão de todos de uma vez (mês ou todos os meses)', function () {
    [$eliel, $ana] = makeCouple();
    $nubank = makeCard($eliel, 'Nubank');
    $ouro = makeCard($eliel, 'Ourocard');
    compras($eliel, $nubank, '2026-09', 3);
    compras($eliel, $ouro, '2026-09', 2, 500);
    compras($eliel, $ouro, '2026-10', 4, 500);
    compras($ana, $nubank, '2026-09', 5);
    makeEntry($eliel, ['name' => 'Sem cartão', 'month' => '2026-09-01']);
    $this->actingAs($eliel);

    $setembro = CarbonImmutable::parse('2026-09-01');
    expect(cleaner()->previewByCard($eliel, $setembro))->toBe([
        ['id' => $nubank->id, 'name' => 'Nubank', 'count' => 3, 'total' => 3000],
        ['id' => $ouro->id, 'name' => 'Ourocard', 'count' => 2, 'total' => 1000],
    ]);

    expect(cleaner()->delete($eliel, null, $setembro, false)['deleted'])->toBe(5)
        ->and(Entry::where('owner_id', $eliel->id)->whereNotNull('card_id')->count())->toBe(4)   // outubro do Ourocard fica
        ->and(Entry::where('owner_id', $ana->id)->count())->toBe(5)                              // do parceiro fica
        ->and(Entry::where('name', 'Sem cartão')->count())->toBe(1);

    expect(cleaner()->delete($eliel, null, null, false)['deleted'])->toBe(4)->and(Entry::whereNotNull('card_id')->where('owner_id', $eliel->id)->count())->toBe(0);
});

it('excluir todos os cartões mantendo as compras: viram lançamentos sem cartão (nada some)', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    compras($eliel, $card, '2026-09', 3);
    $this->actingAs($eliel);

    $r = cleaner()->deleteCards($eliel, false);

    expect($r)->toBe(['cards' => 1, 'purchases' => 0])->and(Card::count())->toBe(0)
        ->and(Entry::count())->toBe(3)->and(Entry::whereNotNull('card_id')->count())->toBe(0);
});

it('excluir todos os cartões apagando as compras: some tudo do cartão e as séries param', function () {
    [$eliel, $ana] = makeCouple();
    $this->actingAs($eliel);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
    $card = makeCard($eliel, 'Nubank');
    $primeira = fixo($eliel, ['frequency' => 'monthly', 'charge_day' => 5], 3000, '2026-09-01');
    Entry::where('recurrence_id', $primeira->recurrence_id)->update(['card_id' => $card->id]);
    Recurrence::whereKey($primeira->recurrence_id)->update(['card_id' => $card->id]);
    compras($eliel, $card, '2026-09', 2);
    compras($ana, $card, '2026-09', 1);

    $r = cleaner()->deleteCards($eliel, true);

    expect($r['cards'])->toBe(1)->and($r['purchases'])->toBe(14)->and(Card::count())->toBe(0)
        ->and(Entry::where('owner_id', $eliel->id)->count())->toBe(0)
        ->and(Entry::where('owner_id', $ana->id)->count())->toBe(1);  // a do parceiro virou lançamento sem cartão

    app(OpenMonthService::class)->openRange($eliel->couple_id, CarbonImmutable::parse('2026-09-01'), 12);
    expect(Entry::where('owner_id', $eliel->id)->count())->toBe(0); // a série parou
});
