<?php

use App\Livewire\Entry\EntryForm;
use App\Models\Category;
use App\Models\Entry;
use App\Services\CategoryService;
use App\Services\EntryService;
use Livewire\Livewire;

it('o casal nasce com categorias de entrada (Salário, Renda extra, Reembolso, Rendimentos) e "Outros" serve para as duas', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    expect(Category::where('kind', 'entrada')->orderBy('position')->pluck('name')->all())->toBe(['Salário', 'Renda extra', 'Reembolso', 'Rendimentos'])
        ->and(Category::where('name', 'Outros')->value('kind'))->toBe('ambas')
        ->and(Category::where('name', 'Moradia')->value('kind'))->toBe('saida');
});

it('em Entrada mostra só categorias de entrada e sugere Salário', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)
        ->assertSee('Moradia')->assertDontSee('Salário')
        ->set('kind', 'entrada')
        ->assertSee('Salário')->assertSee('Renda extra')->assertSee('Outros')->assertDontSee('Moradia')->assertDontSee('Mercado')
        ->assertSet('categoryId', categoryOf($eliel->couple_id, 'Salário')->id);
});

it('em Entrada esconde "Pago com" e "Gasto do casal" e troca os rótulos', function () {
    [$eliel] = makeCouple();
    $tela = Livewire::actingAs($eliel)->test(EntryForm::class);

    $tela->assertSee('Pago com')->assertSee('Já está pago')->assertSee('Gasto do casal')->assertSee('De quem')
        ->set('kind', 'entrada')->set('repeat', 'monthly')
        ->assertDontSee('Pago com')->assertDontSee('Gasto do casal')->assertDontSee('Já está pago')
        ->assertSee('Já recebido')->assertSee('Quem recebe')->assertSee('Recebe no dia')->assertSee('Salário de setembro');
});

it('salvar uma entrada grava sem cartão e sem marcar como gasto do casal', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $cartao = makeCard($eliel);

    Livewire::test(EntryForm::class)->set('month', '2026-09')
        ->set('cardId', $cartao->id)->set('joint', true)
        ->set('kind', 'entrada')->set('amount', 'R$ 3.000,00')->set('name', 'Salário')->set('paid', true)->call('save')->assertHasNoErrors();

    $e = Entry::first();
    expect($e->kind)->toBe('entrada')->and($e->amount_cents)->toBe(300000)->and($e->card_id)->toBeNull()
        ->and($e->joint)->toBeFalse()->and($e->paid)->toBeTrue()->and($e->category->name)->toBe('Salário');
});

it('o valor aceita "3000" e "R$ 3.000,00" e grava o mesmo', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    foreach (['3000', 'R$ 3.000,00', '3.000'] as $texto) {
        Livewire::test(EntryForm::class)->set('month', '2026-09')->set('kind', 'entrada')->set('amount', $texto)->set('name', 'Salário')->call('save');
    }

    expect(Entry::pluck('amount_cents')->unique()->all())->toBe([300000]);
});

it('categoria de saída em uma entrada (ou o contrário) é recusada', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $base = ['name' => 'x', 'amount_cents' => 1000, 'month' => '2026-09-01'];

    expect(fn () => app(EntryService::class)->create($eliel, $base + ['kind' => 'entrada', 'category_id' => categoryOf($eliel->couple_id, 'Moradia')->id]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(EntryService::class)->create($eliel, $base + ['kind' => 'saida', 'category_id' => categoryOf($eliel->couple_id, 'Salário')->id]))
        ->toThrow(InvalidArgumentException::class)
        ->and(Entry::count())->toBe(0);

    app(EntryService::class)->create($eliel, $base + ['kind' => 'entrada', 'category_id' => categoryOf($eliel->couple_id, 'Outros')->id]);
    expect(Entry::count())->toBe(1);
});

it('o erro de campo some assim que a pessoa corrige', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('kind', 'entrada')->set('amount', '10')->set('name', '')->call('save')
        ->assertHasErrors('name')
        ->set('name', 'Salário')->assertHasNoErrors('name');
});

it('nova categoria pode ser de entrada e só aparece no formulário de entrada', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    app(CategoryService::class)->add($eliel, 'Bônus', 'entrada');

    Livewire::test(EntryForm::class)->assertDontSee('Bônus')->set('kind', 'entrada')->assertSee('Bônus');
});

it('mover lançamentos para categoria de outro tipo é recusado', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    makeEntry($eliel, ['category_id' => $mercado->id]);

    expect(fn () => app(CategoryService::class)->remove($eliel, $mercado, categoryOf($eliel->couple_id, 'Salário')))
        ->toThrow(InvalidArgumentException::class);
});

it('o app é claro: não herda o modo escuro do sistema', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get(route('dashboard'))->assertDontSee('flux.appearance', false)->assertDontSee('class="dark"', false);
    expect(file_get_contents(resource_path('css/app.css')))->toContain('color-scheme: light');
});

it('"Outros" aparece por último nas categorias', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    expect(Category::forKind('entrada')->orderBy('position')->pluck('name')->last())->toBe('Outros')
        ->and(Category::forKind('saida')->orderBy('position')->pluck('name')->last())->toBe('Outros');
});
