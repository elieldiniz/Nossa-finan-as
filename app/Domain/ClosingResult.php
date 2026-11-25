<?php

namespace App\Domain;

final class ClosingResult
{
    public readonly int $closing;

    /**
     * @param  int  $invested  aportes do mês: não são gasto (não entram em "closing"), mas saem do caixa
     */
    public function __construct(public readonly int $income, public readonly int $expense, public readonly int $invested = 0)
    {
        $this->closing = $income - $expense;
    }

    /** O que sobra depois de investir (fechamento − investido). */
    public function afterInvesting(): int
    {
        return $this->closing - $this->invested;
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
