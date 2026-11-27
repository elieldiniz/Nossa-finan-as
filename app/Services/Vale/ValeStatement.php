<?php

namespace App\Services\Vale;

use App\Domain\ValeBalance;
use App\Models\Vale;
use App\Models\ValeSpend;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;

/**
 * Monta o extrato do Vale alimentação (um mês ou todos os meses) para exportar em PDF e OFX.
 * O valor do vale entra como crédito todo mês e os gastos como débitos; a sobra NÃO passa para o mês seguinte.
 */
class ValeStatement
{
    /**
     * @return array{
     *   title: string, period: string, generated_at: string, start: string, end: string,
     *   vales: array<int, array{id: int, owner: string, amount: int, spent: int, remaining: int, months: array<int, array<string, mixed>>}>
     * }
     */
    public function build(ViewContext $context, ?CarbonImmutable $month): array
    {
        $vales = Vale::with('owner')->orderBy('id')->get()->filter(fn (Vale $v) => $context->applies($v->owner_id))->values();
        $allSpends = ValeSpend::with('category')->whereIn('vale_id', $vales->pluck('id'))->orderBy('month')->orderBy('id')->get()->groupBy('vale_id');

        $now = CarbonImmutable::now()->startOfMonth();
        $out = [];
        $starts = [];
        $ends = [];

        foreach ($vales as $vale) {
            $spendsOfVale = $allSpends->get($vale->id, collect());
            [$from, $to] = $month
                ? [$month->startOfMonth(), $month->startOfMonth()]
                : [$this->firstMonth($vale, $spendsOfVale), $this->lastMonth($now, $spendsOfVale)];

            $months = [];
            $total = 0;

            for ($m = $from; $m->lessThanOrEqualTo($to); $m = $m->addMonth()) {
                $rows = $spendsOfVale->filter(fn (ValeSpend $s) => $s->month->format('Y-m') === $m->format('Y-m'))
                    ->map(fn (ValeSpend $s) => ['id' => $s->id, 'date' => $s->statementDate()->toDateString(), 'name' => $s->name, 'category' => $s->category?->name ?? 'Sem categoria', 'amount' => $s->amount_cents])
                    ->sortBy([['date', 'asc'], ['id', 'asc']])->values()->all();
                $spent = array_sum(array_column($rows, 'amount'));
                $total += $spent;

                $months[] = [
                    'month' => $m->format('Y-m'),
                    'label' => ucfirst($m->translatedFormat('F \d\e Y')),
                    'credit' => $vale->amount_cents,
                    'credit_date' => $m->toDateString(),
                    'spends' => $rows,
                    'groups' => $this->groups($rows),
                    'spent' => $spent,
                    'remaining' => ValeBalance::remaining($vale->amount_cents, array_column($rows, 'amount')),
                ];
            }

            $starts[] = $from;
            $ends[] = $to->endOfMonth();
            $out[] = [
                'id' => $vale->id, 'owner' => $vale->owner?->name ?? 'Vale', 'amount' => $vale->amount_cents,
                'spent' => $total, 'remaining' => end($months) ? end($months)['remaining'] : $vale->amount_cents, 'months' => $months,
            ];
        }

        $start = $starts ? min($starts) : ($month ?? $now)->startOfMonth();
        $end = $ends ? max($ends) : ($month ?? $now)->endOfMonth();

        return [
            'title' => 'Vale alimentação',
            'period' => $month ? ucfirst($month->translatedFormat('F \d\e Y')) : 'Todos os meses',
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'vales' => $out,
        ];
    }

    /** @param  array<int, array<string, mixed>>  $rows  @return array<int, array{category: string, rows: array<int, array<string, mixed>>, subtotal: int}> */
    private function groups(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['category']]['category'] = $row['category'];
            $groups[$row['category']]['rows'][] = $row;
            $groups[$row['category']]['subtotal'] = ($groups[$row['category']]['subtotal'] ?? 0) + $row['amount'];
        }

        return array_values($groups);
    }

    private function firstMonth(Vale $vale, $spends): CarbonImmutable
    {
        $first = $spends->first()?->month;
        $created = $vale->created_at?->toImmutable()->startOfMonth();

        return collect([$first?->toImmutable()->startOfMonth(), $created])->filter()->min() ?? CarbonImmutable::now()->startOfMonth();
    }

    private function lastMonth(CarbonImmutable $now, $spends): CarbonImmutable
    {
        $last = $spends->last()?->month?->toImmutable()->startOfMonth();

        return $last && $last->greaterThan($now) ? $last : $now;
    }
}
