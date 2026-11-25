<?php

namespace App\Domain;

/**
 * Saldo de uma pessoa (RF-16, RF-31): receber − pagar das pendências não quitadas.
 *
 * Acumula entre meses. Uma pendência deixa de contar a partir do mês seguinte ao da quitação:
 *  - sem parcelas: vale o valor todo até `settled_month`;
 *  - com parcelas: cada parcela vale até o mês em que foi paga (`paid_month`).
 *
 * Cada dívida: ['direction' => 'receber'|'pagar', 'amount' => int,
 *               'settled_month' => ?'YYYY-MM',
 *               'installments' => [['amount' => int, 'paid_month' => ?'YYYY-MM'], ...]]
 */
final class PersonBalance
{
    /** @param  array<int, array<string, mixed>>  $debts */
    public static function net(array $debts, string $month): int
    {
        $net = 0;

        foreach ($debts as $debt) {
            $outstanding = self::outstanding($debt, $month);
            $net += $debt['direction'] === 'receber' ? $outstanding : -$outstanding;
        }

        return $net;
    }

    /** @param  array<string, mixed>  $debt */
    public static function outstanding(array $debt, string $month): int
    {
        $installments = $debt['installments'] ?? [];

        if ($installments === []) {
            $settled = $debt['settled_month'] ?? null;

            return ($settled === null || $settled >= $month) ? $debt['amount'] : 0;
        }

        $sum = 0;

        foreach ($installments as $installment) {
            $paid = $installment['paid_month'] ?? null;

            if ($paid === null || $paid >= $month) {
                $sum += $installment['amount'];
            }
        }

        return $sum;
    }
}
