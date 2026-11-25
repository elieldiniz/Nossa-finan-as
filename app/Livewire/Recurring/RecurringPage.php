<?php

namespace App\Livewire\Recurring;

use App\Domain\RecurrenceSchedule;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Card;
use App\Models\Category;
use App\Models\Recurrence;
use App\Services\EntryService;
use App\Support\Money;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Recorrentes e assinaturas (RF-18): total do mês, lista e formulário.
 */
#[Layout('components.layouts.app')]
#[Title('Recorrentes · Nossa Finanças')]
class RecurringPage extends Component
{
    use WithMonthView;

    public string $kind = 'saida'; // saida | entrada (assinatura/fixo ou salário/renda que se repete)

    public string $name = '';

    public string $amount = '';

    public ?int $chargeDay = null;

    public ?int $cardId = null;

    public ?int $categoryId = null;

    public string $frequency = 'monthly'; // monthly | yearly

    public string $endMode = 'never';

    public ?string $endsOn = null;

    public ?string $error = null;

    public function updatedKind(): void
    {
        $this->resetValidation();

        if (! Category::forKind($this->kind)->whereKey($this->categoryId)->exists()) {
            $this->categoryId = $this->kind === 'entrada' ? Category::where('name', 'Salário')->value('id') : null;
        }

        if ($this->kind === 'entrada') {
            $this->cardId = null;
        }
    }

    public function save(EntryService $entries): void
    {
        $this->error = null;

        if ($this->context()->isReadOnly()) {
            $this->error = 'Você está na visão do seu par (somente leitura).';

            return;
        }

        $this->validate(['name' => ['required', 'string', 'max:255'], 'categoryId' => ['required', 'integer']], [], ['name' => 'nome', 'categoryId' => 'categoria']);

        try {
            $entries->create(auth()->user(), [
                'kind' => $this->kind, 'name' => $this->name, 'amount_cents' => Money::parse($this->amount),
                'month' => $this->monthDate()->toDateString(), 'category_id' => $this->categoryId, 'card_id' => $this->kind === 'entrada' ? null : $this->cardId,
                'repeat' => array_filter([
                    'frequency' => $this->frequency, 'charge_day' => $this->chargeDay,
                    'ends_on' => $this->endMode === 'date' ? $this->endsOn : null,
                ], fn ($v) => $v !== null),
            ]);
            $this->reset('name', 'amount', 'endsOn');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        $context = $this->context();
        $date = $this->monthDate();

        $items = Recurrence::with(['category', 'card'])->where('frequency', '!=', RecurrenceSchedule::INSTALLMENTS)->orderBy('name')->get()
            ->filter(fn (Recurrence $r) => $context->applies($r->owner_id))
            ->map(fn (Recurrence $r) => ['rec' => $r, 'active' => $r->schedule()->occursIn($date->year, $date->month)]);

        return view('livewire.recurring.recurring-page', [
            'items' => $items->values(),
            'total' => (int) $items->where('active', true)->filter(fn ($i) => $i['rec']->kind === 'saida')->sum(fn ($i) => $i['rec']->amount_cents),
            'incomeTotal' => (int) $items->where('active', true)->filter(fn ($i) => $i['rec']->kind === 'entrada')->sum(fn ($i) => $i['rec']->amount_cents),
            'categories' => Category::forKind($this->kind)->orderBy('position')->get(),
            'cards' => Card::orderBy('name')->get(),
        ]);
    }
}
