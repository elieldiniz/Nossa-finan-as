<?php

namespace App\Services\Vale;

use Dompdf\Dompdf;
use Dompdf\Options;

/** PDF do extrato do Vale alimentação: resumo, lançamentos por categoria com subtotal e saldo restante de cada mês. */
class ValePdfExporter
{
    /** @param  array<string, mixed>  $statement  resultado de ValeStatement::build() */
    public function render(array $statement): string
    {
        $dir = storage_path('app/dompdf');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('fontDir', $dir);
        $options->set('fontCache', $dir);
        $options->set('tempDir', $dir);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.vale-statement', ['s' => $statement])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }
}
