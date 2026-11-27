<?php

namespace App\Livewire\Goals;

use App\Domain\GoalAlert;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Goal;
use App\Models\ValeSpend;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Metas e investimentos (RF-20): meta de gastos do mês (com aviso em 80% e 100%), meta de investir por mês
 * e limites por categoria só para as categorias que a pessoa escolher. Os avisos ficam só dentro do app.
 */
#[Layout('components.layouts.app')]
#[Title('Metas · Nossa Finanças')]
class GoalsPage extends Component
{
    use WithMonthView;

    public string $generalLimit = '';

    public string $investLimit = '';

    public ?int $addCategoryId = null;

    public string $addCategoryLimit = '';

    public ?string $error = null;

    public function saveGeneral(): void
    {
        $this->saveGoal(Goal::GASTO, null, $this->generalLimit);
        $this->generalLimit = '';
    }

    /** Usa o valor sugerido (média dos últimos meses). */
    public function useSuggestion(int $cents): void
    {
        $this->saveGoal(Goal::GASTO, null, Money::format($cents));
    }

    public function saveInvest(): void
    {
        $this->saveGoal(Goal::INVESTIMENTO, null, $this->investLimit);
        $this->investLimit = '';
    }

    public function addCategoryGoal(): void
    {
        if ($this->addCategoryId === null || ! Category::forKind(Category::SAIDA)->whereKey($this->addCategoryId)->exists()) {
            $this->error = 'Escolha uma categoria de gasto.';

            return;
        }

        $this->saveGoal(Goal::GASTO, $this->addCategoryId, $this->addCategoryLimit);

        if ($this->error === null) {
            $this->reset('addCategoryId', 'addCategoryLimit');
        }
    }

    public function removeGoal(int $goalId): void
    {
        $goal = Goal::findOrFail($goalId);
        Gate::authorize('delete', $goal);
        $goal->delete();
    }

    private function saveGoal(string $kind, ?int $categoryId, string $value): void
    {
        $this->error = null;

        try {
            $cents = Money::parse($value);
            if ($cents <= 0) {
                throw new InvalidArgumentException('O valor precisa ser maior que zero.');
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage() === 'O valor precisa ser maior que zero.' ? $e->getMessage() : 'Informe um valor válido, por exemplo 5.000,00.';

            return;
        }

        $goal = Goal::where('kind', $kind)->where('category_id', $categoryId)->first() ?? new Goal(['kind' => $kind, 'category_id' => $categoryId]);
        Gate::authorize($goal->exists ? 'update' : 'create', $goal->exists ? $goal : Goal::class);

        $goal->limit_cents = $cents;
        $goal->couple_id = auth()->user()->couple_id;
        $goal->save();
    }

    /** Média dos gastos dos últimos 3 meses que tiveram gasto (sem contar o mês aberto). */
    private function suggestion(CarbonImmutable $month): ?int
    {
        $from = $month->subMonths(3);
        $totals = Entry::where('kind', Entry::SAIDA)->where('month', '>=', $from->toDateString())->where('month', '<', $month->toDateString())->get()
            ->groupBy(fn (Entry $e) => $e->month->format('Y-m'))->map(fn ($g) => (int) $g->sum('amount_cents'))->filter(fn ($t) => $t > 0);

        if ($totals->isEmpty()) {
            return null;
        }

        return (int) (round($totals->avg() / 10000) * 10000); // arredonda para R$ 100
    }

    public function render()
    {
        $this->openMonth();
        $month = $this->monthDate();
        $monthDate = $month->toDateString();

        $expenses = Entry::with('category')->where('month', $monthDate)->where('kind', Entry::SAIDA)->get();
        $byCategory = $expenses->groupBy('category_id')->map(fn ($g) => (int) $g->sum('amount_cents'));
        foreach (! auth()->user()->vale_in_balance ? [] : ValeSpend::where('month', $monthDate)->whereNotNull('category_id')->get()->groupBy('category_id') as $categoryId => $group) { // gasto do vale conta na categoria escolhida
            $byCategory[$categoryId] = ($byCategory[$categoryId] ?? 0) + (int) $group->sum('amount_cents');
        }
        $totalSpent = (int) $expenses->sum('amount_cents') + (auth()->user()->vale_in_balance ? (int) ValeSpend::query()->where('month', $monthDate)->sum('amount_cents') : 0);

        $goals = Goal::all();
        $general = $goals->first(fn (Goal $g) => $g->kind === Goal::GASTO && $g->category_id === null);
        $investGoal = $goals->first(fn (Goal $g) => $g->kind === Goal::INVESTIMENTO && $g->category_id === null);
        $categoryGoals = $goals->filter(fn (Goal $g) => $g->kind === Goal::GASTO && $g->category_id !== null)->keyBy('category_id');

        $invested = (int) Entry::where('kind', Entry::APORTE)->where('month', $monthDate)->sum('amount_cents');
        $accumulated = (int) Entry::where('kind', Entry::APORTE)->where('month', '<=', $monthDate)->sum('amount_cents');

        // categorias: só as que têm limite ou gasto no mês; as demais ficam no seletor "Adicionar limite"
        $categories = Category::forKind(Category::SAIDA)->orderBy('position')->get();
        $rows = $categories->filter(fn (Category $c) => $categoryGoals->has($c->id) || ($byCategory->get($c->id, 0) > 0))->map(fn (Category $c) => [
            'category' => $c,
            'spent' => $byCategory->get($c->id, 0),
            'goal' => $categoryGoals->get($c->id),
            'level' => $categoryGoals->has($c->id) ? GoalAlert::evaluate($byCategory->get($c->id, 0), $categoryGoals->get($c->id)->limit_cents) : GoalAlert::NONE,
        ])->values();

        $isCurrent = $month->format('Y-m') === CarbonImmutable::now()->format('Y-m');
        $daysLeft = $isCurrent ? max(1, $month->daysInMonth - CarbonImmutable::now()->day + 1) : null;

        return view('livewire.goals.goals-page', [
            'general' => $general,
            'generalLevel' => $general ? GoalAlert::evaluate($totalSpent, $general->limit_cents) : GoalAlert::NONE,
            'totalSpent' => $totalSpent,
            'remaining' => $general ? max(0, $general->limit_cents - $totalSpent) : null,
            'perDay' => ($general && $daysLeft) ? intdiv(max(0, $general->limit_cents - $totalSpent), $daysLeft) : null,
            'suggestion' => $general ? null : $this->suggestion($month),
            'investGoal' => $investGoal,
            'invested' => $invested,
            'accumulated' => $accumulated,
            'rows' => $rows,
            'available' => $categories->reject(fn (Category $c) => $categoryGoals->has($c->id))->values(),
        ]);
    }
}
