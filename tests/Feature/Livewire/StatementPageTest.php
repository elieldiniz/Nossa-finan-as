<?php

use App\Livewire\Statement\StatementPage;
use App\Models\Entry;
use App\Models\ValeSpend;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('"50 mercado" cria saída de 50,00 em "Outros", não paga', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')
        ->set('quick', '50 mercado')->call('quickAdd')->assertHasNoErrors()->assertSee('mercado');

    $entry = Entry::withoutGlobalScopes()->first();
    expect($entry->amount_cents)->toBe(5000)->and($entry->paid)->toBeFalse()
        ->and($entry->category->name)->toBe('Outros')->and($entry->kind)->toBe('saida');
});

it('frase fora do formato é rejeitada sem gravar nada', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('quick', 'mercado')->call('quickAdd')
        ->assertSeeHtml('data-testid="quick-error"');

    expect(Entry::withoutGlobalScopes()->count())->toBe(0);
});

it('filtra Pendentes e Pagos e busca por nome', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['name' => 'Aluguel', 'paid' => true]);
    makeEntry($eliel, ['name' => 'Padaria', 'paid' => false]);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')
        ->call('setFilter', 'pending')->assertSee('Padaria')->assertDontSee('Aluguel')
        ->call('setFilter', 'paid')->assertSee('Aluguel')->assertDontSee('Padaria')
        ->call('setFilter', 'all')->set('search', 'alu')->assertSee('Aluguel')->assertDontSee('Padaria');
});

it('o dono marca como pago e isso persiste', function () {
    [$eliel] = makeCouple();
    $item = makeEntry($eliel);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->call('togglePaid', $item->id);

    expect($item->fresh()->paid)->toBeTrue();
});

it('o par não marca pago no item do outro: ação direta é recusada e o item fica igual', function () {
    [$eliel, $ana] = makeCouple();
    $item = makeEntry($eliel);

    Livewire::actingAs($ana)->test(StatementPage::class)->set('month', '2026-09')
        ->call('togglePaid', $item->id)->assertForbidden();

    expect($item->fresh()->paid)->toBeFalse();
});

it('na visão do par não dá para lançar rápido', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(StatementPage::class)->call('setView', 'par')
        ->set('quick', '10 teste')->call('quickAdd');

    expect(Entry::withoutGlobalScopes()->count())->toBe(0);
});

it('o Extrato mostra a palavra do status e explica o que aconteceu ao marcar e desmarcar', function () {
    [$eliel] = makeCouple();
    $conta = makeEntry($eliel, ['name' => 'Luz', 'due_on' => '2026-09-20']);

    $tela = Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')
        ->assertSeeHtml('data-testid="status">A pagar<');

    $tela->call('togglePaid', $conta->id)->assertSeeHtml('data-testid="status">✓ Pago<')->assertSee('“Luz” marcada como paga ✓')->assertSee('Para desfazer, toque no ✓ de novo');

    $tela->call('togglePaid', $conta->id)->assertSeeHtml('data-testid="status">A pagar<')->assertSee('voltou para “a pagar”')->assertSee('volta para Contas a vencer');
});

it('entrada e investimento usam palavras próprias', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['kind' => 'entrada', 'name' => 'Salário', 'category_id' => categoryOf($eliel->couple_id, 'Salário')->id]);
    makeEntry($eliel, ['kind' => 'aporte', 'name' => 'CDB', 'category_id' => categoryOf($eliel->couple_id, 'Renda fixa')->id, 'paid' => true]);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->assertSee('A receber')->assertSee('✓ Aplicado');
});

it('na visão "Nós" cada um vê os lançamentos e parcelas dos dois', function () {
    [$eliel, $ana] = makeCouple();
    makeEntry($eliel, ['name' => 'Parcela do Eliel', 'installment_label' => '1/3']);
    makeEntry($ana, ['name' => 'Parcela da Ana', 'installment_label' => '2/4']);

    foreach ([$eliel, $ana] as $quem) {
        Livewire::actingAs($quem)->test(StatementPage::class)->set('month', '2026-09')
            ->assertSee('Parcela do Eliel')->assertSee('Parcela da Ana');
    }

    Livewire::actingAs($ana)->test(StatementPage::class)->set('month', '2026-09')->call('setView', 'eu')
        ->assertSee('Parcela da Ana')->assertDontSee('Parcela do Eliel');
});

