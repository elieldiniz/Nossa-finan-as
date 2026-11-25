<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use BelongsToCouple;

    protected $table = 'goals';

    protected $fillable = ['category_id', 'limit_cents'];
}
