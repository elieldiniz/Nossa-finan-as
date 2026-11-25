<?php

use App\Domain\CardInvoice;
use App\Domain\RecurrenceSchedule;

it('soma 2 fixos de 300 em 600', function () {
    expect(CardInvoice::total([30000, 30000]))->toBe(60000);
});

it('só gera saída quando o total é maior que zero', function () {
    expect(CardInvoice::asExpense(60000))->toBe(60000)
        ->and(CardInvoice::asExpense(0))->toBeNull();
});

it('prevê a fatura futura pelos fixos e parcelas do mês', function () {
    $recorrentes = [
        ['schedule' => new RecurrenceSchedule('2026-09-05'), 'amount' => 30000],
        ['schedule' => new RecurrenceSchedule('2026-09-05'), 'amount' => 30000],
        ['schedule' => new RecurrenceSchedule('2026-09-05', RecurrenceSchedule::INSTALLMENTS, installmentsTotal: 2), 'amount' => 10000],
    ];

    expect(CardInvoice::forecast($recorrentes, 2026, 10))->toBe(70000)
        ->and(CardInvoice::forecast($recorrentes, 2026, 11))->toBe(60000);
});
