<?php

namespace App\Livewire\Reports;

use App\Domain\ReportCalculator;
use App\Livewire\Concerns\WithMonthView;
use App\Models\Entry;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Relatório por categoria com comparação ao mês anterior, em valor e % (RF-24).
 */
#[Layout('components.layouts.app')]
#[Title('Relatório · Nossa Finanças')]
class ReportPage extends Component
{
    use WithMonthView;

    public function render()
    {
        $context = $this->context();
        $month = $this->monthDate();

        $totals = fn ($date) => Entry::with('category')->where('kind', Entry::SAIDA)->where('month', $date->toDateString())->get()
            ->filter(fn (Entry $e) => $context->applies($e->owner_id))
            ->groupBy(fn (Entry $e) => $e->category?->name ?? 'Sem categoria')
            ->map(fn ($g) => (int) $g->sum('amount_cents'));

        $current = $totals($month);
        $previous = $totals($month->subMonth());

        $rows = $current->keys()->merge($previous->keys())->unique()->map(fn ($name) => [
            'name' => $name,
            'current' => $current->get($name, 0),
            'previous' => $previous->get($name, 0),
        ] + ['variation' => ReportCalculator::variation($current->get($name, 0), $previous->get($name, 0))])
            ->sortByDesc('current')->values();

        return view('livewire.reports.report-page', [
            'rows' => $rows,
            'totalCurrent' => (int) $current->sum(),
            'totalPrevious' => (int) $previous->sum(),
            'totalVariation' => ReportCalculator::variation((int) $current->sum(), (int) $previous->sum()),
        ]);
    }
}
