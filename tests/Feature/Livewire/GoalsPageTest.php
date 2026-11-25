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

it('limite por categoria é salvo e removido', function () {
    [$eliel] = makeCouple();
    $categoria = categoryOf($eliel->couple_id, 'Mercado');

    $tela = Livewire::actingAs($eliel)->test(GoalsPage::class)->set("categoryLimits.{$categoria->id}", '500,00')->call('saveCategory', $categoria->id);

    expect(Goal::where('category_id', $categoria->id)->first()->limit_cents)->toBe(50000);
});

it('limite inválido mostra erro', function () {
    [$eliel] = makeCouple();

    Livewire::actingAs($eliel)->test(GoalsPage::class)->set('generalLimit', '0')->call('saveGeneral')->assertSeeHtml('data-testid="form-error"');

    expect(Goal::count())->toBe(0);
});

it('vencimento em 7 dias aparece; em 8 dias ou pago não', function () {
    [$eliel] = makeCouple();
    $this->travelTo(CarbonImmutable::parse('2026-09-10 10:00'));
    makeEntry($eliel, ['name' => 'Em 7', 'due_on' => '2026-09-17']);
    makeEntry($eliel, ['name' => 'Em 8', 'due_on' => '2026-09-18']);
    makeEntry($eliel, ['name' => 'Pago', 'due_on' => '2026-09-12', 'paid' => true]);

    Livewire::actingAs($eliel)->test(GoalsPage::class)->set('month', '2026-09')->assertSee('Em 7')->assertDontSee('Em 8')->assertDontSee('Pago');
});
