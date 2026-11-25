<?php

namespace App\Domain;

final class SettlementResult
{
    /** @param  array<string, int>  $shares  parte de cada um no total */
    public function __construct(
        public readonly ?string $debtor,
        public readonly ?string $creditor,
        public readonly int $amount,
        public readonly array $shares,
    ) {}

    public function isSettled(): bool
    {
        return $this->amount === 0;
    }
}
