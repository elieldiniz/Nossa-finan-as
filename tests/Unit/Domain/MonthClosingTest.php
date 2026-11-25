<?php

use App\Domain\MonthClosing;

it('fecha o caso da planilha em −R$ 485,00', function () {
    // Madrinha: receber 415 − pagar 300 = +115; Bradesco: 2 fixos de 300.
    $result = MonthClosing::calculate([], cardTotals: [60000, 0], personNets: [11500, 0, 0]);

    expect($result->income)->toBe(11500)
        ->and($result->expense)->toBe(60000)
        ->and($result->closing)->toBe(-48500)
        ->and($result->sign())->toBe('negativo')
        ->and($result->label())->toBe('Saiu mais do que entrou');
});

it('conta pessoa com saldo negativo como saída', function () {
    $result = MonthClosing::calculate([], personNets: [-3000]);

    expect($result->expense)->toBe(3000)->and($result->closing)->toBe(-3000);
});

it('soma entradas e saídas avulsas', function () {
    $result = MonthClosing::calculate([
        ['type' => 'entrada', 'amount' => 500000],
        ['type' => 'saida', 'amount' => 120000],
        ['type' => 'saida', 'amount' => 30000],
    ]);

    expect($result->closing)->toBe(350000)->and($result->sign())->toBe('positivo');
});

it('cartão zerado não vira saída', function () {
    expect(MonthClosing::calculate([], cardTotals: [0])->expense)->toBe(0);
});

it('calcula 200 linhas em menos de 50 ms', function () {
    $entries = array_map(fn ($i) => ['type' => $i % 2 ? 'entrada' : 'saida', 'amount' => 100 + $i], range(1, 200));

    $start = hrtime(true);
    MonthClosing::calculate($entries);

    expect((hrtime(true) - $start) / 1e6)->toBeLessThan(50);
});
