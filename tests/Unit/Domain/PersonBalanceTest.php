<?php

use App\Domain\PersonBalance;

it('Madrinha: pagar 300 e receber 415 dá +115', function () {
    $debts = [
        ['direction' => 'pagar', 'amount' => 30000],
        ['direction' => 'receber', 'amount' => 41500],
    ];

    expect(PersonBalance::net($debts, '2026-09'))->toBe(11500);
});

it('pessoa sem pendências dá zero', function () {
    expect(PersonBalance::net([], '2026-09'))->toBe(0);
});

it('acumula entre meses enquanto não quitar', function () {
    $debts = [['direction' => 'receber', 'amount' => 10000]];

    expect(PersonBalance::net($debts, '2026-09'))->toBe(10000)
        ->and(PersonBalance::net($debts, '2027-03'))->toBe(10000);
});

it('3x de 100 com 2 pagas dá 100', function () {
    $debts = [[
        'direction' => 'receber',
        'amount' => 30000,
        'installments' => [
            ['amount' => 10000, 'paid_month' => '2026-07'],
            ['amount' => 10000, 'paid_month' => '2026-08'],
            ['amount' => 10000, 'paid_month' => null],
        ],
    ]];

    expect(PersonBalance::net($debts, '2026-09'))->toBe(10000);
});

it('a 3ª parcela paga no mês M zera em M+1', function () {
    $debts = [[
        'direction' => 'receber',
        'amount' => 30000,
        'installments' => [
            ['amount' => 10000, 'paid_month' => '2026-07'],
            ['amount' => 10000, 'paid_month' => '2026-08'],
            ['amount' => 10000, 'paid_month' => '2026-09'],
        ],
    ]];

    expect(PersonBalance::net($debts, '2026-10'))->toBe(0);
});

it('dívida sem parcelas quitada some a partir do mês seguinte', function () {
    $debts = [['direction' => 'pagar', 'amount' => 5000, 'settled_month' => '2026-09']];

    expect(PersonBalance::net($debts, '2026-09'))->toBe(-5000)
        ->and(PersonBalance::net($debts, '2026-10'))->toBe(0);
});
