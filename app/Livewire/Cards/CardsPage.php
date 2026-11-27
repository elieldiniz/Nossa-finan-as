<?php

namespace App\Livewire\Cards;

use App\Livewire\Concerns\WithMonthView;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Services\AccountResetter;
use App\Services\CardPurchaseCleaner;
use App\Services\MonthSummary;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
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

    // limpeza em massa (parte de baixo da tela)
    public bool $showCleanAll = false;

    public string $cleanScope = 'month'; // month | all

    public bool $stopSeries = false;

    public bool $showDeleteCards = false;

    public string $deleteMode = 'keep'; // keep = manter as compras soltas | purge = apagar as compras também

    public string $confirmText = '';

    /** O que foi excluído por último (para o Desfazer). @var array<string, mixed>|null */
    public ?array $undo = null;

    public ?string $cleanDone = null;

    public function openCleanAll(): void
    {
        $this->showCleanAll = true;
        $this->showDeleteCards = false;
        $this->cleanScope = 'month';
        $this->stopSeries = false;
        $this->cleanDone = null;
    }

    public function updatedCleanScope(): void
    {
        $this->stopSeries = $this->cleanScope === 'all'; // "todos os meses": as parceladas e fixas devem parar também
    }

    public function cleanAll(CardPurchaseCleaner $cleaner): void
    {
        Gate::authorize('create', Card::class);
        $result = $cleaner->delete(auth()->user(), null, $this->cleanScope === 'all' ? null : $this->monthDate(), $this->stopSeries);

        $this->undo = $result['undo'];
        $this->showCleanAll = false;
        $this->cleanDone = $result['deleted'] === 0
            ? 'Não havia compras suas para excluir.'
            : "{$result['deleted']} compras excluídas de todos os cartões (".Money::format($result['total']).').';
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

    public function openDeleteCards(): void
    {
        $this->showDeleteCards = true;
        $this->showCleanAll = false;
        $this->deleteMode = 'keep';
        $this->confirmText = '';
        $this->cleanDone = null;
    }

    public function deleteAllCards(CardPurchaseCleaner $cleaner): void
    {
        Gate::authorize('create', Card::class);

        if (mb_strtoupper(trim($this->confirmText)) !== 'EXCLUIR') {
            $this->addError('confirmText', 'Digite EXCLUIR para confirmar.');

            return;
        }

        $result = $cleaner->deleteCards(auth()->user(), $this->deleteMode === 'purge');

        $this->showDeleteCards = false;
        $this->undo = null;
        $this->cleanDone = ($result['cards'] === 0 ? 'Nenhum cartão seu podia ser excluído.' : "{$result['cards']} cartões excluídos".($this->deleteMode === 'purge' ? " e {$result['purchases']} compras apagadas." : '. As compras ficaram como lançamentos sem cartão.'))
            .($result['kept'] > 0 ? " {$result['kept']} cartões foram mantidos por terem compras do parceiro." : '');
    }

    // apagar tudo e recomeçar
    public bool $showReset = false;

    public string $resetPhrase = '';

    public string $resetPassword = '';

    public function openReset(): void
    {
        $this->showReset = true;
        $this->showCleanAll = false;
        $this->showDeleteCards = false;
        $this->resetPhrase = '';
        $this->resetPassword = '';
        $this->cleanDone = null;
        $this->resetErrorBag();
    }

    public function resetAll(AccountResetter $resetter): void
    {
        Gate::authorize('create', Card::class);
        $this->resetErrorBag();

        if (mb_strtoupper(trim($this->resetPhrase)) !== 'APAGAR TUDO') {
            $this->addError('resetPhrase', 'Digite APAGAR TUDO para confirmar.');

            return;
        }

        if (! Hash::check($this->resetPassword, auth()->user()->password)) {
            $this->addError('resetPassword', 'Senha incorreta.');

            return;
        }

        $c = $resetter->reset(auth()->user());

        $this->showReset = false;
        $this->undo = null;
        $this->resetPhrase = $this->resetPassword = '';
        $this->cleanDone = "Tudo o que você cadastrou foi apagado: {$c['entries']} lançamentos, {$c['recurrences']} contas recorrentes ou parceladas, {$c['cards']} cartões, {$c['debts']} pendências de pessoas e {$c['vale_spends']} gastos do vale. Pode começar de novo.";
    }

    public function closeDialogs(): void
    {
        $this->showReset = false;
        $this->showCleanAll = false;
        $this->showDeleteCards = false;
        $this->resetErrorBag();
    }

    public function dismissClean(): void
    {
        $this->undo = null;
        $this->cleanDone = null;
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
            'resetPreview' => $this->showReset ? app(AccountResetter::class)->preview(auth()->user()) : null,
            'cleanRows' => $this->showCleanAll ? app(CardPurchaseCleaner::class)->previewByCard(auth()->user(), $this->cleanScope === 'all' ? null : $this->monthDate()) : [],
            'cardsPreview' => $this->showDeleteCards ? app(CardPurchaseCleaner::class)->previewCards(auth()->user()) : null,
        ]);
    }
}
