<?php

use App\Livewire\People\PeoplePage;
use App\Models\PersonDebt;
use Livewire\Livewire;

it('Madrinha mostra +115,00 no caso da planilha', function () {
    [$eliel] = cenarioPlanilha();

    Livewire::actingAs($eliel)->test(PeoplePage::class)->set('month', '2026-09')
        ->assertSee('+R$ 115,00')->assertSee('Glória')->assertSee('Mãe');
});

it('3 parcelas com 2 pagas mostram 100,00 e quitada zera no mês seguinte', function () {
    [$eliel] = makeCouple();
    $pessoa = makePerson($eliel);

    $tela = Livewire::actingAs($eliel)->test(PeoplePage::class)->set('month', '2026-07')
        ->set('personId', $pessoa->id)->set('direction', 'receber')->set('amount', '300,00')->set('installments', 3)->call('addDebt')
        ->assertHasNoErrors();

    $parcelas = PersonDebt::first()->installments;
    $tela->call('payInstallment', $parcelas[0]->id);
    $tela->set('month', '2026-08')->call('payInstallment', $parcelas[1]->id);

    $tela->set('month', '2026-09')->assertSeeHtml('data-testid="net"')->assertSee('+R$ 100,00');

    $tela->call('payInstallment', $parcelas[2]->id)->set('month', '2026-10')->assertSeeHtml('data-testid="net">R$ 0,00<');
});

it('pessoa sem nome é recusada', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(PeoplePage::class)->set('personName', '')->call('addPerson')->assertHasErrors('personName');
});

it('na visão do par não dá para lançar pendência', function () {
    [$eliel] = makeCouple();
    $pessoa = makePerson($eliel);

    Livewire::actingAs($eliel)->test(PeoplePage::class)->call('setView', 'par')
        ->set('personId', $pessoa->id)->set('amount', '10,00')->call('addDebt')->assertSeeHtml('data-testid="form-error"');

    expect(PersonDebt::count())->toBe(0);
});
