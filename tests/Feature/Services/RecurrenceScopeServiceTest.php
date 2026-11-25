<?php

use App\Models\Entry;
use App\Services\OpenMonthService;
use App\Services\RecurrenceScopeService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

/** Fixo de R$ 100 de jan a mar, com jan e fev já pagos. */
function cenarioFixo(): array
{
    [$eliel, $ana] = makeCouple();
    test()->actingAs($eliel);
    fixo($eliel, valor: 10000);
    foreach (['2026-02-01', '2026-03-01'] as $mes) {
        app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse($mes));
    }
    Entry::whereIn('month', ['2026-01-01', '2026-02-01'])->update(['paid' => true]);

    return [$eliel, $ana];
}

it('"todos": 100 vira 120 inclusive nos meses passados já pagos, mantendo-os pagos', function () {
    [$eliel] = cenarioFixo();
    $fevereiro = Entry::where('month', '2026-02-01')->first();

    app(RecurrenceScopeService::class)->update($eliel, $fevereiro, ['amount_cents' => 12000], 'all');

    expect(Entry::pluck('amount_cents')->unique()->all())->toBe([12000])
        ->and(Entry::where('month', '2026-01-01')->first()->paid)->toBeTrue();
});

it('"este e os próximos" preserva os meses anteriores', function () {
    [$eliel] = cenarioFixo();
    $fevereiro = Entry::where('month', '2026-02-01')->first();

    app(RecurrenceScopeService::class)->update($eliel, $fevereiro, ['amount_cents' => 12000], 'following');

    expect(Entry::where('month', '2026-01-01')->first()->amount_cents)->toBe(10000)
        ->and(Entry::where('month', '2026-02-01')->first()->amount_cents)->toBe(12000)
        ->and(Entry::where('month', '2026-03-01')->first()->amount_cents)->toBe(12000);

    // e os meses que ainda vão nascer já nascem com o valor novo
    app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse('2026-04-01'));
    expect(Entry::where('month', '2026-04-01')->first()->amount_cents)->toBe(12000);
});

it('"só este mês" altera uma ocorrência', function () {
    [$eliel] = cenarioFixo();
    $marco = Entry::where('month', '2026-03-01')->first();

    app(RecurrenceScopeService::class)->update($eliel, $marco, ['amount_cents' => 5000], 'this');

    expect(Entry::where('month', '2026-03-01')->first()->amount_cents)->toBe(5000)
        ->and(Entry::where('month', '2026-02-01')->first()->amount_cents)->toBe(10000);
});

it('excluir "só este mês" não faz a ocorrência voltar ao reabrir o mês', function () {
    [$eliel] = cenarioFixo();
    $marco = Entry::where('month', '2026-03-01')->first();

    app(RecurrenceScopeService::class)->delete($eliel, $marco, 'this');
    app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse('2026-03-01'));

    expect(Entry::where('month', '2026-03-01')->count())->toBe(0)
        ->and(Entry::where('month', '2026-02-01')->count())->toBe(1);
});

it('excluir "este e os próximos" encerra a regra no mês anterior', function () {
    [$eliel] = cenarioFixo();
    $fevereiro = Entry::where('month', '2026-02-01')->first();

    app(RecurrenceScopeService::class)->delete($eliel, $fevereiro, 'following');
    app(OpenMonthService::class)->open($eliel->couple_id, CarbonImmutable::parse('2026-05-01'));

    expect(Entry::pluck('month')->map->format('Y-m')->all())->toBe(['2026-01'])
        ->and(Entry::where('month', '2026-05-01')->count())->toBe(0);
});

it('excluir "todos" apaga o histórico inteiro', function () {
    [$eliel] = cenarioFixo();

    app(RecurrenceScopeService::class)->delete($eliel, Entry::where('month', '2026-02-01')->first(), 'all');

    expect(Entry::withTrashed()->count())->toBe(0);
});

it('o par não consegue alterar nem apagar o recorrente do outro', function () {
    [, $ana] = cenarioFixo();
    $entry = Entry::withoutGlobalScopes()->where('month', '2026-02-01')->first();
    $antes = $entry->fresh()->toArray();
    $this->actingAs($ana);

    expect(fn () => app(RecurrenceScopeService::class)->update($ana, $entry, ['amount_cents' => 1], 'all'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(RecurrenceScopeService::class)->delete($ana, $entry, 'all'))
        ->toThrow(AuthorizationException::class)
        ->and($entry->fresh()->toArray())->toBe($antes);
});
