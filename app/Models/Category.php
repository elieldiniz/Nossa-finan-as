<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCouple;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToCouple;

    public const SAIDA = 'saida';

    public const ENTRADA = 'entrada';

    public const AMBAS = 'ambas';

    protected $table = 'categories';

    protected $fillable = ['name', 'kind', 'position'];

    /** Categorias que servem para o tipo de lançamento ("saida" ou "entrada"). */
    public function scopeForKind(Builder $query, string $kind): Builder
    {
        // "Outros" (ambas) sempre por último
        return $query->whereIn('kind', [$kind, self::AMBAS])->orderByRaw("case when kind = 'ambas' then 1 else 0 end");
    }

    public function fitsKind(string $kind): bool
    {
        return $this->kind === self::AMBAS || $this->kind === $kind;
    }
}
