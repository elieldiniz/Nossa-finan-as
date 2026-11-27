<?php

namespace App\Livewire\Charts;

use App\Domain\ReportCalculator;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Entry;
use App\Models\ValeSpend;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Painel de gráficos (RF-23): linha (evolução), barras (mês × mês anterior por categoria),
 * rosca (por categoria) e ranking. Períodos de 3, 6 e 12 meses.
 */
#[Layout('components.layouts.app')]
#[Title('Gráficos · Nossa Finanças')]
class ChartsPage extends Component
{
    use WithMonthView;

    #[Url(as: 'p')]
    public int $period = 6;

    public function setPeriod(int $months): void
    {
        $this->period = in_array($months, [3, 6, 12], true) ? $months : 6;
    }

    /** @return array{line: array, bars: array, donut: array, ranking: array} */
    public function series(): array
    {
        $end = $this->monthDate();
        $start = $end->subMonths(max($this->period, 2) - 1);
        $context = $this->context();

        $byMonth = Entry::with('category')->where('kind', Entry::SAIDA)
            ->whereBetween('month', [$start->toDateString(), $end->toDateString()])->get()
            ->filter(fn (Entry $e) => $context->applies($e->owner_id))
            ->groupBy(fn (Entry $e) => $e->month->format('Y-m'))
            ->map(fn ($entries) => $entries->groupBy(fn (Entry $e) => $e->category?->name ?? 'Sem categoria')
                ->map(fn ($g) => (int) $g->sum('amount_cents'))->all())
            ->all();

        $valeSpends = ValeSpend::query()->with('category')->whereBetween('month', [$start->toDateString(), $end->toDateString()])->get()
            ->filter(fn (ValeSpend $x) => $context->applies($x->owner_id));
        foreach ($valeSpends as $spend) { // o gasto do vale entra na categoria que a pessoa escolheu
            $key = $spend->month->format('Y-m');
            $name = $spend->category?->name ?? 'Vale alimentação';
            $byMonth[$key][$name] = ($byMonth[$key][$name] ?? 0) + $spend->amount_cents;
        }

        return ReportCalculator::build($byMonth, $end->format('Y-m'), $this->period);
    }

    public function render()
    {
        $series = $this->series();

        return view('livewire.charts.charts-page', ['series' => $series, 'current' => end($series['line']) ?: 0]);
    }
}
