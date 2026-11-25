<?php

namespace App\Livewire\Entry;

use App\Models\Card;
use App\Models\Category;
use App\Models\Entry;
use App\Services\EntryService;
use App\Services\RecurrenceScopeService;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Detalhe, edição e exclusão de um lançamento (RF-07, RF-08). Recorrente pede o escopo.
 */
#[Layout('components.layouts.app')]
#[Title('Lançamento · Nossa Finanças')]
class EditEntry extends Component
{
    public Entry $entry;

    public string $name = '';

    public string $amount = '';

    public ?int $categoryId = null;

    public ?int $cardId = null;

    public string $scope = RecurrenceScopeService::FOLLOWING;

    public bool $confirmAll = false;

    public ?string $error = null;

    public function mount(Entry $entry): void
    {
        Gate::authorize('view', $entry);

        $this->entry = $entry;
        $this->name = $entry->name;
        $this->amount = Money::format($entry->amount_cents);
        $this->categoryId = $entry->category_id;
        $this->cardId = $entry->card_id;
    }

    public function save(EntryService $entries, RecurrenceScopeService $scopes): mixed
    {
        Gate::authorize('update', $this->entry);
        $this->error = null;

        $this->validate(['name' => ['required', 'string', 'max:255'], 'categoryId' => ['required', 'integer']]);

        try {
            $changes = [
                'name' => $this->name,
                'amount_cents' => Money::parse($this->amount),
                'category_id' => $this->categoryId,
                'card_id' => $this->cardId,
            ];

            if ($changes['amount_cents'] <= 0) {
                throw new InvalidArgumentException('O valor precisa ser maior que zero.');
            }

            if ($this->entry->recurrence_id !== null) {
                $this->guardScope();
                $scopes->update(auth()->user(), $this->entry, $changes, $this->scope);
            } else {
                $entries->update(auth()->user(), $this->entry, $changes);
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirectRoute('statement', ['m' => $this->entry->month->format('Y-m')], navigate: true);
    }

    public function delete(EntryService $entries, RecurrenceScopeService $scopes): mixed
    {
        Gate::authorize('delete', $this->entry);
        $this->error = null;

        try {
            if ($this->entry->recurrence_id !== null) {
                $this->guardScope();
                $scopes->delete(auth()->user(), $this->entry, $this->scope);
            } else {
                $entries->delete(auth()->user(), $this->entry);
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirectRoute('statement', ['m' => $this->entry->month->format('Y-m')], navigate: true);
    }

    /** "Todos, inclusive os anteriores" mexe no histórico: exige confirmação explícita. */
    private function guardScope(): void
    {
        if ($this->scope === RecurrenceScopeService::ALL && ! $this->confirmAll) {
            throw new InvalidArgumentException('Confirme que quer mudar também os meses anteriores.');
        }
    }

    public function render()
    {
        return view('livewire.entry.edit-entry', [
            'categories' => Category::orderBy('position')->get(),
            'cards' => Card::orderBy('name')->get(),
            'canEdit' => Gate::allows('update', $this->entry),
        ]);
    }
}
