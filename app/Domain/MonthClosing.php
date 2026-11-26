<?php

namespace App\Domain;

/**
 * Fechamento do mês (RF-09): entradas − saídas.
 *
 *  - cada cartão com total > 0 vira UMA saída;
 *  - o saldo líquido de cada pessoa vira entrada (se positivo) ou saída (se negativo);
 *  - o Vale entra no saldo: o valor do vale é entrada e o que já foi gasto dele é saída (o restante segue como sobra);
 *  - investimento ("aporte") não é gasto: fica em "invested" e não muda o fechamento;
 *  - "pago" não muda o resultado (é só informativo).
 */
final class MonthClosing
{
    /**
     * @param  array<int, array{type: string, amount: int}>  $entries  type: 'entrada' | 'saida'; amount em centavos (> 0)
     * @param  array<int, int>  $cardTotals  total do mês de cada cartão
     * @param  array<int, int>  $personNets  saldo líquido de cada pessoa (receber − pagar)
     * @param  int  $valeAmount  valor do vale no mês (entrada)
     * @param  int  $valeSpent  quanto já foi gasto do vale no mês (saída)
     */
    public static function calculate(array $entries, array $cardTotals = [], array $personNets = [], int $valeAmount = 0, int $valeSpent = 0): ClosingResult
    {
        $in = 0;
        $out = 0;
        $invested = 0;

        foreach ($entries as $entry) {
            match ($entry['type']) {
                'entrada' => $in += $entry['amount'],
                'aporte' => $invested += $entry['amount'],
                default => $out += $entry['amount'],
            };
        }

        foreach ($cardTotals as $total) {
            if ($total > 0) {
                $out += $total;
            }
        }

        foreach ($personNets as $net) {
            if ($net > 0) {
                $in += $net;
            } elseif ($net < 0) {
                $out += -$net;
            }
        }

        $in += max($valeAmount, 0);
        $out += max($valeSpent, 0);

        return new ClosingResult($in, $out, $invested);
    }
}
