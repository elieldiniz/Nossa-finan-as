<?php

use App\Livewire\Recurring\RecurringPage;
use App\Models\Entry;
use Livewire\Livewire;

function salvarRecorrente($tela, array $extra = [])
{
    $categoria = categoryOf(auth()->user()->couple_id, 'Assinatura')->id;

    return $tela->set('month', '2026-09')->set('name', 'Streaming')->set('amount', '39,90')->set('categoryId', $categoria)->set('chargeDay', 5)
        ->tap(fn ($t) => collect($extra)->each(fn ($v, $k) => $t->set($k, $v)))->call('save');
}

it('o item criado aparece na lista e no mês', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    salvarRecorrente(Livewire::test(RecurringPage::class))->assertHasNoErrors()->assertSee('Streaming');

    expect(Entry::where('month', '2026-09-01')->where('name', 'Streaming')->exists())->toBeTrue();
});

it('o total soma os recorrentes mensais ativos e o anual entra só no mês de cobrança', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    $tela = Livewire::test(RecurringPage::class);
    salvarRecorrente($tela);
    salvarRecorrente($tela, ['name' => 'Seguro', 'amount' => '1.200,00', 'frequency' => 'yearly']);

    $tela->set('month', '2026-09')->assertSeeHtml('data-testid="total"')->assertSee('R$ 1.239,90')
        ->set('month', '2026-10')->assertSee('R$ 39,90')->assertDontSee('R$ 1.239,90');
});

it('recorrente com data final some depois dela', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    $tela = Livewire::test(RecurringPage::class);
    salvarRecorrente($tela, ['endMode' => 'date', 'endsOn' => '2026-10-23']);

    $tela->set('month', '2026-11')->assertSee('fora deste mês');
});
