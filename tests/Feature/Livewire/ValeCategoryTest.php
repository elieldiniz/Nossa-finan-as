<?php

use App\Livewire\Charts\ChartsPage;
use App\Livewire\Home\HomePage;
use App\Livewire\Statement\StatementPage;
use App\Livewire\Vale\ValePage;
use App\Models\Category;
use App\Models\ValeSpend;
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
