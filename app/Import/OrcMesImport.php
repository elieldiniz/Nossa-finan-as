<?php

namespace App\Import;

use App\Support\Money;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Lê a planilha "Orç - Mês" (CT-01). O layout segue a planilha de origem:
 *  - B/C/D  entradas e saídas (Pago, Nome, Valor), de "Entradas" até "FECHAMENTO DO MÊS";
 *  - F/G/H  cartões (nome do cartão, depois "Parcelamento/Data | Nome | Valor", até "Cartão - TOTAL");
 *  - J/K    pessoas (nome, "Pagar" e/ou "Receber", linhas com valor, "TOTAL");
 *  - M/N    vale (valor e "Gastos Pessoais").
 * Linhas cujo valor é só uma fórmula apontando para outro bloco (total de cartão, saldo de pessoa) são ignoradas,
 * porque o app calcula isso sozinho.
 */
final class OrcMesImport
{
    public const SHEET = 'Orç - Mês';

    private const MONTHS = ['janeiro' => 1, 'fevereiro' => 2, 'março' => 3, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
        'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12];

    private Worksheet $sheet;

    private int $lastRow;

    /** @var array<int, string> */
    private array $warnings = [];

    public function parse(string $path): ImportPayload
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setLoadSheetsOnly([self::SHEET]);
            $book = $reader->load($path);
        } catch (Throwable) {
            throw new InvalidImportFile('Não foi possível ler o arquivo. Use uma planilha .xlsx.');
        }

        $sheet = $book->getSheetByName(self::SHEET);
        if ($sheet === null) {
            throw new InvalidImportFile('A planilha precisa ter a aba "'.self::SHEET.'".');
        }

        $this->sheet = $sheet;
        $this->lastRow = min($sheet->getHighestDataRow(), 5000);
        $this->warnings = [];

        $entries = $this->entries();

