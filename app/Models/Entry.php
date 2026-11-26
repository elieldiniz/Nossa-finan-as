<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Entry extends Model
{
    use BelongsToCouple, SoftDeletes;

    public const ENTRADA = 'entrada';

    public const SAIDA = 'saida';

    /** Investimento (aporte). O valor é "aporte" porque a coluna kind tem 10 caracteres. */
    public const APORTE = 'aporte';

    /** couple_id fica de fora de propósito: nunca vem do cliente. */
    protected $fillable = [
        'owner_id', 'kind', 'name', 'amount_cents', 'month', 'due_on', 'category_id', 'card_id',
        'paid', 'joint', 'paid_by', 'recurrence_id', 'installment_label', 'external_id',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m-d',
            'due_on' => 'date:Y-m-d',
            'paid' => 'boolean',
            'joint' => 'boolean',
            'amount_cents' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(Recurrence::class);
    }
}
