<?php

namespace App\Domain;

/**
 * Vale (RF-17): saldo = valor do vale − gastos do mês. A sobra NÃO passa para o mês seguinte;
 * cada mês começa de novo com o valor do vale.
 */
final class ValeBalance
{
    /** @param  array<int, int>  $spends  gastos do mês, em centavos */
    public static function remaining(int $valeAmount, array $spends): int
    {
        return $valeAmount - array_sum($spends);
    }
}
