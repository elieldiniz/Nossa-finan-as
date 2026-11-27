<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Entry;
use App\Models\Goal;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\ValeSpend;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Categorias editáveis (RF-12). Remover uma categoria em uso exige escolher outra para receber os lançamentos.
 */
class CategoryService
{
    public function add(User $actor, string $name, string $kind = Category::SAIDA): Category
    {
        Gate::forUser($actor)->authorize('create', Category::class);

        $name = trim($name);
        if ($name === '' || Category::where('name', $name)->exists()) {
            throw new InvalidArgumentException('Já existe uma categoria com esse nome (ou o nome está vazio).');
        }

        if (! in_array($kind, [Category::SAIDA, Category::ENTRADA, Category::AMBAS, Category::APORTE], true)) {
            throw new InvalidArgumentException('Escolha se a categoria é de saída, de entrada, de investimento ou de ambas.');
        }

        $category = new Category(['name' => $name, 'kind' => $kind, 'position' => (int) Category::max('position') + 1]);
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
            || Recurrence::where('category_id', $category->id)->exists()
            || ValeSpend::where('category_id', $category->id)->exists();

        if ($inUse && ($moveTo === null || $moveTo->id === $category->id)) {
            throw new InvalidArgumentException('Esta categoria está em uso (lançamentos, contas recorrentes ou gastos do vale alimentação): escolha outra para receber.');
        }

        if ($inUse && ! $moveTo->fitsKind($category->kind === Category::AMBAS ? Category::SAIDA : $category->kind)) {
            throw new InvalidArgumentException('A categoria de destino precisa servir para o mesmo tipo (entrada ou saída).');
        }

        DB::transaction(function () use ($category, $moveTo, $inUse) {
            if ($inUse) {
                Entry::withTrashed()->where('category_id', $category->id)->update(['category_id' => $moveTo->id]);
                Recurrence::where('category_id', $category->id)->update(['category_id' => $moveTo->id]);
                ValeSpend::where('category_id', $category->id)->update(['category_id' => $moveTo->id]);
            }
            Goal::where('category_id', $category->id)->delete();
            $category->delete();
        });
    }
}
