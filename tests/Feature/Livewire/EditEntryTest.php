<?php

use App\Livewire\Entry\EditEntry;
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
