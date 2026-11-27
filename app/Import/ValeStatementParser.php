<?php

namespace App\Import;

use Carbon\CarbonImmutable;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Lê o extrato do cartão de vale alimentação/refeição para importar os gastos.
 *  - OFX: usa o leitor de OFX (débitos = gastos, créditos = recargas);
 *  - PDF: leitura geral por linha ("DD/MM  Estabelecimento  R$ 45,00"), que cobre o PDF exportado pelo próprio app e
 *    extratos no mesmo estilo. O resultado sempre passa por uma prévia, onde a pessoa confere o que foi lido.
 *
 * Mesmo formato do OfxParser: valor com sinal (gasto negativo, crédito positivo), mais "heading" (categoria do extrato).
 */
class ValeStatementParser
{
    /** Palavras que indicam recarga/crédito (não é gasto). */
    private const CREDIT_WORDS = ['credito', 'crédito', 'recarga', 'saldo anterior', 'estorno', 'bonificacao', 'bonificação', 'deposito', 'depósito'];

    /** Linhas de título que não são categoria. */
    private const NOT_HEADINGS = ['data', 'descrição', 'descricao', 'valor', 'subtotal', 'extrato', 'saldo', 'gastos', 'sobra', 'gerado', 'vale', 'total', 'resumo'];

    /** @return array<string, mixed> */
    public function parseOfx(string $contents): array
    {
        $ofx = (new OfxParser)->parse($contents);
        $ofx['format'] = 'ofx';

        return $ofx;
    }

    /** @return array<string, mixed> */
    public function parsePdf(string $pdfBinary): array
    {
        try {
            $text = (new Parser)->parseContent($pdfBinary)->getText();
        } catch (Throwable $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'secured') || str_contains(mb_strtolower($e->getMessage()), 'encrypt')) {
                throw new InvalidImportFile('Esse PDF está protegido por senha. Abra no leitor de PDF, salve uma cópia sem senha e envie de novo.');
            }

            throw new InvalidImportFile('Não consegui ler esse PDF. Confira se é o extrato do vale e se não está protegido por senha.');
        }

        return $this->parseText($text);
    }

    /** @return array<string, mixed> */
    public function parseText(string $text): array
    {
        $year = (int) CarbonImmutable::now()->format('Y');
        $heading = null;
        $transactions = [];
        $seen = [];

        foreach (preg_split('#\R#u', str_replace("\t", ' ', $text)) as $raw) {
            $line = trim(preg_replace('#\s+#u', ' ', $raw) ?? '');

            if ($line === '') {
                continue;
            }

            // "Novembro de 2026" muda o ano das datas sem ano
            if (preg_match('#\b(janeiro|fevereiro|mar[cç]o|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro) de (\d{4})\b#iu', $line, $y) && ! preg_match('#^\d{2}/#', $line)) {
                $year = (int) $y[2];
            }

            if (preg_match('#^(\d{2})/(\d{2})(?:/(\d{2,4}))?\s+(.+?)\s+(-?)\(?(?:R\$\s*)?(-?)([\d.]+,\d{2})\)?(-?)$#u', $line, $m)) {
                [$d, $mo] = [(int) $m[1], (int) $m[2]];
                $y = isset($m[3]) && $m[3] !== '' ? (strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3]) : $year;

                if (! checkdate($mo, $d, $y)) {
                    continue;
                }

                $name = trim(preg_replace('#\s*R\$$#u', '', $m[4]) ?? $m[4]);
                $cents = (int) round(((float) str_replace(',', '.', str_replace('.', '', $m[7]))) * 100);
                $negative = $m[5] === '-' || $m[6] === '-' || $m[8] === '-';
                $isCredit = ! $negative && $this->looksLikeCredit($name);

                if (! $negative && ! $isCredit && str_contains($line, '(')) {
                    $negative = true; // valor entre parênteses = gasto
                }

                $date = sprintf('%04d-%02d-%02d', $y, $mo, $d);
                $key = sha1($date.'|'.mb_strtolower($name).'|'.$cents);
                $seen[$key] = ($seen[$key] ?? 0) + 1;

                $transactions[] = [
                    'id' => 'vpdf:'.substr($key, 0, 24).($seen[$key] > 1 ? '#'.$seen[$key] : ''),
                    'date' => $date,
                    'amount' => $isCredit ? $cents : -$cents,
                    'memo' => $name,
                    'name' => $name,
                    'installment' => null,
                    'heading' => $heading,
                ];

                continue;
            }

            // título de categoria do extrato ("Mercado", "Lazer"): linha curta, sem números
            if (mb_strlen($line) <= 40 && ! preg_match('#\d#', $line) && ! in_array(mb_strtolower(explode(' ', $line)[0]), self::NOT_HEADINGS, true)) {
                $heading = $line;
            }
        }

        if ($transactions === []) {
            throw new InvalidImportFile('Não reconheci lançamentos nesse PDF. Ele precisa ter linhas como “05/11  Mercado  R$ 45,00”. Se o seu banco oferece OFX, use o OFX; ou me mande um exemplo desse PDF para eu ajustar.');
        }

        $dates = array_column($transactions, 'date');
        sort($dates);

        return [
            'format' => 'pdf',
            'bank' => 'Extrato do vale (PDF)',
            'account' => null,
            'start' => $dates[0],
            'end' => end($dates),
            'transactions' => $transactions,
        ];
    }

    private function looksLikeCredit(string $name): bool
    {
        $lower = mb_strtolower($name);

        foreach (self::CREDIT_WORDS as $word) {
            if (str_contains($lower, $word)) {
                return true;
            }
        }

        return false;
    }
}
