<?php

use App\Livewire\Categories\CategoriesPage;
use App\Livewire\Charts\ChartsPage;
use App\Livewire\Goals\GoalsPage;
use App\Livewire\Home\HomePage;
use App\Livewire\Reports\ReportPage;
use App\Livewire\Statement\StatementPage;
use App\Livewire\Vale\ValePage;
use App\Models\Category;
use App\Models\User;
use App\Models\Vale;
use App\Models\ValeSpend;
use App\Services\CategoryService;
use Livewire\Livewire;

it('a tela se chama Vale alimentação (menu, título, cartão do Início e linha do Extrato)', function () {
    [$eliel] = makeCouple();
    makeVale($eliel, 100000);

    $this->actingAs($eliel)->get(route('vale'))->assertOk()->assertSee('Vale alimentação');
    $this->actingAs($eliel)->get(route('more'))->assertSee('Vale alimentação');
    Livewire::actingAs($eliel)->test(HomePage::class)->assertSee('Vale alimentação');
    Livewire::actingAs($eliel)->test(StatementPage::class)->assertSee('Vale alimentação');
});

it('o gasto do vale tem categoria e data: aparecem na lista e a categoria é sugerida pelo nome', function () {
    [$eliel] = makeCouple();
    makeVale($eliel, 100000);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');

    $tela = Livewire::actingAs($eliel)->test(ValePage::class)->set('month', '2026-09')->assertSeeHtml('data-testid="spend-category"')->assertSeeHtml('data-testid="spend-date"')
        ->set('spendName', 'Supermercado Tai Max')->assertSet('spendCategoryId', $mercado->id);

    $tela->set('spendAmount', '45,00')->set('spentOn', '2026-09-12')->call('addSpend')->assertHasNoErrors()->assertSeeHtml('data-testid="spend-meta">12/09 · Mercado<');

    $gasto = ValeSpend::first();
    expect($gasto->category_id)->toBe($mercado->id)->and($gasto->spent_on->toDateString())->toBe('2026-09-12')->and($gasto->amount_cents)->toBe(4500);
    $tela->assertSet('spendName', '')->assertSet('spendCategoryId', null);
});

it('se a pessoa escolhe a categoria, a sugestão pelo nome não troca mais; e dá para ficar sem categoria', function () {
    [$eliel] = makeCouple();
    makeVale($eliel, 100000);
    $lazer = categoryOf($eliel->couple_id, 'Lazer');

    $tela = Livewire::actingAs($eliel)->test(ValePage::class)->set('month', '2026-09')->set('spendCategoryId', $lazer->id)->set('spendName', 'Supermercado')->assertSet('spendCategoryId', $lazer->id);

    $tela->set('spendAmount', '10,00')->call('addSpend');
    expect(ValeSpend::first()->category_id)->toBe($lazer->id);

    Livewire::actingAs($eliel)->test(ValePage::class)->set('month', '2026-09')->set('spendName', 'Coisa')->set('spendAmount', '5,00')->call('addSpend');
    expect(ValeSpend::orderByDesc('id')->first()->category_id)->toBeNull();
});

it('data fora do mês aberto é recusada com aviso', function () {
    [$eliel] = makeCouple();
    makeVale($eliel, 100000);

    Livewire::actingAs($eliel)->test(ValePage::class)->set('month', '2026-09')->set('spendName', 'X')->set('spendAmount', '5,00')->set('spentOn', '2026-10-02')->call('addSpend')->assertSee('A data precisa ser de setembro');

    expect(ValeSpend::count())->toBe(0);
});

it('categoria de outro casal é ignorada', function () {
    [$eliel] = makeCouple();
    [$outro] = makeCouple('Outro', 'Casal');
    makeVale($eliel, 100000);
    $alheia = categoryOf($outro->couple_id, 'Lazer');

    Livewire::actingAs($eliel)->test(ValePage::class)->set('month', '2026-09')->set('spendName', 'X')->set('spendAmount', '5,00')->set('spendCategoryId', $alheia->id)->call('addSpend');

    expect(ValeSpend::first()->category_id)->toBeNull();
});

it('os gastos do vale entram nos gastos por categoria do Início e dos gráficos, na categoria escolhida', function () {
    [$eliel] = makeCouple();
    $vale = makeVale($eliel, 100000);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    foreach ([['Feira', 4000, $mercado->id], ['Sem categoria', 1000, null]] as [$nome, $cents, $cat]) {
        $s = new ValeSpend(['vale_id' => $vale->id, 'owner_id' => $eliel->id, 'name' => $nome, 'amount_cents' => $cents, 'month' => '2026-09-01', 'category_id' => $cat]);
        $s->couple_id = $eliel->couple_id;
        $s->save();
    }

    $home = Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-09');
    expect($home->viewData('byCategory')->all())->toBe(['Mercado' => 4000, 'Vale alimentação' => 1000]);

    $series = Livewire::actingAs($eliel)->test(ChartsPage::class)->set('month', '2026-09')->instance()->series();
    expect($series['donut'])->toHaveKey('Mercado')->toHaveKey('Vale alimentação');
});

it('a categoria padrão do casal nasce como "Vale alimentação"', function () {
    [$eliel] = makeCouple();

    expect(Category::withoutGlobalScopes()->where('couple_id', $eliel->couple_id)->where('name', 'Vale alimentação')->exists())->toBeTrue()
        ->and(Category::withoutGlobalScopes()->where('couple_id', $eliel->couple_id)->where('name', 'Vale')->exists())->toBeFalse();
});

