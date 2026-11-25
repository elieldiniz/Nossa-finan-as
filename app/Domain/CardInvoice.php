<?php

namespace App\Domain;

/**
 * Fatura de cartão (RF-14, RF-15).
 */
final class CardInvoice
{
    /** Soma dos lançamentos do cartão no mês, em centavos. */
    public static function total(array $amounts): int
    {
        return array_sum($amounts);
    }

    /** O cartão só vira saída do mês quando o total é maior que zero. */
    public static function asExpense(int $total): ?int
    {
        return $total > 0 ? $total : null;
    }

    /**
     * Fatura prevista de um mês futuro: soma dos fixos e parcelas que ocorrem nele.
     *
     * @param  array<int, array{schedule: RecurrenceSchedule, amount: int}>  $recurring
     */
    public static function forecast(array $recurring, int $year, int $month): int
    {
        $total = 0;

        foreach ($recurring as $item) {
            if ($item['schedule']->occursIn($year, $month)) {
                $total += $item['amount'];
            }
        }

        return $total;
    }
}
