<?php

use App\Domain\ReportCalculator;

it('300 contra 200 dá +50,00%', function () {
    $v = ReportCalculator::variation(30000, 20000);

    expect($v['delta'])->toBe(10000)->and($v['percent'])->toBe('+50,00%');
});

it('mês anterior zero não divide por zero', function () {
    expect(ReportCalculator::variation(30000, 0))->toBe(['delta' => 30000, 'percent' => null]);
});

it('queda mostra sinal de menos', function () {
    expect(ReportCalculator::variation(15000, 20000)['percent'])->toBe("\u{2212}25,00%");
});

it('monta as 4 séries e troca de período recalcula tudo', function () {
    $dados = [
        '2026-04' => ['Moradia' => 30000],
        '2026-08' => ['Moradia' => 30000, 'Mercado' => 12000],
        '2026-09' => ['Moradia' => 30000, 'Mercado' => 15000, 'Transporte' => 6000],
    ];

    $seis = ReportCalculator::build($dados, '2026-09', 6);
    $tres = ReportCalculator::build($dados, '2026-09', 3);

    expect($seis['line'])->toHaveCount(6)->and($tres['line'])->toHaveCount(3)
        ->and($seis['line']['2026-09'])->toBe(51000)
        ->and($seis['bars']['Mercado'])->toBe(['previous' => 12000, 'current' => 15000])
        ->and($seis['bars']['Transporte'])->toBe(['previous' => 0, 'current' => 6000])
        ->and(array_key_first($seis['ranking']))->toBe('Moradia')
        ->and($seis['donut']['Moradia']['percent'])->toBe('58,82%');
});
