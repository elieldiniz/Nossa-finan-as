<?php

use App\Domain\MonthClosing;
use App\Livewire\Entry\EntryForm;
use App\Livewire\Statement\StatementPage;
use App\Models\Entry;
use Livewire\Livewire;

it('investimento não é gasto: fica fora do fechamento e aparece à parte', function () {
    $r = MonthClosing::calculate([
        ['type' => 'entrada', 'amount' => 500000],
        ['type' => 'saida', 'amount' => 300000],
        ['type' => 'aporte', 'amount' => 80000],
    ]);

    expect($r->income)->toBe(500000)->and($r->expense)->toBe(300000)->and($r->closing)->toBe(200000)
        ->and($r->invested)->toBe(80000)->and($r->afterInvesting())->toBe(120000);
});

it('a aba Investimento mostra só categorias de investimento e rótulos próprios', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('kind', 'aporte')
        ->assertSee('Reserva de emergência')->assertSee('Renda fixa')->assertDontSee('Moradia')->assertDontSee('Salário')
        ->assertSee('Quem investe')->assertSee('Já aplicado')
        ->assertSet('categoryId', categoryOf($eliel->couple_id, 'Reserva de emergência')->id);
});

it('lança um aporte, que aparece no extrato como investimento', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('kind', 'aporte')->set('amount', '300,00')->set('name', 'CDB')->call('save')->assertHasNoErrors();

    $e = Entry::first();
    expect($e->kind)->toBe('aporte')->and($e->amount_cents)->toBe(30000);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', $e->month->format('Y-m'))->assertSee('CDB')->assertSee('Investimento');
});

it('aporte não aceita categoria de gasto', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('kind', 'aporte')->set('amount', '100,00')->set('name', 'X')
        ->set('categoryId', categoryOf($eliel->couple_id, 'Moradia')->id)->call('save')->assertSeeHtml('data-testid="form-error"');

    expect(Entry::count())->toBe(0);
});

it('entrada e aporte nunca ficam presos a um cartão', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel);

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('kind', 'entrada')->set('amount', '500,00')->set('name', 'Bônus')->set('cardId', $card->id)->call('save');

    expect(Entry::first()->card_id)->toBeNull();
});
