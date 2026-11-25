<?php

namespace App\Domain;

final class QuickEntry
{
    public function __construct(
        public readonly string $type,
        public readonly int $amount,
        public readonly string $name,
        public readonly string $category,
    ) {}
}
