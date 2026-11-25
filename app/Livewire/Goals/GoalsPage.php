<?php

namespace App\Livewire\Goals;

use App\Domain\DueWindow;
use App\Domain\GoalAlert;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Goal;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Metas e vencimentos (RF-20, RF-21). Avisos de 80% e 100% só dentro do app (sem e-mail e sem push).
 */
#[Layout('components.layouts.app')]
#[Title('Metas e vencimentos · Nossa Finanças')]
class GoalsPage extends Component
{
    use WithMonthView;

    public string $generalLimit = '';

    /** @var array<int, string> */
    public array $categoryLimits = [];

    public ?string $error = null;

    public function saveGeneral(): void
    {
        $this->saveGoal(null, $this->generalLimit);
        $this->generalLimit = '';
    }

    public function saveCategory(int $categoryId): void
    {
        $this->saveGoal($categoryId, $this->categoryLimits[$categoryId] ?? '');
        unset($this->categoryLimits[$categoryId]);
    }

    public function removeGoal(int $goalId): void
    {
        $goal = Goal::findOrFail($goalId);
        Gate::authorize('delete', $goal);
        $goal->delete();
    }

    private function saveGoal(?int $categoryId, string $value): void
    {
        $this->error = null;

        try {
            $cents = Money::parse($value);
            if ($cents <= 0) {
                throw new InvalidArgumentException('O limite precisa ser maior que zero.');
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $goal = Goal::where('category_id', $categoryId)->first() ?? new Goal(['category_id' => $categoryId]);
        Gate::authorize($goal->exists ? 'update' : 'create', $goal->exists ? $goal : Goal::class);

        $goal->limit_cents = $cents;
        $goal->couple_id = auth()->user()->couple_id;
        $goal->save();
    }

    public function render()
    {
        $this->openMonth();

        $expenses = Entry::with('category')->where('month', $this->monthDate()->toDateString())->where('kind', Entry::SAIDA)->get();
        $byCategory = $expenses->groupBy('category_id')->map(fn ($g) => (int) $g->sum('amount_cents'));
        $goals = Goal::all()->keyBy(fn (Goal $g) => $g->category_id ?? 0);
        $totalSpent = (int) $expenses->sum('amount_cents');

        $categories = Category::forKind(Category::SAIDA)->orderBy('position')->get()->map(fn (Category $c) => [
            'category' => $c,
            'spent' => $byCategory->get($c->id, 0),
            'goal' => $goals->get($c->id),
            'level' => $goals->has($c->id) ? GoalAlert::evaluate($byCategory->get($c->id, 0), $goals->get($c->id)->limit_cents) : GoalAlert::NONE,
        ]);

        $today = CarbonImmutable::now()->startOfDay();
        $due = DueWindow::upcoming(
            Entry::where('paid', false)->where('kind', Entry::SAIDA)->whereBetween('due_on', [$today->toDateString(), $today->addDays(7)->toDateString()])->get()
                ->map(fn (Entry $e) => ['due' => $e->due_on->toDateString(), 'paid' => $e->paid, 'name' => $e->name, 'amount' => $e->amount_cents])->all(),
            $today, 7,
        );

        return view('livewire.goals.goals-page', [
            'general' => $goals->get(0),
            'generalLevel' => $goals->has(0) ? GoalAlert::evaluate($totalSpent, $goals->get(0)->limit_cents) : GoalAlert::NONE,
            'totalSpent' => $totalSpent,
            'categories' => $categories,
            'due' => $due,
        ]);
    }
}
