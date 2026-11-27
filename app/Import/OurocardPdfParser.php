<?php

namespace App\Import;

use Carbon\CarbonImmutable;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Lê a fatura em PDF do Ourocard (Banco do Brasil). Devolve o mesmo formato do OfxParser (compra = valor negativo,
 * pagamento/crédito = positivo) e, a mais, o que só o PDF traz: vencimento, fechamento, limite, total da fatura e
 * o titular de cada compra (cartão principal e adicionais).
 *
 * O PDF é só texto: cada lançamento termina em "DD/MM <país> R$ valor" e as compras vêm agrupadas por titular
 * ("01- NOME   Cartao N. 7424") e por categoria do banco ("Compras diversas", "Hospitais"...).
 */
class OurocardPdfParser
{
    /** Categorias do banco que aparecem como título antes dos lançamentos. */
    private const HEADINGS = ['Compras/Pgto Contas Parc', 'Compras diversas', 'Pagamentos', 'Hospitais', 'Restaurantes', 'Supermercados', 'Farmácias', 'Farmacias',
        'Combustíveis', 'Combustiveis', 'Vestuário', 'Vestuario', 'Viagens', 'Hotéis', 'Hoteis', 'Transportes', 'Educação', 'Educacao', 'Lazer', 'Serviços', 'Servicos',
        'Estornos', 'Tarifas', 'Seguros', 'Assinaturas', 'Entretenimento', 'Postos de combustível'];

    /** @return array<string, mixed> mesmo formato do OfxParser, mais due_date, closing_date, limit, total, holders */
    public function parse(string $pdfBinary): array
    {
        try {
            $text = (new Parser)->parseContent($pdfBinary)->getText();
        } catch (Throwable $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'secured') || str_contains(mb_strtolower($e->getMessage()), 'encrypt')) {
                throw new InvalidImportFile('Esse PDF está protegido por senha. Abra no leitor de PDF, salve uma cópia sem senha e envie de novo.');
            }

