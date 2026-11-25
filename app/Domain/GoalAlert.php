<?php

namespace App\Domain;

/**
 * Avisos de meta (RF-20), só com inteiros: nenhum, 80% ou 100%.
 */
final class GoalAlert
{
    public const NONE = 'nenhum';

    public const WARN_80 = '80';

    public const REACHED_100 = '100';

    public static function evaluate(int $spent, int $limit): string
    {
        if ($limit <= 0) {
            return self::NONE;
        }

        if ($spent * 100 >= $limit * 100) {
            return self::REACHED_100;
        }

        if ($spent * 100 >= $limit * 80) {
            return self::WARN_80;
        }

        return self::NONE;
    }
}
