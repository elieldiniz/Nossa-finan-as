<?php

use App\Domain\RecurrenceSchedule;

it('repete fixo todo mês a partir do início', function () {
    $fixo = new RecurrenceSchedule('2026-01-10');

    expect($fixo->occursIn(2025, 12))->toBeFalse()
        ->and($fixo->occursIn(2026, 1))->toBeTrue()
        ->and($fixo->occursIn(2026, 2))->toBeTrue()
        ->and($fixo->occursIn(2040, 6))->toBeTrue();
});

it('parcelado 3x iniciado em janeiro ocorre em jan, fev e mar e não em abril', function () {
    $parcelado = new RecurrenceSchedule('2026-01-05', RecurrenceSchedule::INSTALLMENTS, installmentsTotal: 3);

    expect($parcelado->occursIn(2026, 1))->toBeTrue()
        ->and($parcelado->occursIn(2026, 3))->toBeTrue()
        ->and($parcelado->occursIn(2026, 4))->toBeFalse()
        ->and($parcelado->installmentLabel(2026, 1))->toBe('1/3')
        ->and($parcelado->installmentLabel(2026, 2))->toBe('2/3')
        ->and($parcelado->installmentLabel(2026, 3))->toBe('3/3')
        ->and($parcelado->installmentLabel(2026, 4))->toBeNull();
});

it('termina na data final e não gera no mês seguinte', function () {
    $assinatura = new RecurrenceSchedule('2026-11-25', endsOn: '2027-12-23');

    expect($assinatura->occursIn(2027, 12))->toBeTrue()
        ->and($assinatura->occursIn(2028, 1))->toBeFalse()
        ->and($assinatura->monthsBetween(2026, 11, 2028, 6))->toHaveCount(14);
});

it('sem data final nunca acaba', function () {
    expect((new RecurrenceSchedule('2026-01-01'))->occursIn(2040, 12))->toBeTrue();
});

it('anual só ocorre no mês de cobrança', function () {
    $seguro = new RecurrenceSchedule('2026-03-15', RecurrenceSchedule::YEARLY);

    expect($seguro->occursIn(2026, 3))->toBeTrue()
        ->and($seguro->occursIn(2026, 4))->toBeFalse()
        ->and($seguro->occursIn(2027, 3))->toBeTrue()
        ->and($seguro->occursIn(2027, 9))->toBeFalse();
});

it('dia 31 em fevereiro vira o último dia do mês', function () {
    $fixo = new RecurrenceSchedule('2026-01-31', dayOfMonth: 31);

    expect($fixo->dateIn(2026, 2)->toDateString())->toBe('2026-02-28')
        ->and($fixo->dateIn(2028, 2)->toDateString())->toBe('2028-02-29')
        ->and($fixo->dateIn(2026, 3)->toDateString())->toBe('2026-03-31');
});

it('parcelado sem número de parcelas é recusado', function () {
    new RecurrenceSchedule('2026-01-01', RecurrenceSchedule::INSTALLMENTS);
})->throws(InvalidArgumentException::class);
