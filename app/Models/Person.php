<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use BelongsToCouple;

    protected $table = 'people';

    protected $fillable = ['name'];

    public function debts(): HasMany
    {
        return $this->hasMany(PersonDebt::class);
    }
}
