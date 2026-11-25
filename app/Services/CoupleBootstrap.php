<?php

namespace App\Services;

use App\Models\Couple;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cria o casal de quem se cadastra (sem convite) com as categorias iniciais (10 de saída/ambas, 4 de entrada e 5 de investimento) (RF-12, RF-28).
 */
class CoupleBootstrap
{
    /** nome => tipo. "Outros" serve para entradas e saídas. */
    public const DEFAULT_CATEGORIES = [
        'Moradia' => 'saida', 'Mercado' => 'saida', 'Assinatura' => 'saida', 'Transporte' => 'saida', 'Saúde' => 'saida',
        'Lazer' => 'saida', 'Educação' => 'saida', 'Pessoas' => 'saida', 'Vale' => 'saida', 'Outros' => 'ambas',
        'Salário' => 'entrada', 'Renda extra' => 'entrada', 'Reembolso' => 'entrada', 'Rendimentos' => 'entrada',
        'Reserva de emergência' => 'aporte', 'Renda fixa' => 'aporte', 'Ações e FIIs' => 'aporte', 'Previdência' => 'aporte', 'Outros investimentos' => 'aporte',
    ];

    public function createFor(User $user): Couple
    {
        return DB::transaction(function () use ($user) {
            $couple = Couple::create(['name' => 'Casal de '.$user->name]);

            $now = now();
            DB::table('categories')->insert(array_map(
                fn (string $name, string $kind, int $position) => [
                    'couple_id' => $couple->id, 'name' => $name, 'kind' => $kind, 'position' => $position,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                array_keys(self::DEFAULT_CATEGORIES),
                array_values(self::DEFAULT_CATEGORIES),
                range(0, count(self::DEFAULT_CATEGORIES) - 1),
            ));

            $user->forceFill(['couple_id' => $couple->id])->save();

            return $couple;
        });
    }
}
