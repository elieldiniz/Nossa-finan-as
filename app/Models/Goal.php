<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use BelongsToCouple;

    public const GASTO = 'gasto';

    public const INVESTIMENTO = 'investimento';

    protected $table = 'goals';

    protected $fillable = ['category_id', 'kind', 'limit_cents'];
}