it('a linha do cartão abre e mostra cada compra com categoria e parcela', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'SHEIN', 'amount_cents' => 12000, 'installment_label' => '2/5', 'category_id' => categoryOf($eliel->couple_id, 'Lazer')->id]);
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'Uber', 'amount_cents' => 2400]);

    $tela = Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')
        ->assertSee('Cartão Nubank')->assertSeeHtml('data-testid="card-purchases">SHEIN, Uber<')->assertDontSeeHtml('data-testid="card-lines"');

    $tela->call('toggleCard', $card->id)->assertSeeHtml('data-testid="card-lines"')->assertSee('SHEIN')->assertSee('Lazer · parcela 2/5')->assertSee('R$ 120,00')->assertSee('Uber')->assertSee('R$ 24,00');
    expect(substr_count($tela->html(), 'data-testid="card-line"'))->toBe(2);

    $tela->call('toggleCard', $card->id)->assertDontSeeHtml('data-testid="card-lines"');
});

it('a busca encontra o cartão pelo nome de uma compra', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'SHEIN', 'amount_cents' => 12000]);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->set('search', 'shein')->assertSee('Cartão Nubank');
});

it('na linha do cartão aparece o nome das compras sem abrir (com "e mais N" quando são muitas)', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Pagbank');
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'Mercado Livre', 'amount_cents' => 12700]);

    $tela = Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->assertSeeHtml('data-testid="card-purchases">Mercado Livre<');

    foreach (['SHEIN', 'Uber', 'Netflix'] as $nome) {
        makeEntry($eliel, ['card_id' => $card->id, 'name' => $nome, 'amount_cents' => 1000]);
    }

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->assertSeeHtml('data-testid="card-purchases">Mercado Livre, SHEIN e mais 2<');
});

it('o Extrato mostra o Vale (restante e gastos), fora do saldo, só na lista "Todos"', function () {
    [$eliel] = makeCouple();
    $vale = makeVale($eliel, 225000);
    $spend = new ValeSpend(['vale_id' => $vale->id, 'owner_id' => $eliel->id, 'name' => 'Mercadão', 'amount_cents' => 45000, 'month' => '2026-09-01']);
    $spend->couple_id = $eliel->couple_id;
    $spend->save();
    makeEntry($eliel, ['name' => 'Luz', 'amount_cents' => 10000]);

    $tela = Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')
        ->assertSeeHtml('data-testid="vale-sub">Mercadão<')->assertSee('R$ 1.800,00')->assertSee('restante · fora do saldo');

    // o saldo do mês não muda por causa do vale (só a Luz de R$ 100,00 é saída)
    expect(app(MonthSummary::class)->build($eliel, CarbonImmutable::parse('2026-09-01'))['closing']->expense)->toBe(10000);

    $tela->call('toggleCard', 0)->assertSee('Mercadão')->assertSee('Gasto do vale')->assertSee('R$ 450,00');
    $tela->call('setFilter', 'pending')->assertDontSee('fora do saldo');
});

it('sem vale cadastrado a linha não aparece', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->assertDontSee('fora do saldo');
});

it('cada linha do Extrato mostra o seu ícone (lançamento, cartão, vale)', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Nubank');
    makeEntry($eliel, ['name' => 'Luz']);
    makeEntry($eliel, ['card_id' => $card->id, 'name' => 'SHEIN']);
    makeVale($eliel, 100000);

    $html = Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->html();

    // um <svg> por linha (lançamento, cartão e vale) dentro das caixinhas coloridas, além do lápis
    expect(substr_count($html, 'rounded-2xl bg-'))->toBeGreaterThanOrEqual(3)
        ->and(preg_match_all('/size-10 shrink-0[^>]*><svg/', $html))->toBe(3);
});
