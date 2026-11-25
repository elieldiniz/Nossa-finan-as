<?php

namespace App\Livewire\Home;

use App\Domain\DueWindow;
use App\Domain\GoalAlert;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Entry;
use App\Models\Goal;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Início · Nossa Finanças')]
class HomePage extends Component
{
    use WithMonthView;

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
            'due' => $this->due(),
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
        $goal = Goal::whereNull('category_id')->first();

        return [
            'goal' => $goal,
            'level' => $goal ? GoalAlert::evaluate($summary['closing']->expense, $goal->limit_cents) : GoalAlert::NONE,
        ];
    }

    private function due(): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $context = $this->context();

        $items = Entry::query()->where('paid', false)->where('kind', Entry::SAIDA)
            ->whereBetween('due_on', [$today->toDateString(), $today->addDays(7)->toDateString()])
            ->get()->filter(fn (Entry $e) => $context->applies($e->owner_id))
            ->map(fn (Entry $e) => ['due' => $e->due_on->toDateString(), 'paid' => $e->paid, 'name' => $e->name, 'amount' => $e->amount_cents])->all();

        return DueWindow::upcoming($items, $today, 7);
    }
}
