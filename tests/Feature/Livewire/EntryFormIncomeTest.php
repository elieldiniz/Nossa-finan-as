<?php

use App\Livewire\Entry\EditEntry;
use App\Livewire\Entry\EntryForm;
use App\Livewire\Home\HomePage;
use App\Livewire\Statement\StatementPage;
use App\Models\Category;
use App\Models\Entry;
use App\Services\CategoryService;
use App\Services\EntryService;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;
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

it('em Entrada esconde "Pago com" e troca os rótulos', function () {
    [$eliel] = makeCouple();
    $tela = Livewire::actingAs($eliel)->test(EntryForm::class);

    $tela->assertSee('Pago com')->assertSee('Já está pago')->assertSee('De quem')
        ->set('kind', 'entrada')->set('repeat', 'monthly')
        ->assertDontSee('Pago com')->assertDontSee('Já está pago')
        ->assertSee('Já recebido')->assertSee('Quem recebe')->assertSee('Recebe no dia')->assertSee('Salário de setembro');
});

it('salvar uma entrada grava sem cartão', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $cartao = makeCard($eliel);

    Livewire::test(EntryForm::class)->set('month', '2026-09')
        ->set('cardId', $cartao->id)
        ->set('kind', 'entrada')->set('amount', 'R$ 3.000,00')->set('name', 'Salário')->set('paid', true)->call('save')->assertHasNoErrors();

    $e = Entry::first();
    expect($e->kind)->toBe('entrada')->and($e->amount_cents)->toBe(300000)->and($e->card_id)->toBeNull()
        ->and($e->paid)->toBeTrue()->and($e->category->name)->toBe('Salário');
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

it('lança o 13º em dezembro: escolhe o mês e ele já conta na previsão daquele mês', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-25 10:00'));

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('kind', 'entrada')->set('amount', '3.000,00')->set('name', '13º salário')
        ->set('month', '2026-12')->call('save')->assertHasNoErrors()->assertRedirect();

    $e = Entry::first();
    expect($e->month->format('Y-m'))->toBe('2026-12')->and($e->kind)->toBe('entrada')->and($e->amount_cents)->toBe(300000);

    $dez = app(MonthSummary::class)->build($eliel, CarbonImmutable::parse('2026-12-01'));
    $nov = app(MonthSummary::class)->build($eliel, CarbonImmutable::parse('2026-11-01'));
    expect($dez['closing']->income)->toBe(300000)->and($nov['closing']->income)->toBe(0);
});

it('mês inválido mostra o erro e não grava', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('kind', 'entrada')->set('amount', '10,00')->set('name', 'X')->set('month', 'abc')->call('save');

    expect(Entry::count())->toBe(0);
});

it('recorrente "Começa em" também aceita mês futuro', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(EntryForm::class)->set('kind', 'entrada')->set('amount', '500,00')->set('name', 'Aluguel recebido')
        ->set('month', '2027-02')->set('repeat', 'monthly')->call('save')->assertHasNoErrors();

    expect(Entry::first()->month->format('Y-m'))->toBe('2027-02');
});

it('o formulário diz para qual mês o lançamento vai (para o 13º não cair no mês errado)', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-11-27 10:00'));

    $tela = Livewire::actingAs($eliel)->test(EntryForm::class)->set('kind', 'entrada')->assertSeeHtml('data-testid="month-target"')->assertSee('Este lançamento vai para novembro de 2026');
    $tela->set('month', '2026-12')->assertSee('Este lançamento vai para dezembro de 2026')->assertSee('só soma nas Entradas daquele mês');
    $tela->set('month', 'abc')->assertDontSeeHtml('data-testid="month-target"');
});

it('entradas ainda não recebidas aparecem como "a receber" no Extrato e no Início, sem mudar o total', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $sal = categoryOf($eliel->couple_id, 'Salário')->id;
    makeEntry($eliel, ['kind' => 'entrada', 'name' => 'Salário', 'amount_cents' => 400000, 'paid' => true, 'category_id' => $sal, 'month' => '2026-11-01']);
    makeEntry($eliel, ['kind' => 'entrada', 'name' => '13º', 'amount_cents' => 500000, 'paid' => false, 'category_id' => $sal, 'month' => '2026-11-01']);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-11')
        ->assertSee('+R$ 9.000,00')->assertSeeHtml('data-testid="pending-income"')->assertSee('R$ 5.000,00 a receber');
    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-11')->assertSeeHtml('data-testid="income"')->assertSee('R$ 5.000,00 a receber');

    // mover o 13º para dezembro (editando o mês) tira do total de novembro e coloca em dezembro
    $decimo = Entry::where('name', '13º')->first();
    Livewire::actingAs($eliel)->test(EditEntry::class, ['entry' => $decimo])->set('month', '2026-12')->call('save')->assertHasNoErrors();

    $nov = Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-11')->assertSee('+R$ 4.000,00')->assertDontSeeHtml('data-testid="pending-income"');
    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-12')->assertSee('+R$ 5.000,00')->assertSee('R$ 5.000,00 a receber');
});

it('sem entradas a receber o "a receber" não aparece', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['kind' => 'entrada', 'name' => 'Salário', 'amount_cents' => 400000, 'paid' => true, 'category_id' => categoryOf($eliel->couple_id, 'Salário')->id]);

    Livewire::actingAs($eliel)->test(StatementPage::class)->set('month', '2026-09')->assertDontSeeHtml('data-testid="pending-income"');
});
