<?php

use App\Livewire\Entry\EntryForm;
use App\Models\Entry;
use App\Models\Recurrence;
use Carbon\CarbonImmutable;
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

it('o formulário começa no mês de verdade, mesmo que ela esteja olhando outro mês', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00'));

    Livewire::actingAs($eliel)->withQueryParams(['m' => '2027-03'])->test(EntryForm::class)->assertSet('month', '2026-11');
});

it('recorrente criada no formulário aparece em novembro, dezembro e janeiro', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00'));

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('amount', '163,00')->set('name', 'Havan')
        ->set('categoryId', categoryOf($eliel->couple_id, 'Outros')->id)->set('repeat', 'monthly')->call('save')->assertHasNoErrors();

    foreach (['2026-11', '2026-12', '2027-01', '2027-10'] as $mes) {
        expect(Entry::where('name', 'Havan')->where('month', $mes.'-01')->count())->toBe(1);
    }
    expect(Entry::where('name', 'Havan')->count())->toBe(12);
});

it('parcelado: nº de parcelas e última parcela andam juntos', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00'));

    $tela = Livewire::actingAs($eliel)->test(EntryForm::class)->set('amount', '150,00')->set('repeat', 'installments')
        ->set('installmentsTotal', 10)->assertSet('lastMonth', '2027-08')
        ->assertSeeHtml('data-testid="installments-summary"')->assertSee('10 parcelas de R$ 150,00')->assertSee('total R$ 1.500,00')->assertSee('de 11/2026 a 08/2027');

    $tela->set('lastMonth', '2027-03')->assertSet('installmentsTotal', 5);
    $tela->set('month', '2027-01')->assertSet('lastMonth', '2027-05');
    $tela->set('lastMonth', '2026-01')->assertSet('installmentsTotal', 2); // antes do início: mínimo 2
});

it('parcelado sem número de parcelas mostra o erro e não grava', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('amount', '50,00')->set('name', 'SHEIN')
        ->set('categoryId', categoryOf($eliel->couple_id, 'Outros')->id)->set('repeat', 'installments')->call('save')
        ->assertSee('Falta preencher: número de parcelas');

    expect(Entry::count())->toBe(0);
});

it('compra parcelada que já começou: marca como pagas as parcelas dos meses anteriores', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00'));

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('amount', '150,00')->set('name', 'SHEIN')
        ->set('categoryId', categoryOf($eliel->couple_id, 'Outros')->id)->set('repeat', 'installments')->set('installmentsTotal', 6)->set('month', '2026-08')
        ->assertSeeHtml('data-testid="past-paid-option"')->call('save')->assertHasNoErrors();

    $parcelas = Entry::orderBy('month')->get();
    expect($parcelas)->toHaveCount(6)
        ->and($parcelas->pluck('month')->map->format('Y-m')->all())->toBe(['2026-08', '2026-09', '2026-10', '2026-11', '2026-12', '2027-01'])
        ->and($parcelas->pluck('paid')->all())->toBe([true, true, true, false, false, false])
        ->and($parcelas[3]->installment_label)->toBe('4/6');
});

it('sem marcar o check, as parcelas antigas ficam pendentes; e início no mês atual nem mostra o check', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00'));

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('repeat', 'monthly')->assertDontSeeHtml('data-testid="past-paid-option"');

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('amount', '80,00')->set('name', 'Academia')
        ->set('categoryId', categoryOf($eliel->couple_id, 'Outros')->id)->set('repeat', 'monthly')->set('month', '2026-09')->set('markPastPaid', false)->call('save');

    expect(Entry::where('month', '<', '2026-11-01')->where('paid', true)->count())->toBe(0)->and(Entry::where('month', '2026-09-01')->count())->toBe(1);
});

it('conta que começou há mais de 12 meses não deixa buracos até hoje', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00'));

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('amount', '80,00')->set('name', 'Plano')
        ->set('categoryId', categoryOf($eliel->couple_id, 'Outros')->id)->set('repeat', 'monthly')->set('month', '2025-01')->call('save');

    // jan/2025 até out/2027 (hoje + 11 meses) = 34 meses, sem lacunas
    expect(Entry::count())->toBe(34)->and(Entry::where('month', '2025-09-01')->exists())->toBeTrue();
});
