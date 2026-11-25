<?php

namespace App\Domain;

use InvalidArgumentException;

/**
 * Acerto do casal (RF-19): quanto um deve ao outro nos gastos conjuntos.
 *
 * `$paid` diz quanto cada um pagou (['Eliel' => 20000, 'Ana' => 0]) e `$weights` a divisão em
 * porcentagem (['Eliel' => 50, 'Ana' => 50], somando 100). O resto de centavo vai para o
 * primeiro da lista, de forma sempre igual.
 */
final class Settlement
{
    /**
     * @param  array<string, int>  $paid
     * @param  array<string, int>  $weights
     */
    public static function calculate(array $paid, array $weights, int $alreadySettled = 0): SettlementResult
    {
        if (array_sum($weights) !== 100) {
            throw new InvalidArgumentException('A divisão precisa somar 100%.');
        }

        $total = array_sum($paid);
        $shares = [];
        $given = 0;

        foreach ($weights as $who => $weight) {
            $shares[$who] = intdiv($total * $weight, 100);
            $given += $shares[$who];
        }

        $rest = $total - $given;
        foreach (array_keys($shares) as $who) {
            if ($rest <= 0) {
                break;
            }
            $shares[$who]++;
            $rest--;
        }

        $balance = [];
        foreach ($shares as $who => $share) {
            $balance[$who] = ($paid[$who] ?? 0) - $share;
        }

        asort($balance);
        $debtor = array_key_first($balance);
        $creditor = array_key_last($balance);
        $amount = max(0, -$balance[$debtor] - $alreadySettled);

        return new SettlementResult($amount > 0 ? $debtor : null, $amount > 0 ? $creditor : null, $amount, $shares);
    }
}
