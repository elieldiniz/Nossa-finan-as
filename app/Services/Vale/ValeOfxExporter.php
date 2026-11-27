<?php

namespace App\Services\Vale;

use Carbon\CarbonImmutable;

/**
 * OFX (formato SGML dos bancos, como o da Nubank) do extrato do Vale alimentação:
 * um extrato por vale; o valor do vale é CREDIT no 1º dia do mês e cada gasto é DEBIT. Dá para reimportar em outros apps.
 */
class ValeOfxExporter
{
    /** @param  array<string, mixed>  $statement  resultado de ValeStatement::build() */
    public function render(array $statement): string
    {
        $out = "OFXHEADER:100\nDATA:OFXSGML\nVERSION:102\nSECURITY:NONE\nENCODING:UTF-8\nCHARSET:NONE\nCOMPRESSION:NONE\nOLDFILEUID:NONE\nNEWFILEUID:NONE\n\n";
        $out .= "<OFX>\n<SIGNONMSGSRSV1>\n<SONRS>\n<STATUS>\n<CODE>0</CODE>\n<SEVERITY>INFO</SEVERITY>\n</STATUS>\n";
        $out .= '<DTSERVER>'.CarbonImmutable::parse($statement['generated_at'])->format('YmdHis')."[-3:BRT]</DTSERVER>\n<LANGUAGE>POR</LANGUAGE>\n";
        $out .= "<FI>\n<ORG>Nossa Financas - Vale alimentacao</ORG>\n<FID>0</FID>\n</FI>\n</SONRS>\n</SIGNONMSGSRSV1>\n<BANKMSGSRSV1>\n";

        foreach ($statement['vales'] as $i => $vale) {
            $out .= '<STMTTRNRS>'."\n<TRNUID>".($i + 1)."</TRNUID>\n<STATUS>\n<CODE>0</CODE>\n<SEVERITY>INFO</SEVERITY>\n</STATUS>\n<STMTRS>\n<CURDEF>BRL</CURDEF>\n";
            $out .= "<BANKACCTFROM>\n<BANKID>0</BANKID>\n<ACCTID>vale-{$vale['id']}</ACCTID>\n<ACCTTYPE>CHECKING</ACCTTYPE>\n</BANKACCTFROM>\n<BANKTRANLIST>\n";
            $out .= '<DTSTART>'.$this->date($statement['start'])."</DTSTART>\n<DTEND>".$this->date($statement['end'])."</DTEND>\n";

            foreach ($vale['months'] as $month) {
                $out .= $this->transaction('CREDIT', $month['credit_date'], $month['credit'], "vale-{$vale['id']}-credito-".str_replace('-', '', $month['month']), 'Credito do Vale alimentacao - '.$vale['owner']);

                foreach ($month['spends'] as $spend) {
                    $out .= $this->transaction('DEBIT', $spend['date'], -$spend['amount'], "vale-{$vale['id']}-gasto-{$spend['id']}", $spend['name']);
                }
            }

            $last = end($vale['months']);
            $out .= "</BANKTRANLIST>\n<LEDGERBAL>\n<BALAMT>".$this->money($last ? $last['remaining'] : $vale['amount'])."</BALAMT>\n<DTASOF>".$this->date($statement['end'])."</DTASOF>\n</LEDGERBAL>\n</STMTRS>\n</STMTTRNRS>\n";
        }

        return $out."</BANKMSGSRSV1>\n</OFX>\n";
    }

    private function transaction(string $type, string $date, int $cents, string $id, string $memo): string
    {
        $memo = htmlspecialchars($memo, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "<STMTTRN>\n<TRNTYPE>{$type}</TRNTYPE>\n<DTPOSTED>".$this->date($date)."</DTPOSTED>\n<TRNAMT>".$this->money($cents)."</TRNAMT>\n<FITID>{$id}</FITID>\n<MEMO>{$memo}</MEMO>\n</STMTTRN>\n";
    }

    private function date(string $date): string
    {
        return CarbonImmutable::parse($date)->format('Ymd').'000000[-3:BRT]';
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
