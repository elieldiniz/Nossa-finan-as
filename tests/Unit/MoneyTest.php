<?php

use App\Support\Money;

it('formata centavos em reais', function () {
    expect(Money::format(225000))->toBe('R$ 2.250,00')
        ->and(Money::format(0))->toBe('R$ 0,00')
        ->and(Money::format(5))->toBe('R$ 0,05')
        ->and(Money::format(123456789))->toBe('R$ 1.234.567,89');
});

it('formata negativo com sinal de menos', function () {
    expect(Money::format(-48500))->toBe("\u{2212}R$ 485,00")
        ->and(Money::signed(11500))->toBe('+R$ 115,00')
        ->and(Money::signed(-48500))->toBe("\u{2212}R$ 485,00");
});

it('converte texto em centavos', function () {
    expect(Money::parse('1.234,56'))->toBe(123456)
        ->and(Money::parse('12,50'))->toBe(1250)
        ->and(Money::parse('50'))->toBe(5000)
        ->and(Money::parse('R$ 2.250,00'))->toBe(225000)
        ->and(Money::parse('0,5'))->toBe(50)
        ->and(Money::parse('-3'))->toBe(-300);
});

it('rejeita valores inválidos', function (string $bad) {
    Money::parse($bad);
})->with(['', 'abc', '1,2,3', '12,345', '1..2'])->throws(InvalidArgumentException::class);

it('soma sem erro de ponto flutuante', function () {
    // 0,10 + 0,20 em float dá 0.30000000000000004; em centavos dá exatamente 30
    expect(Money::parse('0,10') + Money::parse('0,20'))->toBe(30);
});

it('calcula porcentagem só com inteiros', function () {
    expect(Money::percent(30000, 20000))->toBe('150,00%')
        ->and(Money::percent(1, 3))->toBe('33,33%')
        ->and(Money::percent(5, 0))->toBe('0,00%')
        ->and(Money::percent(100, 100))->toBe('100,00%');
});

it('divide centavos sem perder nem criar nenhum', function () {
    expect(Money::split(10001, 2))->toBe([5001, 5000])
        ->and(array_sum(Money::split(10001, 3)))->toBe(10001);
});
