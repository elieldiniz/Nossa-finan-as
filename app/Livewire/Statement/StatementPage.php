<?php

namespace App\Livewire\Statement;

use App\Domain\InvalidQuickPhrase;
use App\Domain\QuickPhraseParser;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\Category;
use App\Models\Entry;
use App\Services\EntryService;
use App\Services\MonthSummary;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Extrato · Nossa Finanças')]
class StatementPage extends Component
{
    use WithMonthView;

    public string $filter = 'all'; // all | pending | paid

    /** Frase que explica o que acabou de acontecer ao marcar/desmarcar. */
    public ?string $notice = null;

    public string $search = '';

    public string $quick = '';

    public ?string $quickError = null;

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['all', 'pending', 'paid'], true) ? $filter : 'all';
    }

    /** Frase rápida (RF-05): "50 mercado" vira saída de R$ 50,00 em "Outros". Formato inválido não grava nada. */
    public function quickAdd(EntryService $entries): void
    {
        $this->quickError = null;

        if ($this->context()->isReadOnly()) {
            $this->quickError = 'Você está na visão do seu par (somente leitura).';

            return;
        }

        try {
            $parsed = QuickPhraseParser::parse($this->quick);
        } catch (InvalidQuickPhrase $e) {
            $this->quickError = $e->getMessage();

            return;
        }

        $category = Category::where('name', $parsed->category)->first() ?? Category::orderBy('position')->firstOrFail();

        $entries->create(auth()->user(), [
            'kind' => $parsed->type, 'name' => $parsed->name, 'amount_cents' => $parsed->amount,
            'month' => $this->monthDate()->toDateString(), 'category_id' => $category->id,
        ]);

        $this->quick = '';
    }

    /** O círculo de "pago": só o dono altera (Policy); a fatura de cartão é compartilhada. */
    public function togglePaid(int $entryId, EntryService $entries): void
    {
        $entry = Entry::findOrFail($entryId);
        Gate::authorize('update', $entry);

        $entries->setPaid(auth()->user(), $entry, ! $entry->paid);

        [$feito, $pendente] = match (true) {
            $entry->kind === Entry::ENTRADA => ['recebida', 'a receber'],
            $entry->kind === Entry::APORTE => ['aplicado', 'a aplicar'],
            default => ['paga', 'a pagar'],
        };

        $this->notice = $entry->paid
            ? "“{$entry->name}” marcada como {$feito} ✓. Para desfazer, toque no ✓ de novo."
            : "“{$entry->name}” voltou para “{$pendente}”."
                .($entry->kind === Entry::SAIDA && $entry->due_on ? ' Se o vencimento estiver próximo, ela volta para Contas a vencer no Início.' : '');
    }

    public function toggleInvoice(int $cardId): void
    {
        $card = Card::findOrFail($cardId);
        Gate::authorize('update', $card);

        $invoice = CardInvoice::firstOrNew(['card_id' => $card->id, 'month' => $this->monthDate()->toDateString()]);
        $invoice->couple_id = auth()->user()->couple_id;
        $invoice->paid = ! $invoice->paid;
        $invoice->paid_at = $invoice->paid ? now() : null;
        $invoice->save();

        $this->notice = $invoice->paid
            ? "Fatura do {$card->name} marcada como paga ✓. Para desfazer, toque no ✓ de novo."
            : "Fatura do {$card->name} voltou para “a pagar”. Se o vencimento estiver próximo, ela volta para Contas a vencer no Início.";
    }

    /** Cartões com a lista de compras aberta. @var array<int, int> */
    public array $openCards = [];

    public function toggleCard(int $cardId): void
    {
        $this->openCards = in_array($cardId, $this->openCards, true)
            ? array_values(array_diff($this->openCards, [$cardId]))
            : [...$this->openCards, $cardId];
    }

    public function dismissNotice(): void
    {
        $this->notice = null;
    }

    public function render()
    {
        $this->openMonth();

        $service = app(MonthSummary::class);
        $summary = $service->build(auth()->user(), $this->monthDate(), $this->view);
        $term = mb_strtolower(trim($this->search));

        $rows = $service->rows($summary)
            ->when($this->filter === 'pending', fn ($r) => $r->where('paid', false))
            ->when($this->filter === 'paid', fn ($r) => $r->where('paid', true))
            ->when($term !== '', fn ($r) => $r->filter(fn ($row) => str_contains(mb_strtolower($row['name'].' '.$row['sub'].' '.collect($row['lines'] ?? [])->pluck('name')->implode(' ')), $term)))
            ->values();

        return view('livewire.statement.statement-page', ['summary' => $summary, 'rows' => $rows]);
    }
}
