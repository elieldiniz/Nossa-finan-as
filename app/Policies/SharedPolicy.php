<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Cadastros compartilhados (categorias, cartões, pessoas, metas): os dois leem e editam.
 */
class SharedPolicy extends OwnedPolicy
{
    public function update(User $user, Model $model): bool
    {
        return $this->view($user, $model);
    }
}
