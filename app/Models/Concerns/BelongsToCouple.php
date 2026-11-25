<?php

namespace App\Models\Concerns;

use App\Models\Couple;
use App\Models\Scopes\CoupleScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCouple
{
    protected static function bootBelongsToCouple(): void
    {
        static::addGlobalScope(new CoupleScope);

        // Quem cria é sempre do casal do usuário logado: o cliente nunca escolhe o casal.
        static::creating(function ($model) {
            $user = auth()->user();

            if ($user !== null && $user->couple_id !== null) {
                $model->couple_id = $user->couple_id;
            }
        });
    }

    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }
}
