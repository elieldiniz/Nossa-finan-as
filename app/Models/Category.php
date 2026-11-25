<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToCouple;

    protected $table = 'categories';

    protected $fillable = ['name', 'position'];
}
