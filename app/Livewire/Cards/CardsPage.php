<?php

namespace App\Livewire\Cards;

use App\Livewire\Concerns\WithMonthView;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Services\MonthSummary;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Cartões livres e fatura do mês (RF-13, RF-14, RF-15). Sem limite de quantidade.
 */
#[Layout('components.layouts.app')]
#[Title('Cartões · Nossa Finanças')]
class CardsPage extends Component
{
    use WithMonthView;

    public string $name = '';

    public ?int $closingDay = null;

    public ?int $dueDay = null;

    public string $limit = '';

    #[Url(as: 'editar')]
    public ?int $editingId = null;

    public function mount(): void
    {
        if ($this->editingId) {
            $this->edit($this->editingId);
        }
    }

    public function addCard(): void
    {
        Gate::authorize('create', Card::class);
        $this->validate(['name' => ['required', 'string', 'max:255'], 'closingDay' => ['nullable', 'integer', 'between:1,31'], 'dueDay' => ['nullable', 'integer', 'between:1,31']], [], ['name' => 'nome']);

        $card = $this->editingId ? Card::findOrFail($this->editingId) : new Card(['owner_id' => auth()->id()]);
        Gate::authorize($this->editingId ? 'update' : 'create', $this->editingId ? $card : Card::class);

        $card->fill([
            'name' => $this->name, 'closing_day' => $this->closingDay, 'due_day' => $this->dueDay,
            'limit_cents' => $this->limit === '' ? null : Money::parse($this->limit),
        ]);
        $card->couple_id = auth()->user()->couple_id;
        $card->save();

        $this->reset('name', 'closingDay', 'dueDay', 'limit', 'editingId');
    }

    public function edit(int $id): void
    {
        $card = Card::findOrFail($id);
        $this->editingId = $card->id;
        $this->name = $card->name;
        $this->closingDay = $card->closing_day;
        $this->dueDay = $card->due_day;
        $this->limit = $card->limit_cents ? Money::format($card->limit_cents) : '';
    }

    public function remove(int $id): void
    {
        $card = Card::findOrFail($id);
        Gate::authorize('delete', $card);
        $card->delete();
    }

    public function toggleInvoice(int $id): void
    {
        $card = Card::findOrFail($id);
        Gate::authorize('update', $card);

        $invoice = CardInvoice::firstOrNew(['card_id' => $card->id, 'month' => $this->monthDate()->toDateString()]);
        $invoice->couple_id = auth()->user()->couple_id;
        $invoice->paid = ! $invoice->paid;
        $invoice->paid_at = $invoice->paid ? now() : null;
        $invoice->save();
    }

    public function render()
    {
        $this->openMonth();

        return view('livewire.cards.cards-page', [
            'summary' => app(MonthSummary::class)->build(auth()->user(), $this->monthDate(), $this->view),
            'isFuture' => $this->monthDate()->greaterThan(now()->startOfMonth()),
        ]);
    }
}
