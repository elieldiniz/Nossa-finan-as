<?php

namespace App\Import;

use App\Support\Money;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
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

    /**
     * Abas que parecem a planilha "Orç - Mês": primeiro as que têm esse nome ("Orç - Mês", "Orç - Mês Outubro"…);
     * se não houver nenhuma, as que têm a estrutura (blocos "Entradas", "Saídas" e "FECHAMENTO DO MÊS"), seja qual for o nome da aba.
     *
     * @return array<int, string>
     */
    public function sheetNames(string $path): array
    {
        try {
            $names = IOFactory::createReaderForFile($path)->listWorksheetNames($path);
        } catch (Throwable) {
            throw new InvalidImportFile('Não foi possível ler o arquivo. Use uma planilha .xlsx.');
        }

        $byName = array_values(array_filter($names, fn (string $n) => str_starts_with($this->lower(trim($n)), $this->lower(self::SHEET))));

        if ($byName !== []) {
            return $byName;
        }

        $this->allSheetNames = $names;

        return array_values(array_filter($names, fn (string $n) => $this->looksLikeOrcMes($path, $n)));
    }

    /** @var array<int, string> abas do arquivo (usado só na mensagem de erro) */
    private array $allSheetNames = [];

    private function looksLikeOrcMes(string $path, string $name): bool
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setLoadSheetsOnly([$name]);
            $this->sheet = $reader->load($path)->getSheetByName($name);
            $this->lastRow = min($this->sheet->getHighestDataRow(), 5000);

            return $this->findRow('B', fn ($t) => $t === 'entradas') !== null
                && $this->findRow('B', fn ($t) => str_starts_with($t, 'fechamento')) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param  string|null  $sheetName  aba a ler; sem ela, a primeira aba "Orç - Mês…" do arquivo */
    public function parse(string $path, ?string $sheetName = null): ImportPayload
    {
        $names = $this->sheetNames($path);

        if ($names === []) {
            $found = $this->allSheetNames === [] ? '' : ' Abas encontradas no arquivo: '.implode(', ', array_map(fn ($n) => '"'.$n.'"', $this->allSheetNames)).'.';

            throw new InvalidImportFile('Não reconheci a planilha: preciso da aba "'.self::SHEET.'" (ou "'.self::SHEET.' Outubro", etc.), com os blocos "Entradas", "Saídas" e "FECHAMENTO DO MÊS".'.$found);
        }

        $sheetName ??= $names[0];

        if (! in_array($sheetName, $names, true)) {
            throw new InvalidImportFile('Não encontrei a aba "'.$sheetName.'" na planilha.');
        }

        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setLoadSheetsOnly([$sheetName]);
            $book = $reader->load($path);
        } catch (Throwable) {
            throw new InvalidImportFile('Não foi possível ler o arquivo. Use uma planilha .xlsx.');
        }

        $sheet = $book->getSheetByName($sheetName);
        if ($sheet === null) {
            throw new InvalidImportFile('Não encontrei a aba "'.$sheetName.'" na planilha.');
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

    /** Textos da coluna F que NÃO são o nome de um cartão. */
    private const NOT_A_CARD = ['total', 'cartão - total', 'cartao - total', 'recorrente', 'assinaturas', 'assinatura', 'compras a vista', 'compras à vista',
        'parcelamento/data', 'cartões', 'cartoes', 'fixos', 'fixo'];

    /**
     * Cartões: o nome fica na coluna F com G e H vazios (com ou sem a linha "Parcelamento/Data" logo abaixo);
     * as compras vêm nas linhas seguintes até o próximo cartão ou "Cartão - TOTAL". Linhas "Total" são só somas.
     *
     * @return array<int, array{name: string, lines: array<int, array<string, mixed>>}>
     */
    private function cards(): array
    {
        $cards = [];
        $current = null;
        $section = null; // 'recorrente' depois de um título "Assinaturas"/"Recorrente" sem valor; 'avista' depois de "Compras a vista"
        $group = false;  // true depois de uma linha "FIXOS": as linhas sem marcação logo abaixo também são fixas

        for ($r = 4; $r <= $this->lastRow; $r++) {
            $label = $this->text('F', $r);
            $lower = $this->lower($label);
            $name = $this->text('G', $r);
            $amount = $this->money('H', $r);
            $date = $this->dateAt('F', $r);

            if (str_starts_with($lower, 'cartão - total') || str_starts_with($lower, 'cartao - total')) {
                $current = null; // fim deste cartão (alguns arquivos têm um só "Cartão - TOTAL" no fim, outros um por cartão)
                $section = null;
                $group = false;

                continue;
            }

            // nome de cartão: texto em F, sem nome nem valor ao lado
            if ($date === null && $label !== '' && $name === '' && ($amount === null || $amount <= 0) && ! in_array($lower, self::NOT_A_CARD, true)) {
                [$cardName, $dueDay] = $this->splitCardName($label);
                $cards[] = ['name' => $cardName, 'due_day' => $dueDay, 'lines' => []];
                $current = array_key_last($cards);
                $section = null;
                $group = false;

                continue;
            }

            if ($current === null) {
                continue;
            }

            // títulos de seção sem valor
            if ($name === '' && ($amount === null || $amount <= 0)) {
                $group = false;
                $section = match (true) {
                    in_array($lower, ['assinaturas', 'assinatura', 'recorrente', 'fixos', 'fixo'], true) => 'recorrente',
                    str_starts_with($lower, 'compras a vista'), str_starts_with($lower, 'compras à vista') => 'avista',
                    str_starts_with($lower, 'total') => null,
                    default => $section,
                };

                continue;
            }

            if ($amount === null || $amount <= 0 || str_starts_with($lower, 'total')) {
                continue;
            }

            if ($label !== '' || $date !== null) {
                $group = in_array($lower, ['fixos', 'fixo'], true); // só "FIXOS" vale para as linhas de baixo
            }

            $cards[$current]['lines'][] = $this->cardLine($label, $date, $name, $amount, $r, $cards[$current]['name'], $section, $group);
        }

        return $cards;
    }

    /**
     * "FIXOS", "Recorrente", "Assinaturas" ou vazio = mensal; "k/n" = parcelado; uma DATA = última parcela (mensal até aquele mês);
     * "Compras a vista" = só neste mês; o resto vira avulso (com aviso).
     */
    private function cardLine(string $label, ?CarbonImmutable $date, string $name, int $amount, int $row, string $card, ?string $section = null, bool $group = false): array
    {
        $kind = 'fixo';
        $installment = null;
        $endsOn = null;
        $lower = $this->lower($label);

        if ($date !== null) {
            $endsOn = $date->toDateString();
        } elseif (preg_match('#^\s*(\d{1,3})\s*/\s*(\d{1,3})\s*$#', $label, $m) && (int) $m[2] >= (int) $m[1] && (int) $m[1] >= 1) {
            $kind = 'parcelado';
            $installment = [(int) $m[1], (int) $m[2]];
        } elseif ($label !== '' && ! in_array($lower, ['fixos', 'fixo', 'recorrente', 'assinaturas', 'assinatura'], true)) {
            $kind = 'avulso';
            $this->warnings[] = "Cartão {$card}, linha {$row}: não entendi \"{$label}\" em Parcelamento/Data; importei como lançamento avulso.";
        } elseif ($label === '' && ! $group && $section !== 'recorrente') {
            $kind = 'avulso'; // sem marcação e fora de um grupo FIXOS/Recorrente: compra só deste mês
        }

        return ['kind' => $kind, 'name' => $name !== '' ? $name : ($kind === 'fixo' ? 'Fixo' : 'Lançamento'), 'amount' => $amount, 'installment' => $installment, 'ends_on' => $endsOn];
    }

    /** "Sicoob - vence dia 3" → ['Sicoob', 3]; sem a parte do vencimento → [nome, null]. */
    private function splitCardName(string $label): array
    {
        if (preg_match('#^(.*?)\s*[-–—·(]*\s*vence\s*(?:dia)?\s*(\d{1,2})\s*\)?\s*$#iu', $label, $m) && trim($m[1]) !== '' && (int) $m[2] >= 1 && (int) $m[2] <= 31) {
            return [trim($m[1], ' -–—·('), (int) $m[2]];
        }

        return [$label, null];
    }

    /** Data na célula (data de verdade ou número de série do Excel); null se não for data. */
    private function dateAt(string $col, int $row): ?CarbonImmutable
    {
        $cell = $this->sheet->getCell($col.$row);
        $value = $this->raw($col, $row);

        try {
            if ($value instanceof \DateTimeInterface) {
                return CarbonImmutable::instance($value);
            }

            if (is_numeric($value) && ExcelDate::isDateTime($cell) && (float) $value > 30000) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value));
            }
        } catch (Throwable) {
            return null;
        }

        return null;
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
            $value = $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();

            // texto com formatação parcial (negrito em parte do texto etc.) vem como RichText: usa o texto puro
            return $value instanceof RichText ? $value->getPlainText() : $value;
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
