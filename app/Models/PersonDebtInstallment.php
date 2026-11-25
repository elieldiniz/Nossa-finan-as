<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonDebtInstallment extends Model
{
    protected $fillable = ['person_debt_id', 'position', 'amount_cents', 'paid_month'];

    protected function casts(): array
    {
        return ['paid_month' => 'date:Y-m-d', 'amount_cents' => 'integer'];
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(PersonDebt::class, 'person_debt_id');
    }
}
