<?php

use App\Domain\Settlement;

it('200 pagos só pelo Eliel em 50/50: Ana deve 100 ao Eliel', function () {
    $result = Settlement::calculate(['Eliel' => 20000, 'Ana' => 0], ['Eliel' => 50, 'Ana' => 50]);

    expect($result->debtor)->toBe('Ana')
        ->and($result->creditor)->toBe('Eliel')
        ->and($result->amount)->toBe(10000);
});

it('divide o resto de centavo sempre do mesmo jeito', function () {
    $result = Settlement::calculate(['Eliel' => 10001, 'Ana' => 0], ['Eliel' => 50, 'Ana' => 50]);

    expect($result->shares)->toBe(['Eliel' => 5001, 'Ana' => 5000])
        ->and($result->amount)->toBe(5000);
});

it('depois de acertar a dívida é zero', function () {
    $result = Settlement::calculate(['Eliel' => 20000, 'Ana' => 0], ['Eliel' => 50, 'Ana' => 50], alreadySettled: 10000);

    expect($result->amount)->toBe(0)
        ->and($result->isSettled())->toBeTrue()
        ->and($result->debtor)->toBeNull();
});

it('respeita divisão diferente de 50/50', function () {
    $result = Settlement::calculate(['Eliel' => 10000, 'Ana' => 0], ['Eliel' => 60, 'Ana' => 40]);

    expect($result->amount)->toBe(4000);
});

it('recusa divisão que não soma 100', function () {
    Settlement::calculate(['Eliel' => 100, 'Ana' => 0], ['Eliel' => 60, 'Ana' => 60]);
})->throws(InvalidArgumentException::class);
