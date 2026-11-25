<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;

class Settlement extends Model
{
    use BelongsToCouple;

    protected $table = 'settlements';

    protected $fillable = ['month', 'amount_cents', 'settled_by', 'settled_at'];

    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m-d',
            'settled_at' => 'datetime',
        ];
    }
}
