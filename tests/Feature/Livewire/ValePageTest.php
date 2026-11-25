<?php

use App\Livewire\Vale\ValePage;
use App\Models\Vale;
use App\Models\ValeSpend;
use Livewire\Livewire;

it('vale de 2.250 com gasto de 250 mostra 2.000,00', function () {
    [$eliel] = makeCouple();

    $tela = Livewire::actingAs($eliel)->test(ValePage::class)->set('month', '2026-09')
        ->set('valeAmount', '2.250,00')->call('saveVale')
        ->set('spendName', 'Lanche')->set('spendAmount', '250,00')->call('addSpend')
        ->assertSee('R$ 2.000,00');

    expect(Vale::first()->amount_cents)->toBe(225000);
    $tela->call('nextMonth')->assertSee('R$ 2.250,00'); // a sobra não passa para o mês seguinte
});

it('gasto sem vale definido é recusado', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(ValePage::class)->set('spendName', 'x')->set('spendAmount', '10,00')->call('addSpend')
        ->assertSeeHtml('data-testid="form-error"');
});

it('cada um tem o seu vale; o do par é somente leitura', function () {
    [$eliel, $ana] = makeCouple();
    makeVale($eliel, 100000);
    makeVale($ana, 200000);

    Livewire::actingAs($eliel)->test(ValePage::class)->assertSee('R$ 1.000,00')->assertSee('R$ 2.000,00')
        ->call('setView', 'par')->assertSee('R$ 2.000,00')->assertDontSee('Novo gasto pessoal');
});

it('o par não remove gasto do vale do outro', function () {
    [$eliel, $ana] = makeCouple();
    $vale = makeVale($eliel);
    $gasto = new ValeSpend(['vale_id' => $vale->id, 'owner_id' => $eliel->id, 'name' => 'x', 'amount_cents' => 100, 'month' => '2026-09-01']);
    $gasto->couple_id = $eliel->couple_id;
    $gasto->saveQuietly();

    Livewire::actingAs($ana)->test(ValePage::class)->call('removeSpend', $gasto->id)->assertForbidden();

    expect(ValeSpend::count())->toBe(1);
});
