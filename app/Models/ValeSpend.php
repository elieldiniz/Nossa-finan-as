<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValeSpend extends Model
{
    use BelongsToCouple;

    protected $table = 'vale_spends';

    protected $fillable = ['vale_id', 'owner_id', 'name', 'category_id', 'amount_cents', 'month', 'spent_on', 'external_id'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Data a mostrar no extrato: a informada; senão o dia em que foi lançado (se for do mês); senão o dia 1º. */
    public function statementDate(): CarbonImmutable
    {
        if ($this->spent_on) {
            return $this->spent_on->toImmutable();
        }

        $month = $this->month->toImmutable()->startOfMonth();
        $created = $this->created_at?->toImmutable();

        return $created && $created->format('Y-m') === $month->format('Y-m') ? $created->startOfDay() : $month;
    }

    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m-d',
            'spent_on' => 'date:Y-m-d',
        ];
    }
}
