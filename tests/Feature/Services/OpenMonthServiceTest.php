<?php

use App\Models\Entry;
use App\Services\EntryService;
use App\Services\OpenMonthService;
use Carbon\CarbonImmutable;

it('fixo de janeiro já nasce em todos os 12 meses seguintes, sem ninguém abrir o mês', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15 10:00'));
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel);

    expect(Entry::where('month', '2026-02-01')->count())->toBe(1)
        ->and(Entry::where('month', '2026-02-01')->first()->paid)->toBeFalse()
        ->and(Entry::where('month', '2026-12-01')->count())->toBe(1)
        ->and(Entry::count())->toBe(12);

    // abrir o mês depois não duplica nada
    expect(app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse('2026-02-01')))->toBe(0)
        ->and(Entry::count())->toBe(12);
});

it('parcelado já cria todas as parcelas de uma vez e marcar uma como paga não mexe nas outras', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel, ['frequency' => 'installments', 'installments_total' => 5], 15000, '2026-11-01');

    $parcelas = Entry::orderBy('month')->get();
    expect($parcelas->pluck('installment_label')->all())->toBe(['1/5', '2/5', '3/5', '4/5', '5/5'])
        ->and($parcelas->first()->month->format('Y-m'))->toBe('2026-11')->and($parcelas->last()->month->format('Y-m'))->toBe('2027-03');

    app(EntryService::class)->setPaid($eliel, $parcelas[1], true);
    expect(Entry::where('paid', false)->count())->toBe(4)->and(Entry::where('paid', true)->first()->installment_label)->toBe('2/5');
});

it('conta sem fim continua sendo completada a cada acesso (janela de 12 meses)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-01-15 10:00'));
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    fixo($eliel);
    expect(app(OpenMonthService::class)->openRange($eliel->couple_id, CarbonImmutable::parse('2026-06-01'), 12))->toBe(5); // jan–dez/26 já existiam: faltam só jan–mai/27
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

    Entry::where('month', '>=', '2026-02-01')->forceDelete(); // só janeiro: o mês seguinte será gerado agora

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
