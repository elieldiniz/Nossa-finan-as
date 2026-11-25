<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Couple extends Model
{
    protected $fillable = ['name'];

    public function members(): HasMany
    {
        return $this->hasMany(User::class)->orderBy('id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