it('a tela do vale tem os botões de exportar PDF e OFX, que seguem o período escolhido', function () {
    [$eliel] = makeCouple();
    makeVale($eliel, 100000);

    $tela = Livewire::actingAs($eliel)->test(ValePage::class)->set('month', '2026-09')->assertSeeHtml('data-testid="export-pdf"')->assertSeeHtml('data-testid="export-ofx"')
        ->assertSeeHtml('vale/exportar/pdf?m=2026-09');

    $tela->set('exportScope', 'tudo')->assertSeeHtml('vale/exportar/pdf?m=tudo')->assertSeeHtml('vale/exportar/ofx?m=tudo');
});

function gastoVale(User $u, Vale $vale, string $mes, string $nome, int $cents, ?int $categoria): ValeSpend
{
    $s = new ValeSpend(['vale_id' => $vale->id, 'owner_id' => $u->id, 'name' => $nome, 'amount_cents' => $cents, 'month' => $mes.'-01', 'category_id' => $categoria]);
    $s->couple_id = $u->couple_id;
    $s->save();

    return $s;
}

it('a categoria do vale é a mesma lista das compras: remover categoria em uso por gasto do vale pede outra e leva os gastos junto', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $vale = makeVale($eliel, 100000);
    $lazer = categoryOf($eliel->couple_id, 'Lazer');
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    $gasto = gastoVale($eliel, $vale, '2026-09', 'Pizza', 6000, $lazer->id);
    $service = app(CategoryService::class);

    expect(fn () => $service->remove($eliel, $lazer))->toThrow(InvalidArgumentException::class, 'gastos do vale alimentação');

    $service->remove($eliel, $lazer, $mercado);

    expect($gasto->fresh()->category_id)->toBe($mercado->id)->and(Category::where('name', 'Lazer')->exists())->toBeFalse();
});

it('a tela de categorias mostra quantos lançamentos e gastos do vale cada categoria tem', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $vale = makeVale($eliel, 100000);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    makeEntry($eliel, ['category_id' => $mercado->id]);
    makeEntry($eliel, ['category_id' => $mercado->id]);
    gastoVale($eliel, $vale, '2026-09', 'Feira', 1000, $mercado->id);

    Livewire::actingAs($eliel)->test(CategoriesPage::class)
        ->assertSeeHtml('data-testid="usage-'.$mercado->id.'"')->assertSee('2 lançamentos')->assertSee('1 gasto do vale alimentação');
});

it('o limite por categoria (Metas) conta o gasto do vale na categoria escolhida', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $vale = makeVale($eliel, 100000);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    makeEntry($eliel, ['category_id' => $mercado->id, 'amount_cents' => 30000]);
    gastoVale($eliel, $vale, '2026-09', 'Feira', 20000, $mercado->id);

    Livewire::actingAs($eliel)->test(GoalsPage::class)->set('month', '2026-09')->set('addCategoryId', $mercado->id)->set('addCategoryLimit', 'R$ 400,00')->call('addCategoryGoal')
        ->assertSee('R$ 500,00')->assertSee('de R$ 400,00');
});

it('o relatório compara por categoria incluindo o gasto do vale', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $vale = makeVale($eliel, 100000);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    makeEntry($eliel, ['category_id' => $mercado->id, 'amount_cents' => 30000]);
    gastoVale($eliel, $vale, '2026-09', 'Feira', 20000, $mercado->id);
    gastoVale($eliel, $vale, '2026-09', 'Sem cat', 5000, null);

    $tela = Livewire::actingAs($eliel)->test(ReportPage::class)->set('month', '2026-09');

    expect(collect($tela->viewData('rows'))->pluck('current', 'name')->all())->toBe(['Mercado' => 50000, 'Vale alimentação' => 5000]);
});

it('editar um gasto do vale: muda nome, valor, categoria e data (liga gastos antigos a uma categoria)', function () {
    [$eliel] = makeCouple();
    $vale = makeVale($eliel, 100000);
    $antigo = gastoVale($eliel, $vale, '2026-09', 'Compra antiga', 1000, null);
    $mercado = categoryOf($eliel->couple_id, 'Mercado');

    $tela = Livewire::actingAs($eliel)->test(ValePage::class)->set('month', '2026-09')->call('editSpend', $antigo->id)
        ->assertSet('spendName', 'Compra antiga')->assertSeeHtml('data-testid="spend-form-title">Editar gasto')->assertSee('Salvar alterações');

    $tela->set('spendName', 'Supermercado')->set('spendAmount', '25,50')->set('spendCategoryId', $mercado->id)->set('spentOn', '2026-09-10')->call('addSpend')->assertHasNoErrors()
        ->assertSet('editingSpendId', null)->assertSet('spendName', '')->assertSeeHtml('data-testid="spend-meta">10/09 · Mercado<');

    $g = $antigo->fresh();
    expect($g->name)->toBe('Supermercado')->and($g->amount_cents)->toBe(2550)->and($g->category_id)->toBe($mercado->id)->and(ValeSpend::count())->toBe(1);
});

it('cancelar a edição limpa o formulário; só o dono edita o gasto', function () {
    [$eliel, $ana] = makeCouple();
    $vale = makeVale($eliel, 100000);
    $gasto = gastoVale($eliel, $vale, '2026-09', 'Do Eliel', 1000, null);

    Livewire::actingAs($eliel)->test(ValePage::class)->set('month', '2026-09')->call('editSpend', $gasto->id)->call('cancelEdit')->assertSet('editingSpendId', null)->assertSet('spendName', '');
    Livewire::actingAs($ana)->test(ValePage::class)->set('month', '2026-09')->call('editSpend', $gasto->id)->assertForbidden();
});
