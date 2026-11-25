<?php

namespace App\Import;

use App\Domain\ClosingResult;
use App\Domain\MonthClosing;

/**
 * O que foi lido da planilha "Orç - Mês", tudo em centavos. Serializável para a prévia (Livewire).
 */
final class ImportPayload
{
    /**
     * @param  array<int, array{type: string, name: string, amount: int, paid: bool}>  $entries
     * @param  array<int, array{name: string, lines: array<int, array{kind: string, name: string, amount: int, installment: ?array{0: int, 1: int}}>}>  $cards
     * @param  array<int, array{name: string, debts: array<int, array{direction: string, name: string, amount: int}>}>  $people
     * @param  ?array{amount: int, spends: array<int, array{name: string, amount: int}>}  $vale
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public readonly array $entries = [],
        public readonly array $cards = [],
        public readonly array $people = [],
        public readonly ?array $vale = null,
        public readonly ?int $detectedMonth = null,
        public readonly array $warnings = [],
    ) {}

    public function cardTotal(array $card): int
    {
        return array_sum(array_column($card['lines'], 'amount'));
    }

    public function personNet(array $person): int
    {
        $net = 0;
        foreach ($person['debts'] as $debt) {
            $net += $debt['direction'] === 'receber' ? $debt['amount'] : -$debt['amount'];
        }

        return $net;
    }

    /** Fechamento do mês importado (mesma conta do app), para conferir com a planilha. */
    public function closing(): ClosingResult
    {
        return MonthClosing::calculate(
            array_map(fn ($e) => ['type' => $e['type'], 'amount' => $e['amount']], $this->entries),
            array_map(fn ($c) => $this->cardTotal($c), $this->cards),
            array_map(fn ($p) => $this->personNet($p), $this->people),
        );
    }

    /**
     * Itens que têm dono (trocável na prévia). A chave é estável: e0, c0l1, p0d2, v0 (gasto do vale).
     *
     * @return array<string, array{section: string, label: string, amount: int}>
     */
    public function items(): array
    {
        $items = [];
        foreach ($this->entries as $i => $e) {
            $items["e{$i}"] = ['section' => $e['type'] === 'entrada' ? 'Entradas' : 'Saídas', 'label' => $e['name'], 'amount' => $e['amount']];
        }
        foreach ($this->cards as $ci => $card) {
            foreach ($card['lines'] as $li => $line) {
                $items["c{$ci}l{$li}"] = ['section' => 'Cartão '.$card['name'], 'label' => $line['name'], 'amount' => $line['amount']];
            }
        }
        foreach ($this->people as $pi => $person) {
            foreach ($person['debts'] as $di => $debt) {
                $items["p{$pi}d{$di}"] = ['section' => $person['name'], 'label' => ($debt['direction'] === 'pagar' ? 'Pagar' : 'Receber').($debt['name'] !== '' ? ' · '.$debt['name'] : ''), 'amount' => $debt['amount']];
            }
        }
        foreach ($this->vale['spends'] ?? [] as $si => $spend) {
            $items["v{$si}"] = ['section' => 'Vale', 'label' => $spend['name'], 'amount' => $spend['amount']];
        }

        return $items;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $d): self
    {
        return new self($d['entries'] ?? [], $d['cards'] ?? [], $d['people'] ?? [], $d['vale'] ?? null, $d['detectedMonth'] ?? null, $d['warnings'] ?? []);
    }
}
