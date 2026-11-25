<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;

class CardInvoice extends Model
{
    use BelongsToCouple;

    protected $table = 'card_invoices';

    protected $fillable = ['card_id', 'month', 'paid', 'paid_at'];

    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m-d',
            'paid' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }
}
