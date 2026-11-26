<?php

namespace App\Livewire\Home;

use App\Domain\GoalAlert;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Card;
use App\Models\CardInvoice;
use App\Models\Entry;
use App\Models\Goal;
use App\Models\User;
use App\Services\DueBills;
use App\Services\EntryService;
use App\Services\MonthSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Início · Nossa Finanças')]
class HomePage extends Component
{
    use WithMonthView;

    /** Desmarca uma conta que estava paga (botão "Desfazer" da própria linha). */
    public function unpayBill(string $type, int $id, ?string $month, EntryService $entries): void
    {
        if ($type === 'invoice') {
            $card = Card::findOrFail($id);
            Gate::authorize('update', $card);
            CardInvoice::where('card_id', $card->id)->where('month', $month.'-01')->first()?->update(['paid' => false, 'paid_at' => null]);

            return;
        }

        $entry = Entry::findOrFail($id);
        Gate::authorize('update', $entry);
        $entries->setPaid(auth()->user(), $entry, false);
    }

    /** "Marcar pago" nas contas a vencer: lançamento (só o dono) ou fatura do cartão (compartilhada). */
    public function payBill(string $type, int $id, ?string $month, EntryService $entries): void
    {
        if ($type === 'invoice') {
            $card = Card::findOrFail($id);
            Gate::authorize('update', $card);

            $invoice = CardInvoice::firstOrNew(['card_id' => $card->id, 'month' => $month.'-01']);
            $invoice->couple_id = auth()->user()->couple_id;
            $invoice->paid = true;
            $invoice->paid_at = now();
            $invoice->save();

            return;
        }

        $entry = Entry::findOrFail($id);
        Gate::authorize('update', $entry);
        $entries->setPaid(auth()->user(), $entry, true);
    }

    public function render()
    {
        $this->openMonth();

        $service = app(MonthSummary::class);
        $summary = $service->build(auth()->user(), $this->monthDate(), $this->view);
        $rows = $service->rows($summary);

        return view('livewire.home.home-page', [
            'summary' => $summary,
            'recent' => $rows->take(5),
            'byCategory' => $this->expensesByCategory($summary),
            'goalAlert' => $this->goalAlert($summary),
            'owners' => User::where('couple_id', auth()->user()->couple_id)->pluck('name', 'id'),
            'due' => app(DueBills::class)->forMonth($this->monthDate(), $this->context()),
            'investing' => $this->investing($summary),
        ]);
    }

    /** @return Collection<string, int> categoria => centavos gastos no mês */
    private function expensesByCategory(array $summary): Collection
    {
        $cardLines = collect($summary['cards'])->flatMap(fn ($c) => $c['lines']);

        return $summary['entries']->where('kind', Entry::SAIDA)->merge($cardLines)
            ->groupBy(fn (Entry $e) => $e->category?->name ?? 'Sem categoria')
            ->map(fn ($group) => (int) $group->sum('amount_cents'))
            ->sortDesc();
    }

    private function goalAlert(array $summary): array
    {
        $goal = Goal::where('kind', Goal::GASTO)->whereNull('category_id')->first();

        return [
            'goal' => $goal,
            'level' => $goal ? GoalAlert::evaluate($summary['closing']->expense, $goal->limit_cents) : GoalAlert::NONE,
        ];
    }

    /** Investimentos: investido no mês, meta de investir e total acumulado até o mês. */
    private function investing(array $summary): array
    {
        $context = $this->context();
        $goal = Goal::where('kind', Goal::INVESTIMENTO)->whereNull('category_id')->first();

        $accumulated = (int) Entry::where('kind', Entry::APORTE)->where('month', '<=', $this->monthDate()->toDateString())->get()
            ->filter(fn (Entry $e) => $context->applies($e->owner_id))->sum('amount_cents');

        return ['month' => $summary['closing']->invested, 'goal' => $goal, 'accumulated' => $accumulated];
    }
}
