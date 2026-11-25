<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('recusa valores negativos e donos inexistentes nas demais tabelas', function () {
    [$a, , $couple] = makeCouple();
    $now = ['created_at' => now(), 'updated_at' => now()];
    $personId = DB::table('people')->insertGetId(['couple_id' => $couple->id, 'name' => 'Madrinha'] + $now);

    $debt = fn (array $over) => DB::table('person_debts')->insert($over + [
        'couple_id' => $couple->id, 'person_id' => $personId, 'owner_id' => $a->id,
        'direction' => 'receber', 'amount_cents' => 41500, 'created_month' => '2026-09-01',
    ] + $now);

    $debt([]); // válido
    expect(DB::table('person_debts')->count())->toBe(1);

    expect(fn () => $debt(['amount_cents' => -1]))->toThrow(QueryException::class)
        ->and(fn () => $debt(['owner_id' => 99999]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('vales')->insert(['couple_id' => $couple->id, 'owner_id' => $a->id, 'amount_cents' => -5] + $now))->toThrow(QueryException::class);
});
