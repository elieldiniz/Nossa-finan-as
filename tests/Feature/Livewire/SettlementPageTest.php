<?php

use App\Livewire\Settlement\SettlementPage;
use Livewire\Livewire;

it('200 pagos só pelo Eliel: "Ana deve R$ 100,00 a Eliel"; após acertar mostra 0,00', function () {
    [$eliel, $ana] = makeCouple();
    makeEntry($eliel, ['joint' => true, 'amount_cents' => 20000, 'paid_by' => $eliel->id]);

    $tela = Livewire::actingAs($eliel)->test(SettlementPage::class)->set('month', '2026-09')
        ->assertSeeHtml('data-testid="settlement-text"')->assertSee('Ana deve a Eliel')->assertSee('R$ 100,00');

    $tela->call('settle')->assertSee('Tudo acertado')->assertSee('R$ 0,00');
});

it('gastos que não são do casal ficam fora do acerto', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['joint' => false, 'amount_cents' => 20000]);

    Livewire::actingAs($eliel)->test(SettlementPage::class)->set('month', '2026-09')->assertSee('Tudo acertado');
});

it('a divisão pode mudar e é guardada', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['joint' => true, 'amount_cents' => 10000]);

    Livewire::actingAs($eliel)->test(SettlementPage::class)->set('month', '2026-09')->set('firstPercent', 60)->assertSee('R$ 40,00');

    expect($eliel->couple->fresh()->split_first_percent)->toBe(60);
});
