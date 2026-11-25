<?php

namespace App\Domain;

use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Relatórios e séries do painel (RF-23, RF-24).
 */
final class ReportCalculator
{
    /**
     * Variação entre dois valores: diferença em centavos e porcentagem formatada
     * (null quando o valor anterior é zero, para não dividir por zero).
     *
     * @return array{delta: int, percent: ?string}
     */
    public static function variation(int $current, int $previous): array
    {
        $delta = $current - $previous;

        if ($previous === 0) {
            return ['delta' => $delta, 'percent' => null];
        }

        $basis = intdiv(abs($delta) * 10000 * 2 + abs($previous), abs($previous) * 2);
        $text = intdiv($basis, 100).','.str_pad((string) ($basis % 100), 2, '0', STR_PAD_LEFT).'%';
        $sign = $delta > 0 ? '+' : ($delta < 0 ? "\u{2212}" : '');

        return ['delta' => $delta, 'percent' => $sign.$text];
    }

    /**
     * Monta as 4 séries do painel.
     *
     * @param  array<string, array<string, int>>  $expensesByMonth  'YYYY-MM' => [categoria => centavos]
     * @return array{line: array<string, int>, bars: array<string, array{previous: int, current: int}>, donut: array<string, array{amount: int, percent: string}>, ranking: array<string, int>}
     */
    public static function build(array $expensesByMonth, string $currentMonth, int $months = 6): array
    {
        $line = [];
        $cursor = CarbonImmutable::createFromFormat('Y-m-d', $currentMonth.'-01')->startOfDay();

        for ($i = $months - 1; $i >= 0; $i--) {
            $key = $cursor->subMonths($i)->format('Y-m');
            $line[$key] = array_sum($expensesByMonth[$key] ?? []);
        }

        $previousKey = $cursor->subMonth()->format('Y-m');
        $current = $expensesByMonth[$currentMonth] ?? [];
        $previous = $expensesByMonth[$previousKey] ?? [];

        $bars = [];
        foreach (array_unique(array_merge(array_keys($current), array_keys($previous))) as $category) {
            $bars[$category] = ['previous' => $previous[$category] ?? 0, 'current' => $current[$category] ?? 0];
        }

        arsort($current);
        $total = array_sum($current);
        $donut = [];
        foreach ($current as $category => $amount) {
            $donut[$category] = ['amount' => $amount, 'percent' => Money::percent($amount, $total)];
        }

        return ['line' => $line, 'bars' => $bars, 'donut' => $donut, 'ranking' => $current];
    }
}
