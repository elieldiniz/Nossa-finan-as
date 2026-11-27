<?php

namespace App\Livewire\Cards;

use App\Livewire\Concerns\WithMonthView;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\Entry;
use App\Services\CardPurchaseCleaner;
use App\Services\EntryService;
use App\Services\MonthSummary;
use App\Support\Money;
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

    public string $search = '';

    // excluir compras em massa
    public bool $showClean = false;

    public string $cleanScope = 'month'; // month | all

    public bool $stopSeries = false;

    /** O que foi excluído por último (para o botão Desfazer). @var array<string, mixed>|null */
    public ?array $undo = null;

    public ?string $cleanDone = null;

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

    public function openClean(): void
    {
        Gate::authorize('update', $this->card);
        $this->showClean = true;
        $this->cleanScope = 'month';
        $this->stopSeries = false;
        $this->cleanDone = null;
    }

    public function updatedCleanScope(): void
    {
        // "todos os meses": as parceladas e fixas devem parar também (senão a próxima parcela volta)
        $this->stopSeries = $this->cleanScope === 'all';
    }

    public function closeClean(): void
    {
        $this->showClean = false;
    }

    public function cleanAll(CardPurchaseCleaner $cleaner): void
    {
        Gate::authorize('update', $this->card);
        $month = $this->cleanScope === 'all' ? null : $this->monthDate();

        $result = $cleaner->delete(auth()->user(), $this->card, $month, $this->stopSeries);

        $this->undo = $result['undo'];
        $this->showClean = false;
        $this->cleanDone = $result['deleted'] === 0
            ? 'Não havia compras suas para excluir.'
            : "{$result['deleted']} compras excluídas (".Money::format($result['total']).').';
    }

    public function undoClean(CardPurchaseCleaner $cleaner): void
    {
        if ($this->undo === null) {
            return;
        }

        $n = $cleaner->undo(auth()->user(), $this->undo);
        $this->undo = null;
        $this->cleanDone = "{$n} compras restauradas.";
    }

    public function dismissClean(): void
    {
        $this->undo = null;
        $this->cleanDone = null;
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
            'lines' => ($mine['lines'] ?? collect())->when(trim($this->search) !== '', function ($l) {
                $term = mb_strtolower(trim($this->search));

                return $l->filter(fn (Entry $e) => str_contains(mb_strtolower($e->name.' '.($e->category?->name ?? '')), $term));
            })->values(),
            'clean' => $this->showClean ? app(CardPurchaseCleaner::class)->preview(auth()->user(), $this->card, $this->cleanScope === 'all' ? null : $this->monthDate()) : null,
            'allCount' => ($mine['lines'] ?? collect())->count(),
            'total' => $mine['total'] ?? 0,
            'paid' => $mine['paid'] ?? false,
            'isFuture' => $this->monthDate()->greaterThan(now()->startOfMonth()),
        ]);
    }
}
