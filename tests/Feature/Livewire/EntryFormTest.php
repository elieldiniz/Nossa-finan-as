<?php

use App\Livewire\Entry\EntryForm;
use App\Models\Entry;
use App\Models\Recurrence;
use Livewire\Livewire;

function preencher($tela, array $extra = [])
{
    [$eliel] = [auth()->user()];
    $tela->set('month', '2026-09')->set('amount', '50,00')->set('name', 'Mercado')
        ->set('categoryId', categoryOf($eliel->couple_id, 'Mercado')->id);
    foreach ($extra as $k => $v) {
        $tela->set($k, $v);
    }

    return $tela;
}

it('salvar persiste os campos e "já está pago" desmarcado salva não pago', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    preencher(Livewire::test(EntryForm::class))->call('save')->assertHasNoErrors()->assertRedirect();

    $e = Entry::first();
    expect($e->amount_cents)->toBe(5000)->and($e->name)->toBe('Mercado')->and($e->paid)->toBeFalse()
        ->and($e->owner_id)->toBe($eliel->id)->and($e->month->toDateString())->toBe('2026-09-01');
});

it('sem categoria é recusado', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    Livewire::test(EntryForm::class)->set('amount', '10,00')->set('name', 'x')->call('save')->assertHasErrors('categoryId');

    expect(Entry::count())->toBe(0);
});

it('valor inválido ou zero mostra erro e não grava', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    preencher(Livewire::test(EntryForm::class), ['amount' => '0'])->call('save')->assertSeeHtml('data-testid="form-error"');
    preencher(Livewire::test(EntryForm::class), ['amount' => 'abc'])->call('save')->assertSeeHtml('data-testid="form-error"');

    expect(Entry::count())->toBe(0);
});

it('em "Nós" o dono padrão é quem lança e pode ser trocado para o par', function () {
    [$eliel, $ana] = makeCouple();
    $this->actingAs($eliel);

    $tela = Livewire::test(EntryForm::class)->call('setView', 'nos');
    expect($tela->get('ownerId'))->toBe($eliel->id);

    preencher($tela, ['ownerId' => $ana->id])->call('save');

    expect(Entry::first()->owner_id)->toBe($ana->id);
});

it('dono fora do casal é recusado', function () {
    [$eliel] = makeCouple();
    [$carlos] = makeCouple('Carlos', 'Dora');
    $this->actingAs($eliel);

    preencher(Livewire::test(EntryForm::class), ['ownerId' => $carlos->id])->call('save')->assertSeeHtml('data-testid="form-error"');

    expect(Entry::withoutGlobalScopes()->count())->toBe(0);
});

it('recorrente com data final gera a regra correta', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    preencher(Livewire::test(EntryForm::class), ['repeat' => 'monthly', 'chargeDay' => 25, 'endMode' => 'date', 'endsOn' => '2027-12-23'])->call('save');

    $regra = Recurrence::first();
    expect($regra->frequency)->toBe('monthly')->and($regra->charge_day)->toBe(25)->and($regra->ends_on->toDateString())->toBe('2027-12-23')
        ->and(Entry::first()->recurrence_id)->toBe($regra->id);
});

it('parcelado grava a regra e marca "1/3"', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    preencher(Livewire::test(EntryForm::class), ['repeat' => 'installments', 'installmentsTotal' => 3])->call('save');

    expect(Entry::first()->installment_label)->toBe('1/3')->and(Recurrence::first()->installments_total)->toBe(3);
});

it('lançamento de pessoa de fora não aparece para o outro casal', function () {
    [$eliel] = makeCouple();
    [$carlos] = makeCouple('Carlos', 'Dora');
    $this->actingAs($eliel);
    preencher(Livewire::test(EntryForm::class))->call('save');

    $this->actingAs($carlos);
    expect(Entry::count())->toBe(0);
});

it('toque duplo em Salvar não cria o lançamento duas vezes', function () {
    [$eliel] = makeCouple();
    $card = makeCard($eliel, 'Picpay');

    $tela = Livewire::actingAs($eliel)->test(EntryForm::class)
        ->set('amount', '19,00')->set('name', 'Parcela picpay')->set('cardId', $card->id)
        ->set('categoryId', categoryOf($eliel->couple_id, 'Outros')->id);
    $tela->call('save')->call('save');

    expect(Entry::count())->toBe(1);
});

it('sem categoria mostra o que falta junto do botão', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('amount', '19,00')->set('name', 'X')->call('save')
        ->assertSeeHtml('data-testid="form-summary"')->assertSee('Falta preencher: categoria');
});
