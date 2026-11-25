<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Entry;
use App\Models\Goal;
use App\Models\Recurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Categorias editáveis (RF-12). Remover uma categoria em uso exige escolher outra para receber os lançamentos.
 */
class CategoryService
{
    public function add(User $actor, string $name): Category
    {
        Gate::forUser($actor)->authorize('create', Category::class);

        $name = trim($name);
        if ($name === '' || Category::where('name', $name)->exists()) {
            throw new InvalidArgumentException('Já existe uma categoria com esse nome (ou o nome está vazio).');
        }

        $category = new Category(['name' => $name, 'position' => (int) Category::max('position') + 1]);
        $category->couple_id = $actor->couple_id;
        $category->save();

        return $category;
    }

    public function rename(User $actor, Category $category, string $name): Category
    {
        Gate::forUser($actor)->authorize('update', $category);

        $name = trim($name);
        if ($name === '' || Category::where('name', $name)->where('id', '!=', $category->id)->exists()) {
            throw new InvalidArgumentException('Já existe uma categoria com esse nome (ou o nome está vazio).');
        }

        $category->update(['name' => $name]);

        return $category;
    }

    public function remove(User $actor, Category $category, ?Category $moveTo = null): void
    {
        Gate::forUser($actor)->authorize('delete', $category);

        $inUse = Entry::withTrashed()->where('category_id', $category->id)->exists()
            || Recurrence::where('category_id', $category->id)->exists();

        if ($inUse && ($moveTo === null || $moveTo->id === $category->id)) {
            throw new InvalidArgumentException('Esta categoria está em uso: escolha outra para receber os lançamentos.');
        }

        DB::transaction(function () use ($category, $moveTo, $inUse) {
            if ($inUse) {
                Entry::withTrashed()->where('category_id', $category->id)->update(['category_id' => $moveTo->id]);
                Recurrence::where('category_id', $category->id)->update(['category_id' => $moveTo->id]);
            }
            Goal::where('category_id', $category->id)->delete();
            $category->delete();
        });
    }
}
