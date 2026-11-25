<?php

use App\Models\Entry;
use App\Services\OpenMonthService;
use Carbon\CarbonImmutable;

it('fixo de janeiro aparece em fevereiro sem ação manual', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel);

    $criadas = app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse('2026-02-01'));

    expect($criadas)->toBe(1)
        ->and(Entry::where('month', '2026-02-01')->count())->toBe(1)
        ->and(Entry::where('month', '2026-02-01')->first()->paid)->toBeFalse();
});

it('parcelado 3x aparece em jan, fev e mar e não em abril', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel, ['frequency' => 'installments', 'installments_total' => 3]);
    $service = app(OpenMonthService::class);

    foreach (['2026-02-01', '2026-03-01', '2026-04-01'] as $mes) {
        $service->open($eliel->couple_id, CarbonImmutable::parse($mes));
    }

    expect(Entry::where('month', '2026-01-01')->first()->installment_label)->toBe('1/3')
        ->and(Entry::where('month', '2026-02-01')->first()->installment_label)->toBe('2/3')
        ->and(Entry::where('month', '2026-03-01')->first()->installment_label)->toBe('3/3')
        ->and(Entry::where('month', '2026-04-01')->count())->toBe(0);
});

it('abrir o mês duas vezes não duplica', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel);
    $service = app(OpenMonthService::class);
    $mes = CarbonImmutable::parse('2026-02-01');

    $service->open($eliel->couple_id, $mes);
    $segunda = $service->open($eliel->couple_id, $mes);

    expect($segunda)->toBe(0)->and(Entry::where('month', '2026-02-01')->count())->toBe(1);
});

it('herda 200 itens em até 2 segundos', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    foreach (range(1, 200) as $i) {
        fixo($eliel, valor: 100 + $i);
    }

    $inicio = hrtime(true);
    $criadas = app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse('2026-02-01'));

    expect($criadas)->toBe(200)->and((hrtime(true) - $inicio) / 1e9)->toBeLessThan(2.0);
});

it('recorrência com data final para de gerar depois dela', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel, ['frequency' => 'monthly', 'ends_on' => '2026-03-23']);
    $service = app(OpenMonthService::class);

    $service->open($eliel->couple_id, CarbonImmutable::parse('2026-03-01'));
    $service->open($eliel->couple_id, CarbonImmutable::parse('2026-04-01'));

    expect(Entry::where('month', '2026-03-01')->count())->toBe(1)
        ->and(Entry::where('month', '2026-04-01')->count())->toBe(0);
});
