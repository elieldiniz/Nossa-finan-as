<?php

namespace App\Domain;

final class ClosingResult
{
    public readonly int $closing;

    public function __construct(public readonly int $income, public readonly int $expense)
    {
        $this->closing = $income - $expense;
    }

    /** 'positivo' | 'negativo' | 'zero' — a tela mostra sinal e rótulo, nunca só cor (UI-05). */
    public function sign(): string
    {
        return $this->closing > 0 ? 'positivo' : ($this->closing < 0 ? 'negativo' : 'zero');
    }

    public function label(): string
    {
        return match ($this->sign()) {
            'positivo' => 'Entrou mais do que saiu',
            'negativo' => 'Saiu mais do que entrou',
            default => 'Entradas e saídas iguais',
        };
    }
}
