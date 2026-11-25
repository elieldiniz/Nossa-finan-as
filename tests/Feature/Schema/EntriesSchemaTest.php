<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function rawEntry(array $over = []): array
{
    [$a, , $couple] = makeCouple();

    return $over + [
        'couple_id' => $couple->id,
        'owner_id' => $a->id,
        'kind' => 'saida',
        'name' => 'Teste',
        'amount_cents' => 1000,
        'month' => '2026-09-01',
        'category_id' => categoryOf($couple)->id,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}

it('grava um lançamento válido', function () {
    DB::table('entries')->insert(rawEntry());

    expect(DB::table('entries')->count())->toBe(1);
});

it('recusa lançamento sem categoria', function () {
    DB::table('entries')->insert(rawEntry(['category_id' => null]));
})->throws(QueryException::class);

it('recusa valor zero ou negativo no banco', function (int $valor) {
    DB::table('entries')->insert(rawEntry(['amount_cents' => $valor]));
})->with([0, -100])->throws(QueryException::class);

it('recusa a mesma ocorrência de recorrência duas vezes no mesmo mês', function () {
    $row = rawEntry();
    $recId = DB::table('recurrences')->insertGetId([
        'couple_id' => $row['couple_id'], 'owner_id' => $row['owner_id'], 'category_id' => $row['category_id'],
        'kind' => 'saida', 'name' => 'Fixo', 'amount_cents' => 30000, 'frequency' => 'monthly',
        'starts_on' => '2026-01-01', 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('entries')->insert($row + ['recurrence_id' => $recId]);
    DB::table('entries')->insert($row + ['recurrence_id' => $recId]);
})->throws(QueryException::class);
