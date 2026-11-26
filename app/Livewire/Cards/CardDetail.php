<?php

namespace App\Livewire\Cards;

use App\Livewire\Concerns\WithMonthView;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\Entry;
use App\Services\EntryService;
use App\Services\MonthSummary;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Tela de um cartão: só as compras dele no mês, cada uma com editar e excluir. */
#[Layout('components.layouts.app')]
#[Title('Cartão · Nossa Finanças')]
class CardDetail extends Component
{
    use WithMonthView;

    public Card $card;

    public ?string $error = null;

    public function mount(Card $card): void
    {
        Gate::authorize('view', $card);
        $this->card = $card;
    }

    /** Exclui uma compra que não é recorrente; as recorrentes/parceladas pedem o escopo na tela de edição. */
    public function deleteLine(int $entryId, EntryService $entries): void
    {
        $this->error = null;
        $entry = Entry::where('card_id', $this->card->id)->findOrFail($entryId);
        Gate::authorize('delete', $entry);

        try {
            $entries->delete(auth()->user(), $entry);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function toggleInvoice(): void
    {
        Gate::authorize('update', $this->card);

        $invoice = CardInvoice::firstOrNew(['card_id' => $this->card->id, 'month' => $this->monthDate()->toDateString()]);
        $invoice->couple_id = auth()->user()->couple_id;
        $invoice->paid = ! $invoice->paid;
        $invoice->paid_at = $invoice->paid ? now() : null;
        $invoice->save();
    }

    public function removeCard(): mixed
    {
        Gate::authorize('delete', $this->card);
        $this->card->delete();

        return $this->redirectRoute('cards', navigate: true);
    }

    public function render()
    {
        $this->openMonth();

        $summary = app(MonthSummary::class)->build(auth()->user(), $this->monthDate(), $this->view);
        $mine = collect($summary['cards'])->first(fn ($c) => $c['card']->id === $this->card->id);

        return view('livewire.cards.card-detail', [
            'summary' => $summary,
            'lines' => $mine['lines'] ?? collect(),
            'total' => $mine['total'] ?? 0,
            'paid' => $mine['paid'] ?? false,
            'isFuture' => $this->monthDate()->greaterThan(now()->startOfMonth()),
        ]);
    }
}
