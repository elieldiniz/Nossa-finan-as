<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use BelongsToCouple;

    protected $table = 'cards';

    protected $fillable = ['name', 'closing_day', 'due_day', 'limit_cents', 'owner_id'];
}
