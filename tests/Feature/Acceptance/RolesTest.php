<?php

use App\Livewire\Entry\EditEntry;
use App\Livewire\Statement\StatementPage;
use App\Models\Entry;
use Livewire\Livewire;

it('o lançamento do parceiro A é visível ao B após recarregar, e quem é de fora não vê nada', function () {
    [$eliel, $ana] = makeCouple();
    [$carlos] = makeCouple('Carlos', 'Dora');
    makeEntry($eliel, ['name' => 'Só do Eliel']);

    Livewire::actingAs($ana)->test(StatementPage::class)->set('month', '2026-09')->assertSee('Só do Eliel');
    Livewire::actingAs($carlos)->test(StatementPage::class)->set('month', '2026-09')->assertDontSee('Só do Eliel');
});

it('escrita no item do par é recusada e o item fica idêntico', function () {
    [$eliel, $ana] = makeCouple();
    $item = makeEntry($eliel, ['name' => 'Original']);
    $antes = $item->fresh()->toArray();

    Livewire::actingAs($ana)->test(StatementPage::class)->set('month', '2026-09')->call('togglePaid', $item->id)->assertForbidden();
    Livewire::actingAs($ana)->test(EditEntry::class, ['entry' => $item])->set('name', 'Hackeado')->call('save')->assertForbidden();
    Livewire::actingAs($ana)->test(EditEntry::class, ['entry' => $item])->call('delete')->assertForbidden();

    expect($item->fresh()->toArray())->toBe($antes)->and(Entry::count())->toBe(1);
});

it('cada um lança no seu nome e o outro vê em somente leitura', function () {
    [$eliel, $ana] = makeCouple();

    Livewire::actingAs($ana)->test(StatementPage::class)->set('month', '2026-09')->set('quick', '30 padaria')->call('quickAdd');
    $item = Entry::first();

    expect($item->owner_id)->toBe($ana->id);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->call('setView', 'par')
        ->assertSee('padaria')->assertSeeHtml('data-testid="readonly-badge"');
});
