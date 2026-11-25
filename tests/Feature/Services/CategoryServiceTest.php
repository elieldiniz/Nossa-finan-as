<?php

use App\Models\Category;
use App\Models\Entry;
use App\Services\CategoryService;

it('adiciona, renomeia e recusa nome repetido', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $service = app(CategoryService::class);

    $pets = $service->add($eliel, 'Pets');
    $service->rename($eliel, $pets, 'Animais');

    expect(Category::where('name', 'Animais')->exists())->toBeTrue()
        ->and(fn () => $service->add($eliel, 'Mercado'))->toThrow(InvalidArgumentException::class);
});

it('remover categoria em uso sem destino é recusado', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    makeEntry($eliel, ['category_id' => $mercado->id]);

    expect(fn () => app(CategoryService::class)->remove($eliel, $mercado))->toThrow(InvalidArgumentException::class)
        ->and(Category::where('name', 'Mercado')->exists())->toBeTrue();
});

it('remover com destino migra os lançamentos e a categoria some', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    $outros = categoryOf($eliel->couple_id, 'Outros');
    $item = makeEntry($eliel, ['category_id' => $mercado->id]);

    app(CategoryService::class)->remove($eliel, $mercado, $outros);

    expect(Category::where('name', 'Mercado')->exists())->toBeFalse()
        ->and(Entry::find($item->id)->category_id)->toBe($outros->id);
});

it('renomear reflete nos lançamentos', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    $item = makeEntry($eliel, ['category_id' => $mercado->id]);

    app(CategoryService::class)->rename($eliel, $mercado, 'Supermercado');

    expect(Entry::with('category')->find($item->id)->category->name)->toBe('Supermercado');
});
