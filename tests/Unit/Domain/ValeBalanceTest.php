<?php

use App\Domain\ValeBalance;

it('vale 2.250 com gastos de 250 sobra 2.000', function () {
    expect(ValeBalance::remaining(225000, [25000]))->toBe(200000);
});

it('sem gastos o saldo é o valor do vale', function () {
    expect(ValeBalance::remaining(225000, []))->toBe(225000);
});

it('a sobra de janeiro não soma em fevereiro', function () {
    $janeiro = ValeBalance::remaining(225000, [25000]);
    $fevereiro = ValeBalance::remaining(225000, []);

    expect($janeiro)->toBe(200000)->and($fevereiro)->toBe(225000);
});
