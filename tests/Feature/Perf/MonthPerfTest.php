<?php

use App\Livewire\Home\HomePage;
use App\Models\Entry;
use App\Models\User;
use App\Services\MonthSummary;
use App\Services\OpenMonthService;
use Carbon\CarbonImmutable;
use Database\Seeders\Perf200Seeder;
use Livewire\Livewire;

function mesCom200(): User
{
    [$eliel] = makeCouple();
    (new Perf200Seeder)->run($eliel);

    return $eliel;
}

it('recalcula o fechamento de 200 lançamentos em até 1 segundo', function () {
    $eliel = mesCom200();
    test()->actingAs($eliel);

    $inicio = hrtime(true);
    $resumo = app(MonthSummary::class)->build($eliel, CarbonImmutable::parse('2026-09-01'));

    expect((hrtime(true) - $inicio) / 1e9)->toBeLessThan(1.0)->and($resumo['entries'])->toHaveCount(200);
});

it('abre a tela do mês com 200 lançamentos em até 2 segundos', function () {
    $eliel = mesCom200();

    $inicio = hrtime(true);
    Livewire::actingAs($eliel)->test(HomePage::class)->set('month', '2026-09')->assertSee('R$');

    expect((hrtime(true) - $inicio) / 1e9)->toBeLessThan(2.0);
});

it('abre o mês seguinte herdando 200 itens em até 2 segundos', function () {
    [$eliel] = makeCouple();
    test()->actingAs($eliel);
    foreach (range(1, 200) as $i) {
        fixo($eliel, valor: 100 + $i);
    }

    Entry::where('month', '>=', '2026-02-01')->forceDelete();

    $inicio = hrtime(true);
    app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse('2026-02-01'));

    expect((hrtime(true) - $inicio) / 1e9)->toBeLessThan(2.0);
});
