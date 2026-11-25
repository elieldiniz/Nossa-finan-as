<?php

use App\Models\PersonDebtInstallment;
use App\Services\PersonDebtService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

it('300 em 3x de 100 com 2 pagas dá 100,00', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $pessoa = makePerson($eliel);
    $service = app(PersonDebtService::class);

    $divida = $service->create($eliel, $pessoa, 'receber', 30000, CarbonImmutable::parse('2026-07-01'), installments: 3);
    $service->payInstallment($eliel, $divida->installments[0], CarbonImmutable::parse('2026-07-01'));
    $service->payInstallment($eliel, $divida->installments[1], CarbonImmutable::parse('2026-08-01'));

    expect($divida->installments)->toHaveCount(3)
        ->and($service->balance($pessoa, CarbonImmutable::parse('2026-09-01')))->toBe(10000);
});

it('a 3ª parcela paga no mês M zera em M+1', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $pessoa = makePerson($eliel);
    $service = app(PersonDebtService::class);

    $divida = $service->create($eliel, $pessoa, 'receber', 30000, CarbonImmutable::parse('2026-07-01'), installments: 3);
    foreach ($divida->installments as $parcela) {
        $service->payInstallment($eliel, $parcela, CarbonImmutable::parse('2026-09-01'));
    }

    expect($service->balance($pessoa, CarbonImmutable::parse('2026-10-01')))->toBe(0);
});

it('sem parcelas permanece até quitar', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $pessoa = makePerson($eliel);
    $service = app(PersonDebtService::class);

    $divida = $service->create($eliel, $pessoa, 'pagar', 5000, CarbonImmutable::parse('2026-01-01'));

    expect($service->balance($pessoa, CarbonImmutable::parse('2026-12-01')))->toBe(-5000);

    $service->settle($eliel, $divida, CarbonImmutable::parse('2026-12-01'));

    expect($service->balance($pessoa, CarbonImmutable::parse('2027-01-01')))->toBe(0);
});

it('o par não marca parcela do outro como paga', function () {
    [$eliel, $ana] = makeCouple();
    $this->actingAs($ana);
    $pessoa = makePerson($eliel);
    $divida = app(PersonDebtService::class)->create($eliel, $pessoa, 'receber', 30000, CarbonImmutable::parse('2026-07-01'), installments: 3);

    expect(fn () => app(PersonDebtService::class)->payInstallment($ana, PersonDebtInstallment::first(), CarbonImmutable::parse('2026-07-01')))
        ->toThrow(AuthorizationException::class);
});
