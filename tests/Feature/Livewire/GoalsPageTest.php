<?php

use App\Livewire\Goals\GoalsPage;
use App\Livewire\Home\HomePage;
use App\Models\Goal;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

it('meta de 1.000: 799,99 não avisa, 800 avisa 80% e 1.000 avisa 100%', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    Livewire::test(GoalsPage::class)->set('generalLimit', '1.000,00')->call('saveGeneral');

    makeEntry($eliel, ['amount_cents' => 79999]);
    Livewire::test(HomePage::class)->set('month', '2026-09')->assertDontSeeHtml('data-testid="goal-banner"');

    makeEntry($eliel, ['amount_cents' => 1]);
    Livewire::test(HomePage::class)->set('month', '2026-09')->assertSeeHtml('data-testid="goal-banner"')->assertSee('80%');

    makeEntry($eliel, ['amount_cents' => 20000]);
    Livewire::test(HomePage::class)->set('month', '2026-09')->assertSee('100%');
});

it('mostra o progresso: quanto gastou, quanto falta e quanto dá por dia', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-09-10 10:00'));
    makeEntry($eliel, ['amount_cents' => 60000]);

    Livewire::actingAs($eliel)->test(GoalsPage::class)->set('month', '2026-09')
        ->set('generalLimit', '3.000,00')->call('saveGeneral')
        ->assertSeeHtml('data-testid="spent">R$ 600,00<')
        ->assertSee('(20%)')
        ->assertSeeHtml('data-testid="remaining"')->assertSee('Faltam R$ 2.400,00')
        ->assertSeeHtml('data-testid="per-day">R$ 114,28<'); // 2.400 / 21 dias restantes (10 a 30 de setembro)
});

it('passou da meta: mostra quanto passou', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['amount_cents' => 120000]);

    Livewire::actingAs($eliel)->test(GoalsPage::class)->set('month', '2026-09')->set('generalLimit', '1.000,00')->call('saveGeneral')
        ->assertSee('Passou R$ 200,00 da meta');
});

it('sugere a média dos últimos meses e um clique define a meta', function () {
    [$eliel] = makeCouple();
    foreach (['2026-06' => 40000, '2026-07' => 60000, '2026-08' => 50000] as $mes => $cents) {
        makeEntry($eliel, ['month' => $mes.'-01', 'amount_cents' => $cents]);
    }

    $tela = Livewire::actingAs($eliel)->test(GoalsPage::class)->set('month', '2026-09')
        ->assertSeeHtml('data-testid="suggestion"')->assertSee('Usar R$ 500,00');

    $tela->call('useSuggestion', 50000)->assertDontSeeHtml('data-testid="suggestion"');

    expect(Goal::where('kind', 'gasto')->whereNull('category_id')->first()->limit_cents)->toBe(50000);
});

it('sem histórico não sugere nada', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(GoalsPage::class)->set('month', '2026-09')->assertDontSeeHtml('data-testid="suggestion"');
});

it('limites por categoria: só aparecem as categorias com gasto ou limite, e dá para adicionar e remover', function () {
    [$eliel] = makeCouple();
    $mercado = categoryOf($eliel->couple_id, 'Mercado');
    makeEntry($eliel, ['category_id' => $mercado->id, 'amount_cents' => 30000]);

    $tela = Livewire::actingAs($eliel)->test(GoalsPage::class)->set('month', '2026-09')
        ->assertSeeHtml('data-testid="cat-'.$mercado->id.'"')->assertSee('sem limite')
        ->assertDontSeeHtml('data-testid="cat-'.categoryOf($eliel->couple_id, 'Lazer')->id.'"');

    $tela->set('addCategoryId', $mercado->id)->set('addCategoryLimit', 'R$ 500,00')->call('addCategoryGoal')->assertHasNoErrors()
        ->assertSee('de R$ 500,00');

    $goal = Goal::where('category_id', $mercado->id)->first();
    expect($goal->limit_cents)->toBe(50000)->and($goal->kind)->toBe('gasto');

    $tela->call('removeGoal', $goal->id)->assertSee('sem limite');
    expect(Goal::where('category_id', $mercado->id)->exists())->toBeFalse();
});

it('o seletor de limites só oferece categorias de gasto', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(GoalsPage::class)->assertSee('Lazer')->assertDontSee('Salário')->assertDontSee('Renda fixa')->assertDontSee('Rendimentos');
});

it('valor inválido mostra erro e não grava', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(GoalsPage::class)->set('generalLimit', '0')->call('saveGeneral')->assertSeeHtml('data-testid="form-error"');
    Livewire::actingAs($eliel)->test(GoalsPage::class)->set('generalLimit', 'abc')->call('saveGeneral')->assertSee('Informe um valor válido');

    expect(Goal::count())->toBe(0);
});

it('meta de investimento: investido no mês, total já investido e progresso', function () {
    [$eliel] = makeCouple();
    $renda = categoryOf($eliel->couple_id, 'Renda fixa')->id;
    makeEntry($eliel, ['kind' => 'aporte', 'month' => '2026-08-01', 'amount_cents' => 50000, 'category_id' => $renda]);
    makeEntry($eliel, ['kind' => 'aporte', 'month' => '2026-09-01', 'amount_cents' => 30000, 'category_id' => $renda]);

    Livewire::actingAs($eliel)->test(GoalsPage::class)->set('month', '2026-09')
        ->set('investLimit', '1.000,00')->call('saveInvest')
        ->assertSeeHtml('data-testid="invested">R$ 300,00<')->assertSeeHtml('data-testid="accumulated">R$ 800,00<')
        ->assertSee('30% da meta de investir R$ 1.000,00')->assertSee('faltam R$ 700,00');

    expect(Goal::where('kind', 'investimento')->first()->limit_cents)->toBe(100000)
        ->and(Goal::where('kind', 'gasto')->count())->toBe(0);
});

it('as metas de gasto e de investimento convivem sem se misturar', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);

    Livewire::test(GoalsPage::class)->set('generalLimit', '5.000,00')->call('saveGeneral')->set('investLimit', '800,00')->call('saveInvest');

    expect(Goal::where('kind', 'gasto')->first()->limit_cents)->toBe(500000)->and(Goal::where('kind', 'investimento')->first()->limit_cents)->toBe(80000);
});

it('a tela de metas não tem mais a lista de vencimentos (ela fica no Início)', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(GoalsPage::class)->assertDontSee('Vence nos próximos')->assertSee('Contas a vencer');
});
