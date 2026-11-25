<?php

use App\Livewire\Charts\ChartsPage;
use App\Models\User;
use Livewire\Livewire;

function gastosEmMeses(User $u): void
{
    foreach (['2026-07' => 40000, '2026-08' => 54000, '2026-09' => 60000] as $mes => $cents) {
        makeEntry($u, ['month' => $mes.'-01', 'amount_cents' => $cents, 'category_id' => categoryOf($u->couple_id, 'Mercado')->id]);
    }
}

it('entrega as 4 séries e a variação contra o mês anterior', function () {
    [$eliel] = makeCouple();
    gastosEmMeses($eliel);

    $tela = Livewire::actingAs($eliel)->test(ChartsPage::class)->set('month', '2026-09');
    $series = $tela->instance()->series();

    expect(array_keys($series))->toBe(['line', 'bars', 'donut', 'ranking'])
        ->and($series['line']['2026-09'])->toBe(60000);

    $tela->assertSeeHtml('data-testid="variation"')->assertSee('11,11%');
});

it('trocar o período recalcula as séries', function () {
    [$eliel] = makeCouple();
    gastosEmMeses($eliel);

    $tela = Livewire::actingAs($eliel)->test(ChartsPage::class)->set('month', '2026-09');

    expect(count($tela->instance()->series()['line']))->toBe(6);
    $tela->call('setPeriod', 3);
    expect(count($tela->instance()->series()['line']))->toBe(3);
    $tela->call('setPeriod', 12);
    expect(count($tela->instance()->series()['line']))->toBe(12);
});

it('tem a tabela alternativa acessível com os números', function () {
    [$eliel] = makeCouple();
    gastosEmMeses($eliel);

    Livewire::actingAs($eliel)->test(ChartsPage::class)->set('month', '2026-09')->assertSeeHtml('data-testid="line-table"')->assertSee('R$ 600,00');
});