        return new ImportPayload(
            entries: $entries,
            cards: $this->cards(),
            people: $this->people(),
            vale: $this->vale(),
            detectedMonth: $this->month(),
            warnings: $this->warnings,
        );
    }

    /** @return array<int, array{type: string, name: string, amount: int, paid: bool}> */
    private function entries(): array
    {
        $in = $this->findRow('B', fn ($t) => $t === 'entradas');
        $out = $this->findRow('B', fn ($t) => $t === 'saídas' || $t === 'saidas');
        $end = $this->findRow('B', fn ($t) => str_starts_with($t, 'fechamento'));

        if ($in === null || $out === null || $end === null) {
            throw new InvalidImportFile('Não encontrei os blocos "Entradas", "Saídas" e "FECHAMENTO DO MÊS" na aba.');
        }

        $result = [];
        foreach ([['entrada', $in + 2, $out - 1], ['saida', $out + 2, $end - 1]] as [$type, $from, $to]) {
            for ($r = $from; $r <= $to; $r++) {
                $amount = $this->money('D', $r);
                if ($amount === null || $amount <= 0 || $this->isReference('D', $r)) {
                    continue;
                }
                $result[] = ['type' => $type, 'name' => $this->text('C', $r) ?: ($type === 'entrada' ? 'Entrada' : 'Saída'), 'amount' => $amount, 'paid' => (bool) $this->raw('B', $r)];
            }
        }

        return $result;
    }

    /** @return array<int, array{name: string, lines: array<int, array<string, mixed>>}> */
    private function cards(): array
    {
        $cards = [];

        for ($r = 4; $r <= $this->lastRow; $r++) {
            $name = $this->text('F', $r);
            if ($name === '' || $this->lower($this->text('F', $r + 1)) !== 'parcelamento/data') {
                continue;
            }

            $lines = [];
            for ($k = $r + 2; $k <= $this->lastRow; $k++) {
                $label = $this->text('F', $k);
                if (str_starts_with($this->lower($label), 'cartão - total') || str_starts_with($this->lower($label), 'cartao - total')) {
                    break;
                }
                $amount = $this->money('H', $k);
                if ($amount === null || $amount <= 0) {
                    continue;
                }
                $lines[] = $this->cardLine($label, $this->text('G', $k), $amount, $k, $name);
            }

            $cards[] = ['name' => $name, 'lines' => $lines];
        }

        return $cards;
    }

    /** "FIXOS" ou vazio = fixo; "k/n" = parcelado; o resto vira avulso (com aviso). */
    private function cardLine(string $label, string $name, int $amount, int $row, string $card): array
    {
        $kind = 'fixo';
        $installment = null;

        if (preg_match('#^\s*(\d{1,3})\s*/\s*(\d{1,3})\s*$#', $label, $m) && (int) $m[2] >= (int) $m[1] && (int) $m[1] >= 1) {
            $kind = 'parcelado';
            $installment = [(int) $m[1], (int) $m[2]];
        } elseif ($label !== '' && $this->lower($label) !== 'fixos' && $this->lower($label) !== 'fixo') {
            $kind = 'avulso';
            $this->warnings[] = "Cartão {$card}, linha {$row}: não entendi \"{$label}\" em Parcelamento/Data; importei como lançamento avulso.";
        }

        return ['kind' => $kind, 'name' => $name !== '' ? $name : ($kind === 'fixo' ? 'Fixo' : 'Lançamento'), 'amount' => $amount, 'installment' => $installment];
    }

    /** @return array<int, array{name: string, debts: array<int, array<string, mixed>>}> */
    private function people(): array
    {
        $people = [];
        $current = null;
        $direction = null;

        for ($r = 4; $r <= $this->lastRow; $r++) {
            $label = $this->text('J', $r);
            $lower = $this->lower($label);

            if (in_array($lower, ['pagar', 'receber'], true)) {
                $direction = $lower;

                continue;
            }
            if ($lower === 'nome' || str_starts_with($lower, 'total')) {
                continue;
            }

            if ($label !== '' && in_array($this->lower($this->text('J', $r + 1)), ['pagar', 'receber'], true)) {
                $people[] = ['name' => $label, 'debts' => []];
                $current = array_key_last($people);
                $direction = null;

                continue;
            }

            $amount = $this->money('K', $r);
            if ($current !== null && $direction !== null && $amount !== null && $amount > 0 && ! $this->isReference('K', $r)) {
                $people[$current]['debts'][] = ['direction' => $direction, 'name' => $label, 'amount' => $amount];
            }
        }

        return $people;
    }

    /** @return ?array{amount: int, spends: array<int, array{name: string, amount: int}>} */
    private function vale(): ?array
    {
        $valeRow = $this->findRow('M', fn ($t) => $t === 'valor');
        $amount = $valeRow === null ? null : $this->money('N', $valeRow);
        if ($amount === null) {
            return null;
        }

        $spends = [];
        $start = $this->findRow('M', fn ($t) => str_starts_with($t, 'gastos pessoais'));
        for ($r = ($start ?? $this->lastRow) + 1; $r <= $this->lastRow; $r++) {
            if (str_starts_with($this->lower($this->text('M', $r)), 'total')) {
                break;
            }
            $value = $this->money('N', $r);
            if ($value !== null && $value > 0 && ! $this->isReference('N', $r)) {
                $spends[] = ['name' => $this->text('M', $r) ?: 'Gasto', 'amount' => $value];
            }
        }

        return ['amount' => $amount, 'spends' => $spends];
    }

    private function month(): ?int
    {
        if (preg_match('/financeiro de\s+(\p{L}+)/u', $this->lower($this->text('B', 2)), $m)) {
            return self::MONTHS[$m[1]] ?? null;
        }

        return null;
    }

    // ---- leitura de células -------------------------------------------------

    private function raw(string $col, int $row): mixed
    {
        $cell = $this->sheet->getCell($col.$row);

        try {
            return $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();
        } catch (Throwable) {
            return null;
        }
    }

    private function text(string $col, int $row): string
    {
        $value = $this->raw($col, $row);

        return is_scalar($value) && ! is_bool($value) ? trim((string) $value) : '';
    }

    private function money(string $col, int $row): ?int
    {
        $value = $this->raw($col, $row);

        if (is_int($value) || is_float($value)) {
            return Money::parse(number_format((float) $value, 2, ',', ''));
        }

        return null;
    }

    /** O valor é só uma fórmula do tipo "=K15" ou "=H53" (aponta para outro bloco)? */
    private function isReference(string $col, int $row): bool
    {
        $cell = $this->sheet->getCell($col.$row);

        return $cell->isFormula() && (bool) preg_match('/^=\s*\$?[A-Z]{1,2}\$?\d+\s*$/', (string) $cell->getValue());
    }

    private function findRow(string $col, callable $match): ?int
    {
        for ($r = 1; $r <= $this->lastRow; $r++) {
            if ($match($this->lower($this->text($col, $r)))) {
                return $r;
            }
        }

        return null;
    }

    private function lower(string $text): string
    {
        return mb_strtolower(trim($text));
    }
}
