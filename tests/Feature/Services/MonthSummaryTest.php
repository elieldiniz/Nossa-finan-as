<?php

use App\Services\MonthSummary;
use App\Support\ViewContext;

it('o caso da planilha em "Nós" fecha em −R$ 485,00', function () {
    [$eliel, , $setembro] = cenarioPlanilha();

    $resumo = app(MonthSummary::class)->build($eliel, $setembro, ViewContext::NOS);

    expect($resumo['closing']->closing)->toBe(-48500)
        ->and($resumo['closing']->income)->toBe(11500)
        ->and($resumo['closing']->expense)->toBe(60000)
        ->and($resumo['cards'][0]['total'])->toBe(60000)
        ->and($resumo['vale']['remaining'])->toBe(225000)
        ->and(collect($resumo['people'])->firstWhere('person.name', 'Madrinha')['net'])->toBe(11500);
});

it('a visão "Eliel" traz só o que é do Eliel e a "Ana" só o dela', function () {
    [$eliel, , $setembro] = cenarioPlanilha();

    $eu = app(MonthSummary::class)->build($eliel, $setembro, ViewContext::EU);
    $par = app(MonthSummary::class)->build($eliel, $setembro, ViewContext::PAR);

    // as 2 saídas de cartão são do Eliel; a pendência da Madrinha é da Ana
    expect($eu['closing']->expense)->toBe(60000)->and($eu['closing']->income)->toBe(0)
        ->and($par['closing']->income)->toBe(11500)->and($par['closing']->expense)->toBe(0)
        ->and($par['view']->isReadOnly())->toBeTrue()
        ->and($eu['view']->isReadOnly())->toBeFalse();
});

it('200 lançamentos usam no máximo 15 consultas', function () {
    [$eliel, , $setembro] = cenarioPlanilha();
    foreach (range(1, 200) as $i) {
        makeEntry($eliel, ['name' => "Item {$i}", 'amount_cents' => 100 + $i]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    app(MonthSummary::class)->build($eliel, $setembro, ViewContext::NOS);

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(15);
});
