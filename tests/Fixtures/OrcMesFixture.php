<?php

namespace Tests\Fixtures;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Monta uma planilha .xlsx no mesmo layout de "Orç - Mês" (sem dados pessoais), para os testes.
 */
final class OrcMesFixture
{
    /** Setembro: Madrinha pagar 300 e receber 415, Bradesco com 2 fixos de 300, Vale 2.250. */
    public static function setembro(array $extra = []): string
    {
        $book = new Spreadsheet;
        $s = $book->getActiveSheet();
        $s->setTitle($extra['sheet'] ?? 'Orç - Mês');

        $s->setCellValue('B2', 'Financeiro de Setembro - Pagamento Outubro')->setCellValue('M2', 'Vale Setembro');
        $s->setCellValue('B3', 'Fluxo de Caixa')->setCellValue('F3', 'Cartões')->setCellValue('J3', 'Recebimento/Pagamento - Pessoas');
        $s->setCellValue('M3', 'Valor')->setCellValue('N3', 2250)->setCellValue('M4', 'Gastos');

        // Entradas (linha 8 é o saldo da Madrinha, só fórmula) e saídas (cartões são fórmulas)
        $s->setCellValue('B4', 'Entradas')->setCellValue('B5', 'Pago')->setCellValue('C5', 'Nome')->setCellValue('D5', 'Valor');
        for ($r = 6; $r <= 17; $r++) {
            $s->setCellValue("B{$r}", false);
        }
        $s->setCellValue('D8', '=K15');
        foreach ($extra['entradas'] ?? [] as $i => [$nome, $valor, $pago]) {
            $s->setCellValue('B'.(9 + $i), $pago)->setCellValue('C'.(9 + $i), $nome)->setCellValue('D'.(9 + $i), $valor);
        }

        $s->setCellValue('B18', 'Saídas')->setCellValue('B19', 'Pago')->setCellValue('C19', 'Nome')->setCellValue('D19', 'Valor');
        for ($r = 20; $r <= 31; $r++) {
            $s->setCellValue("B{$r}", false)->setCellValue("D{$r}", 0);
        }
        $s->setCellValue('C23', 'Cartão Cresol')->setCellValue('D23', '=H63')->setCellValue('C24', 'Cartão Bradesco')->setCellValue('D24', '=H53');
        foreach ($extra['saidas'] ?? [] as $i => [$nome, $valor, $pago]) {
            $s->setCellValue('B'.(25 + $i), $pago)->setCellValue('C'.(25 + $i), $nome)->setCellValue('D'.(25 + $i), $valor);
        }
        $s->setCellValue('B32', 'FECHAMENTO DO MÊS')->setCellValue('D32', '=SUM(D6:D17)-SUM(D20:D31)');

        // Cartão Bradesco: 2 fixos
        $s->setCellValue('F4', 'Bradesco ')->setCellValue('F5', 'Parcelamento/Data')->setCellValue('G5', 'Nome')->setCellValue('H5', 'Valor ');
        $s->setCellValue('F6', 'FIXOS')->setCellValue('H6', 300)->setCellValue('H7', 300);
        foreach ($extra['bradesco'] ?? [] as $i => [$parc, $nome, $valor]) {
            $s->setCellValue('F'.(8 + $i), $parc)->setCellValue('G'.(8 + $i), $nome)->setCellValue('H'.(8 + $i), $valor);
        }
        $s->setCellValue('F53', 'Cartão - TOTAL')->setCellValue('H53', '=SUM(H6:H52)');
        $s->setCellValue('F55', 'Cresol')->setCellValue('F56', 'Parcelamento/Data')->setCellValue('G56', 'Nome')->setCellValue('H56', 'Valor ');
        $s->setCellValue('F63', 'Cartão - TOTAL')->setCellValue('H63', '=SUM(H57:H62)');

        // Pessoas
        $s->setCellValue('J4', 'Madrinha')->setCellValue('J5', 'Pagar')->setCellValue('J6', 'Nome')->setCellValue('K6', 'Valor')->setCellValue('K7', 300);
        $s->setCellValue('J10', 'Receber')->setCellValue('J11', 'Nome')->setCellValue('K11', 'Valor')->setCellValue('K12', 415);
        $s->setCellValue('J15', 'TOTAL')->setCellValue('K15', '=SUM(K12:K14)-SUM(K7:K9)');
        $s->setCellValue('J17', 'Glória')->setCellValue('J18', 'Receber')->setCellValue('J19', 'Nome')->setCellValue('K19', 'Valor')->setCellValue('J22', 'TOTAL')->setCellValue('K22', '=SUM(K20:K21)');
        $s->setCellValue('J24', 'Mãe')->setCellValue('J25', 'Pagar')->setCellValue('J26', 'Nome')->setCellValue('K26', 'Valor')->setCellValue('J30', 'TOTAL')->setCellValue('K30', '=SUM(K27:K28)');

        // Vale
        $s->setCellValue('M8', 'Gastos Pessoais')->setCellValue('M12', 'TOTAL')->setCellValue('N12', '=N3-SUM(N9:N11)');
        foreach ($extra['vale_gastos'] ?? [] as $i => [$nome, $valor]) {
            $s->setCellValue('M'.(9 + $i), $nome)->setCellValue('N'.(9 + $i), $valor);
        }

        if (isset($extra['extra_rows'])) {
            // arquivo grande: muitas saídas depois do bloco de saídas não cabem; usa linhas de entradas "avulsas"
            for ($i = 0; $i < $extra['extra_rows']; $i++) {
                $s->setCellValue('P'.(100 + $i), 'x');
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'orcmes').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}
