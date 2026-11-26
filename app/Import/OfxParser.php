<?php

namespace App\Import;

use Carbon\CarbonImmutable;

/**
 * Lê um extrato/fatura em OFX (formato SGML dos bancos, como o da Nubank).
 * Devolve só o necessário: período, banco e as transações (data, valor em centavos com sinal, descrição, identificador).
 */
final class OfxParser
{
    /**
     * @return array{bank: ?string, account: ?string, start: ?string, end: ?string, transactions: array<int, array{id: string, date: string, amount: int, memo: string, name: string, installment: ?array{0: int, 1: int}}>}
     */
    public function parse(string $contents): array
    {
        if (! str_contains(strtoupper($contents), '<OFX>')) {
            throw new InvalidImportFile('Esse arquivo não parece um extrato OFX.');
        }

        // alguns bancos declaram CP1252 mas gravam UTF-8: só converte se não for UTF-8 válido
        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        preg_match_all('#<STMTTRN>(.*?)(?:</STMTTRN>|(?=<STMTTRN>)|</BANKTRANLIST>)#s', $contents, $blocks);

        $transactions = [];
        foreach ($blocks[1] as $i => $block) {
            $date = $this->date($this->tag($block, 'DTPOSTED'));
            $amount = $this->amount($this->tag($block, 'TRNAMT'));

            if ($date === null || $amount === null || $amount === 0) {
                continue;
            }

            $memo = trim(html_entity_decode($this->tag($block, 'MEMO') ?? $this->tag($block, 'NAME') ?? '', ENT_QUOTES | ENT_HTML5));
            $installment = null;

            if (preg_match('#\s*-?\s*parcela\s+(\d{1,3})\s*/\s*(\d{1,3})\s*$#iu', $memo, $m) && (int) $m[1] >= 1 && (int) $m[2] >= (int) $m[1]) {
                $installment = [(int) $m[1], (int) $m[2]];
                $memo = trim(preg_replace('#\s*-?\s*parcela\s+\d{1,3}\s*/\s*\d{1,3}\s*$#iu', '', $memo));
            }

            $transactions[] = [
                // o banco repete o FITID entre uma compra internacional e o seu IOF: com o valor junto, cada linha é única
                'id' => ($this->tag($block, 'FITID') ?: 'sem-id-'.$i).':'.abs($amount),
                'date' => $date->toDateString(),
                'amount' => $amount,
                'memo' => $memo,
                'name' => $memo !== '' ? $memo : 'Sem descrição',
                'installment' => $installment,
            ];
        }

        if ($transactions === []) {
            throw new InvalidImportFile('Não encontrei nenhuma transação nesse arquivo OFX.');
        }

        return [
            'bank' => $this->tag($contents, 'ORG'),
            'account' => $this->tag($contents, 'ACCTID'),
            'start' => $this->date($this->tag($contents, 'DTSTART'))?->toDateString(),
            'end' => $this->date($this->tag($contents, 'DTEND'))?->toDateString(),
            'transactions' => $transactions,
        ];
    }

    private function tag(string $text, string $name): ?string
    {
        return preg_match('#<'.$name.'>\s*([^<\r\n]*)#i', $text, $m) ? trim($m[1]) : null;
    }

    private function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || ! preg_match('#^(\d{4})(\d{2})(\d{2})#', $value, $m)) {
            return null;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? CarbonImmutable::create((int) $m[1], (int) $m[2], (int) $m[3]) : null;
    }

    /** "-51.50" ou "-51,50" → -5150 (centavos, com sinal). */
    private function amount(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = str_contains($value, ',') && ! str_contains($value, '.') ? str_replace(',', '.', $value) : str_replace(',', '', $value);

        return is_numeric($value) ? (int) round(((float) $value) * 100) : null;
    }
}
