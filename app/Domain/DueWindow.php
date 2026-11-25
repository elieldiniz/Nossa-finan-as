<?php

namespace App\Domain;

use Carbon\CarbonImmutable;

/**
 * Janela de vencimentos (RF-21): itens NÃO pagos que vencem de hoje até hoje + N dias.
 */
final class DueWindow
{
    /**
     * @param  array<int, array{due: string|CarbonImmutable, paid: bool}>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function upcoming(array $items, CarbonImmutable $today, int $days = 7): array
    {
        $start = $today->startOfDay();
        $end = $start->addDays($days);

        return array_values(array_filter($items, function (array $item) use ($start, $end) {
            if ($item['paid']) {
                return false;
            }

            $due = CarbonImmutable::parse($item['due'])->startOfDay();

            return $due->greaterThanOrEqualTo($start) && $due->lessThanOrEqualTo($end);
        }));
    }
}
