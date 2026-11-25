<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;

class ValeSpend extends Model
{
    use BelongsToCouple;

    protected $table = 'vale_spends';

    protected $fillable = ['vale_id', 'owner_id', 'name', 'amount_cents', 'month'];

    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m-d',
        ];
    }
}