            throw new InvalidImportFile('Não consegui ler esse PDF. Confira se é a fatura do cartão e se não está protegido por senha.');
        }

        return $this->parseText($text);
    }

    /** @return array<string, mixed> */
    public function parseText(string $text): array
    {
        $text = str_replace("\r", '', $text);

        if (! preg_match('#Cartao N\.|Cartão N\.#iu', $text) || ! preg_match('#Lan[cç]amentos nesta fatura#iu', $text)) {
            throw new InvalidImportFile('Esse PDF não parece uma fatura do Ourocard (Banco do Brasil).');
        }

        $closing = $this->date($text, '#Fatura fechada em.*?(\d{2}/\d{2}/\d{4})#su');
        $due = $this->date($text, '#Vencimento\s*(\d{2}/\d{2}/\d{4})#u');

        if ($closing === null) {
            throw new InvalidImportFile('Não encontrei a data de fechamento da fatura no PDF.');
        }

        // só a parte dos lançamentos desta fatura (antes de "Parcelamentos Próxima Fatura")
        $start = preg_match('#(?:\d{2}-\s*[^\n]*?)?Cartao N\.#iu', $text, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : 0;
        $end = preg_match('#Parcelamentos Pr[oó]xima Fatura#iu', $text, $e, PREG_OFFSET_CAPTURE) ? $e[0][1] : strlen($text);
        $body = substr($text, $start, $end - $start);

        // cabeçalhos de página e linhas de subtotal/total viram ruído: guarda os subtotais para conferir
        $body = preg_replace('#Data\s*Descri[cç][aã]o\s+Pa[ií]s\s+Valor#iu', ' ', $body);
        $body = preg_replace('#\s*P[aá]gina\s+\d+/\d+#iu', ' ', $body);
        $holders = [];
        $body = preg_replace_callback('#(Subtotal|Total)\s*\tR\$\s*([\d.]+,\d{2})\s*US\$\s*[\d.]+,\d{2}#iu', function ($m) use (&$holders) {
            if (strcasecmp($m[1], 'Subtotal') === 0) {
                $holders[] = ['subtotal' => $this->cents($m[2])];
            }

            return ' ';
        }, $body);

        $transactions = [];
        $warnings = [];
        $holder = ['card' => null, 'name' => null];
        $heading = null;
        $seen = [];
        $cursor = 0;

        preg_match_all('#(?P<d>\d{2}/\d{2})\t(?P<c>[A-Z]{2}|\d{1,3})\t(?P<cur>R\$|US\$)\s*(?P<v>[\d.]+,\d{2})(?P<neg>-)?#u', $body, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $recordStart = $match[0][1];
            $piece = substr($body, $cursor, $recordStart - $cursor);
            $cursor = $recordStart + strlen($match[0][0]);

            // um título de titular antes da descrição: "02- NOME   Cartao N. 6733"
            while (preg_match('#(\d{2})-\s*(.+?)\s+Cart[aã]o?\s*N\.\s*(\d{4})#iu', $piece, $h, PREG_OFFSET_CAPTURE)) {
                $holder = ['card' => $h[3][0], 'name' => trim($h[2][0])];
                $piece = substr($piece, 0, $h[0][1]).' '.substr($piece, $h[0][1] + strlen($h[0][0]));
                $heading = null;
            }

            [$piece, $heading] = $this->stripHeadings($piece, $heading);
            $description = trim(preg_replace('#\s+#', ' ', preg_replace('#\s{2,}\S.*$#u', '', trim($piece))) ?? '');
            $raw = trim($piece);

            if ($description === '') {
                continue;
            }

            $installment = null;
            if (preg_match('#\bPARC\s+(\d{1,3})/(\d{1,3})\b#i', $description, $p) && (int) $p[2] >= (int) $p[1] && (int) $p[1] >= 1) {
                $installment = [(int) $p[1], (int) $p[2]];
                $description = trim(substr($description, 0, strpos($description, $p[0])));
            }

            // "RD FARMA   JI PARANA" (cidade separada por espaços) → "RD FARMA"
            $name = trim(preg_split('#\s{2,}#u', $raw)[0] ?? $description);
            $name = trim(preg_replace('#\s+PARC\s+\d+/\d+.*$#i', '', $name) ?? $name);
            if ($name === '' || mb_strlen($name) > mb_strlen($description) + 3) {
                $name = $description;
            }

            $amount = $this->cents($match['v'][0]);
            $credit = ($match['neg'][0] ?? '') === '-';

            if ($match['cur'][0] === 'US$') {
                $warnings[] = "“{$name}” é uma compra em dólar e não foi importada (confira na fatura).";

                continue;
            }

            $date = $this->purchaseDate($match['d'][0], $closing);
            $key = sha1(implode('|', [$holder['card'], $match['d'][0], mb_strtolower($name), $amount, $installment ? $installment[0].'/'.$installment[1] : '']));
            $seen[$key] = ($seen[$key] ?? 0) + 1;

            $transactions[] = [
                'id' => 'bb:'.substr($key, 0, 24).($seen[$key] > 1 ? '#'.$seen[$key] : ''),
                'date' => $date,
                'amount' => $credit ? $amount : -$amount,
                'memo' => $name,
                'name' => $name,
                'installment' => $installment,
                'holder' => $holder['card'],
                'holder_name' => $holder['name'],
                'heading' => $heading,
            ];
        }

        if ($transactions === []) {
            throw new InvalidImportFile('Não encontrei nenhum lançamento nesse PDF.');
        }

        // subtotal de cada titular (na mesma ordem em que aparecem)
        foreach (array_values(array_unique(array_filter(array_column($transactions, 'holder')))) as $i => $card) {
            $holders[$i]['card'] = $card;
            $holders[$i]['name'] = collect($transactions)->firstWhere('holder', $card)['holder_name'] ?? null;
        }

        return [
            'bank' => 'Ourocard (Banco do Brasil)',
            'account' => $this->first($text, '#Final\s+(\d{4})#u'),
            'start' => null,
            'end' => $closing->toDateString(),
            'closing_date' => $closing->toDateString(),
            'due_date' => $due?->toDateString(),
            'limit' => ($l = $this->first($text, '#Limite\s+[úu]nico\s*R\$\s*([\d.]+,\d{2})#iu')) ? $this->cents($l) : null,
            'total' => ($t = $this->first($text, '#Total\s*\tR\$\s*([\d.]+,\d{2})#u')) ? $this->cents($t) : null,
            'holders' => array_values(array_filter($holders, fn ($h) => isset($h['card']))),
            'warnings' => $warnings,
            'transactions' => $transactions,
        ];
    }

    /** Tira os títulos de categoria do banco do começo do trecho; guarda o último. @return array{0: string, 1: ?string} */
    private function stripHeadings(string $piece, ?string $heading): array
    {
        $alt = implode('|', array_map(fn ($h) => preg_quote($h, '#'), self::HEADINGS));

        while (preg_match('#^\s*('.$alt.')\b\s*#iu', $piece, $m)) {
            $piece = substr($piece, strlen($m[0]));
            if (mb_strtolower($m[1]) !== 'compras/pgto contas parc' && mb_strtolower($m[1]) !== 'compras diversas' && mb_strtolower($m[1]) !== 'pagamentos') {
                $heading = $m[1];
            } elseif (mb_strtolower($m[1]) === 'compras/pgto contas parc') {
                $heading = null;
            }
        }

        return [$piece, $heading];
    }

    /** "28/10" com fechamento em 25/11/2026 → 2026-10-28; mês depois do fechamento é do ano anterior. */
    private function purchaseDate(string $ddmm, CarbonImmutable $closing): string
    {
        [$d, $m] = array_map('intval', explode('/', $ddmm));
        $year = $m > $closing->month ? $closing->year - 1 : $closing->year;

        return checkdate($m, $d, $year) ? sprintf('%04d-%02d-%02d', $year, $m, $d) : $closing->toDateString();
    }

    private function date(string $text, string $pattern): ?CarbonImmutable
    {
        $raw = $this->first($text, $pattern);

        return $raw ? CarbonImmutable::createFromFormat('!d/m/Y', $raw) : null;
    }

    private function first(string $text, string $pattern): ?string
    {
        return preg_match($pattern, $text, $m) ? trim($m[1]) : null;
    }

    private function cents(string $brl): int
    {
        return (int) round(((float) str_replace(',', '.', str_replace('.', '', $brl))) * 100);
    }
}
