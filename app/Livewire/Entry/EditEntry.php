<?php

namespace App\Livewire\Entry;

use App\Models\Card;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Recurrence;
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

    public ?string $dueOn = null;

    public bool $paid = false;

    /** Mês do lançamento (Y-m): só muda em lançamentos que não são recorrentes. */
    public string $month = '';

    // regra da conta recorrente (vale para a conta toda)
    public ?int $chargeDay = null;

    public string $endMode = 'never'; // never | date

    public ?string $endsOn = null;

    public ?int $installmentsTotal = null;

    public ?string $error = null;

    public function mount(Entry $entry): void
    {
        Gate::authorize('view', $entry);

        $this->entry = $entry;
        $this->name = $entry->name;
        $this->amount = Money::format($entry->amount_cents);
        $this->categoryId = $entry->category_id;
        $this->cardId = $entry->card_id;
        $this->dueOn = $entry->due_on?->toDateString();
        $this->paid = (bool) $entry->paid;
        $this->month = $entry->month->format('Y-m');

        if ($entry->recurrence_id !== null) {
            $recurrence = Recurrence::withoutGlobalScopes()->where('couple_id', $entry->couple_id)->find($entry->recurrence_id);
            $this->chargeDay = $recurrence?->charge_day;
            $this->installmentsTotal = $recurrence?->installments_total;
            $this->endsOn = $recurrence?->ends_on?->toDateString();
            $this->endMode = $this->endsOn ? 'date' : 'never';
        }
    }

    public function isInstallment(): bool
    {
        return $this->installmentsTotal !== null;
    }

    public function save(EntryService $entries, RecurrenceScopeService $scopes): mixed
    {
        Gate::authorize('update', $this->entry);
        $this->error = null;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'categoryId' => ['required', 'integer'],
            'dueOn' => ['nullable', 'date'],
            'month' => ['required', 'date_format:Y-m'],
            'chargeDay' => ['nullable', 'integer', 'min:1', 'max:31'],
            'installmentsTotal' => ['nullable', 'integer', 'min:2', 'max:360'],
            'endsOn' => ['nullable', 'date'],
        ], [], ['dueOn' => 'vencimento', 'month' => 'mês', 'chargeDay' => 'dia de cobrança', 'installmentsTotal' => 'número de parcelas', 'endsOn' => 'data final']);

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

            $single = [
                'due_on' => $this->entry->kind === Entry::SAIDA ? ($this->dueOn ?: null) : null,
                'paid' => $this->paid,
                'paid_by' => $this->paid ? ($this->entry->paid ? $this->entry->paid_by : auth()->id()) : null,
            ];

            if ($this->entry->recurrence_id !== null) {
                $this->guardScope();
                $scopes->update(auth()->user(), $this->entry, $changes + $single, $this->scope);

                $scopes->reschedule(auth()->user(), $this->entry->refresh(), array_filter([
                    'charge_day' => $this->chargeDay,
                    'installments_total' => $this->installmentsTotal,
                ], fn ($v) => $v !== null) + ['ends_on' => $this->isInstallment() || $this->endMode !== 'date' ? null : $this->endsOn]);
            } else {
                $entries->update(auth()->user(), $this->entry, $changes + $single + ['month' => $this->month.'-01']);
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->redirectRoute('statement', ['m' => $this->entry->fresh()->month->format('Y-m')], navigate: true);
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
            'categories' => Category::forKind($this->entry->kind)->orderBy('position')->get(),
            'cards' => Card::orderBy('name')->get(),
            'canEdit' => Gate::allows('update', $this->entry),
        ]);
    }
}
