<?php

namespace App\Domain;

/**
 * Fechamento do mês (RF-09): entradas − saídas.
 *
 *  - cada cartão com total > 0 vira UMA saída;
 *  - o saldo líquido de cada pessoa vira entrada (se positivo) ou saída (se negativo);
 *  - o Vale fica de fora (é controlado à parte);
 *  - "pago" não muda o resultado (é só informativo).
 */
final class MonthClosing
{
    /**
     * @param  array<int, array{type: string, amount: int}>  $entries  type: 'entrada' | 'saida'; amount em centavos (> 0)
     * @param  array<int, int>  $cardTotals  total do mês de cada cartão
     * @param  array<int, int>  $personNets  saldo líquido de cada pessoa (receber − pagar)
     */
    public static function calculate(array $entries, array $cardTotals = [], array $personNets = []): ClosingResult
    {
        $in = 0;
        $out = 0;

        foreach ($entries as $entry) {
            if ($entry['type'] === 'entrada') {
                $in += $entry['amount'];
            } else {
                $out += $entry['amount'];
            }
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

        return new ClosingResult($in, $out);
    }
}
