<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Regra do casal (RF-02, RF-03): os dois LEEM tudo do casal; só o DONO altera ou apaga o que é seu.
 */
class OwnedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->couple_id !== null;
    }

    public function view(User $user, Model $model): bool
    {
        return $user->couple_id !== null && $model->couple_id === $user->couple_id;
    }

    public function create(User $user): bool
    {
        return $user->couple_id !== null;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->view($user, $model) && (int) $model->owner_id === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }
}
