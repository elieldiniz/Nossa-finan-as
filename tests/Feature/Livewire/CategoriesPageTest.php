<?php

use App\Livewire\Categories\CategoriesPage;
use App\Models\Category;
use Livewire\Livewire;

it('remoção de categoria em uso exige destino; com destino migra', function () {
    [$eliel] = makeCouple();
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    $outros = categoryOf($eliel->couple_id, 'Outros');
    $item = makeEntry($eliel, ['category_id' => $mercado->id]);

    $tela = Livewire::actingAs($eliel)->test(CategoriesPage::class)->call('remove', $mercado->id)->assertSeeHtml('data-testid="form-error"');
    expect(Category::where('name', 'Mercado')->exists())->toBeTrue();

    $tela->set("moveTo.{$mercado->id}", $outros->id)->call('remove', $mercado->id);
    expect(Category::where('name', 'Mercado')->exists())->toBeFalse()->and($item->fresh()->category_id)->toBe($outros->id);
});

it('renomear reflete nos lançamentos e adicionar cria nova', function () {
    [$eliel] = makeCouple();
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    $item = makeEntry($eliel, ['category_id' => $mercado->id]);

    Livewire::actingAs($eliel)->test(CategoriesPage::class)
        ->set("names.{$mercado->id}", 'Supermercado')->call('rename', $mercado->id)
        ->set('newName', 'Pets')->call('add');

    expect($item->fresh()->category->name)->toBe('Supermercado')->and(Category::where('name', 'Pets')->exists())->toBeTrue();
});
