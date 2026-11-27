<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use App\Models\Entry;
use App\Models\ValeSpend;
use App\Services\CategoryService;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Categorias · Nossa Finanças')]
class CategoriesPage extends Component
{
    public string $newName = '';

    public string $newKind = 'saida';

    /** @var array<int, string> */
    public array $names = [];

    /** @var array<int, int|string|null> */
    public array $moveTo = [];

    public ?string $error = null;

    public function mount(): void
    {
        $this->names = Category::orderBy('position')->pluck('name', 'id')->all();
    }

    public function add(CategoryService $service): void
    {
        $this->guarded(fn () => $service->add(auth()->user(), $this->newName, $this->newKind));
        $this->newName = '';
        $this->mount();
    }

    public function rename(int $id, CategoryService $service): void
    {
        $this->guarded(fn () => $service->rename(auth()->user(), Category::findOrFail($id), $this->names[$id] ?? ''));
        $this->mount();
    }

    public function remove(int $id, CategoryService $service): void
    {
        $destination = ($this->moveTo[$id] ?? null) ? Category::find((int) $this->moveTo[$id]) : null;
        $this->guarded(fn () => $service->remove(auth()->user(), Category::findOrFail($id), $destination));
        $this->mount();
    }

    private function guarded(callable $action): void
    {
        $this->error = null;

        try {
            $action();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        // quanto cada categoria está em uso: lançamentos (inclui recorrentes) e gastos do vale alimentação
        $usage = [];
        foreach (Entry::selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id') as $id => $n) {
            $usage[$id]['entries'] = (int) $n;
        }
        foreach (ValeSpend::selectRaw('category_id, count(*) as n')->whereNotNull('category_id')->groupBy('category_id')->pluck('n', 'category_id') as $id => $n) {
            $usage[$id]['vale'] = (int) $n;
        }

        return view('livewire.categories.categories-page', ['categories' => Category::orderBy('position')->get(), 'usage' => $usage]);
    }
}
