<?php

namespace App\Services;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cria o casal de quem se cadastra (sem convite) com as 10 categorias iniciais (RF-12, RF-28).
 */
class CoupleBootstrap
{
    public const DEFAULT_CATEGORIES = [
        'Moradia', 'Mercado', 'Assinatura', 'Transporte', 'Saúde',
        'Lazer', 'Educação', 'Pessoas', 'Vale', 'Outros',
    ];

    public function createFor(User $user): Couple
    {
        return DB::transaction(function () use ($user) {
            $couple = Couple::create(['name' => 'Casal de '.$user->name]);

            $now = now();
            DB::table('categories')->insert(array_map(
                fn (string $name, int $position) => [
                    'couple_id' => $couple->id, 'name' => $name, 'position' => $position,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                self::DEFAULT_CATEGORIES,
                array_keys(self::DEFAULT_CATEGORIES),
            ));

            $user->forceFill(['couple_id' => $couple->id])->save();

            return $couple;
        });
    }
}
