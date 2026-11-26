<?php

use App\Livewire\Entry\EditEntry;
use App\Livewire\Home\HomePage;
use App\Livewire\Statement\StatementPage;
use App\Models\Entry;
use App\Services\OpenMonthService;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('o dono edita um lançamento simples e o fechamento muda', function () {
    [$eliel] = makeCouple();
    $item = makeEntry($eliel, ['name' => 'Antes', 'amount_cents' => 5000]);

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => $item])
        ->set('name', 'Depois')->set('amount', '80,00')->call('save')->assertHasNoErrors()->assertRedirect();

    expect($item->fresh()->name)->toBe('Depois')->and($item->fresh()->amount_cents)->toBe(8000);
});

it('o dono exclui um lançamento simples', function () {
    [$eliel] = makeCouple();
    $item = makeEntry($eliel);

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => $item])->call('delete')->assertRedirect();

    expect(Entry::count())->toBe(0);
});

it('o par vê mas não edita nem exclui: 403 e o item fica idêntico', function () {
    [$eliel, $ana] = makeCouple();
    $item = makeEntry($eliel);
    $antes = $item->fresh()->toArray();

    Livewire::actingAs($ana)->test(EditEntry::class, ['entry' => $item])->assertSeeHtml('data-testid="readonly-badge"');
    Livewire::actingAs($ana)->test(EditEntry::class, ['entry' => $item])->call('save')->assertForbidden();
    Livewire::actingAs($ana)->test(EditEntry::class, ['entry' => $item])->call('delete')->assertForbidden();

    expect($item->fresh()->toArray())->toBe($antes);
});

it('quem é de fora do casal nem abre o lançamento', function () {
    [$eliel] = makeCouple();
    [$carlos] = makeCouple('Carlos', 'Dora');
    $item = makeEntry($eliel);

    $this->actingAs($carlos)->get(route('entry.show', $item->id))->assertNotFound();
});

it('recorrente: cada escopo altera só o previsto', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel, valor: 10000);
    app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse('2026-02-01'));
    $fev = Entry::where('month', '2026-02-01')->first();

    Livewire::test(EditEntry::class, ['entry' => $fev])->set('amount', '120,00')->set('scope', 'this')->call('save');
    expect(Entry::where('month', '2026-01-01')->first()->amount_cents)->toBe(10000)
        ->and(Entry::where('month', '2026-02-01')->first()->amount_cents)->toBe(12000);
});

it('"todos, inclusive os anteriores" exige confirmação explícita', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel, valor: 10000);
    $jan = Entry::first();

    $tela = Livewire::test(EditEntry::class, ['entry' => $jan])->set('amount', '150,00')->set('scope', 'all')->call('save')
        ->assertSeeHtml('data-testid="form-error"');
    expect($jan->fresh()->amount_cents)->toBe(10000);

    $tela->set('confirmAll', true)->call('save');
    expect($jan->fresh()->amount_cents)->toBe(15000);
});

it('ajusta vencimento, mês e "já pago" de um lançamento comum', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $e = makeEntry($eliel, ['name' => 'IPVA', 'month' => '2026-09-01', 'due_on' => '2026-09-10']);

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => $e])
        ->assertSet('dueOn', '2026-09-10')->assertSet('month', '2026-09')->assertSeeHtml('data-testid="due-input"')
        ->set('dueOn', '2026-10-15')->set('month', '2026-10')->set('paid', true)->call('save')->assertHasNoErrors();

    $e->refresh();
    expect($e->due_on->toDateString())->toBe('2026-10-15')->and($e->month->format('Y-m'))->toBe('2026-10')->and($e->paid)->toBeTrue()->and($e->paid_by)->toBe($eliel->id);
});

it('recorrente: vencimento e pago valem só para o mês; o resto segue o escopo', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
    $e = fixo($eliel, ['frequency' => 'monthly', 'charge_day' => 5], 30000, '2026-09-01');

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => $e])->set('scope', 'this')
        ->set('dueOn', '2026-09-20')->set('paid', true)->call('save')->assertHasNoErrors();

    $set = Entry::orderBy('month')->get();
    expect($set[0]->due_on->toDateString())->toBe('2026-09-20')->and($set[0]->paid)->toBeTrue()
        ->and($set[1]->due_on->toDateString())->toBe('2026-10-05')->and($set[1]->paid)->toBeFalse();
});

it('muda o dia de cobrança da conta toda', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
    $e = fixo($eliel, ['frequency' => 'monthly', 'charge_day' => 5], 30000, '2026-09-01');

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => $e])->assertSet('chargeDay', 5)->set('chargeDay', 28)->call('save')->assertHasNoErrors();

    expect(Entry::orderBy('month')->pluck('due_on')->map->toDateString()->take(3)->all())->toBe(['2026-09-28', '2026-10-28', '2026-11-28']);
});

it('parcelado: reduzir o nº de parcelas remove as não pagas e preserva as pagas; aumentar cria mais', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
    $e = fixo($eliel, ['frequency' => 'installments', 'installments_total' => 6, 'charge_day' => 10], 15000, '2026-09-01');
    expect(Entry::count())->toBe(6);
    Entry::where('month', '2026-09-01')->update(['paid' => true]);

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => $e->fresh()])->assertSet('installmentsTotal', 6)->set('installmentsTotal', 3)->call('save')->assertHasNoErrors();

    expect(Entry::orderBy('month')->pluck('installment_label')->all())->toBe(['1/3', '2/3', '3/3']);

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => Entry::first()])->set('installmentsTotal', 5)->call('save')->assertHasNoErrors();
    expect(Entry::count())->toBe(5)->and(Entry::orderBy('month')->pluck('installment_label')->last())->toBe('5/5');
});

it('recorrente: definir uma data final apaga os meses depois dela (não pagos) e tirar a data estende de novo', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $this->travelTo(CarbonImmutable::parse('2026-09-15 10:00'));
    $e = fixo($eliel, ['frequency' => 'monthly', 'charge_day' => 5], 30000, '2026-09-01');

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => $e])->set('endMode', 'date')->set('endsOn', '2026-12-31')->call('save')->assertHasNoErrors();
    expect(Entry::count())->toBe(4)->and(Entry::orderBy('month')->get()->last()->month->format('Y-m'))->toBe('2026-12');

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => Entry::first()])->assertSet('endMode', 'date')->set('endMode', 'never')->call('save')->assertHasNoErrors();
    expect(Entry::count())->toBe(12);
});

it('parcelado sem número de parcelas mostra erro ao editar', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $e = fixo($eliel, ['frequency' => 'installments', 'installments_total' => 4], 15000, '2026-09-01');

    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => $e])->set('installmentsTotal', 1)->call('save')->assertHasErrors('installmentsTotal');
});

it('há um caminho visível para editar: lápis no Extrato e nome da conta a vencer no Início', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00'));
    $e = makeEntry($eliel, ['name' => 'Luz', 'month' => '2026-10-01', 'due_on' => '2026-10-07']);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-10')->assertSeeHtml('data-testid="edit-link"')->assertSeeHtml(route('entry.show', $e->id));
    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-10')->assertSeeHtml('data-testid="bill-edit"');
});
