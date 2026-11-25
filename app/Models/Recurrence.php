<?php

namespace App\Models;

use App\Domain\RecurrenceSchedule;
use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recurrence extends Model
{
    use BelongsToCouple;

    protected $fillable = [
        'owner_id', 'category_id', 'card_id', 'kind', 'name', 'amount_cents', 'frequency',
        'starts_on', 'charge_day', 'ends_on', 'installments_total', 'joint',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'joint' => 'boolean',
            'amount_cents' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function schedule(): RecurrenceSchedule
    {
        return new RecurrenceSchedule(
            $this->starts_on->toImmutable(),
            $this->frequency,
            $this->charge_day,
            $this->ends_on?->toImmutable(),
            $this->installments_total,
        );
    }
}
