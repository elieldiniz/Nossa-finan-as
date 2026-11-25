<?php

use App\Livewire\Reports\ReportPage;
use Livewire\Livewire;

it('categoria com 300 no mês e 200 no anterior mostra +50,00%', function () {
    [$eliel] = makeCouple();
    $cat = categoryOf($eliel->couple_id, 'Mercado')->id;
    makeEntry($eliel, ['month' => '2026-08-01', 'amount_cents' => 20000, 'category_id' => $cat]);
    makeEntry($eliel, ['month' => '2026-09-01', 'amount_cents' => 30000, 'category_id' => $cat]);

    Livewire::actingAs($eliel)->test(ReportPage::class)->set('month', '2026-09')
        ->assertSeeHtml('data-testid="report"')->assertSee('Mercado')->assertSee('+50,00%');
});

it('mês anterior vazio não quebra', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['amount_cents' => 30000]);

    Livewire::actingAs($eliel)->test(ReportPage::class)->set('month', '2026-09')->assertOk()->assertSee('novo');
});
