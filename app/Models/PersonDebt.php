<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersonDebt extends Model
{
    use BelongsToCouple;

    protected $fillable = ['person_id', 'owner_id', 'direction', 'description', 'amount_cents', 'created_month', 'settled_month'];

    protected function casts(): array
    {
        return ['created_month' => 'date:Y-m-d', 'settled_month' => 'date:Y-m-d', 'amount_cents' => 'integer'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PersonDebtInstallment::class)->orderBy('position');
    }

    /** Formato esperado por App\Domain\PersonBalance. */
    public function toBalanceArray(): array
    {
        return [
            'direction' => $this->direction,
            'amount' => $this->amount_cents,
            'settled_month' => $this->settled_month?->format('Y-m'),
            'installments' => $this->installments->map(fn ($i) => [
                'amount' => $i->amount_cents,
                'paid_month' => $i->paid_month?->format('Y-m'),
            ])->all(),
        ];
    }
}
