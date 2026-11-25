<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * 200 lançamentos em um mês, para medir tempo e consultas (RNF-01, RNF-02). Só para testes e QA.
 */
class Perf200Seeder extends Seeder
{
    public function run(User $owner, string $month = '2026-09-01', int $count = 200): void
    {
        $category = Category::withoutGlobalScopes()->where('couple_id', $owner->couple_id)->first();
        $now = now();

        Entry::withoutGlobalScopes()->insert(array_map(fn (int $i) => [
            'couple_id' => $owner->couple_id, 'owner_id' => $owner->id, 'kind' => $i % 5 === 0 ? 'entrada' : 'saida',
            'name' => "Item {$i}", 'amount_cents' => 1000 + $i, 'month' => $month, 'category_id' => $category->id,
            'paid' => $i % 2 === 0, 'joint' => false, 'created_at' => $now, 'updated_at' => $now,
        ], range(1, $count)));
    }
}
