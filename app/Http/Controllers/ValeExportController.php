<?php

namespace App\Http\Controllers;

use App\Services\Vale\ValeOfxExporter;
use App\Services\Vale\ValePdfExporter;
use App\Services\Vale\ValeStatement;
use App\Support\ViewContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Baixar o extrato do Vale alimentação em PDF ou OFX (um mês ou todos os meses). */
class ValeExportController extends Controller
{
    public function __invoke(Request $request, string $format, ValeStatement $statements, ValeOfxExporter $ofx, ValePdfExporter $pdf): Response
    {
        abort_unless(in_array($format, ['pdf', 'ofx'], true), 404);

        $m = (string) $request->query('m', CarbonImmutable::now()->format('Y-m'));
        $all = $m === 'tudo';
        abort_unless($all || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) === 1, 422, 'Mês inválido.');

        $view = in_array($request->query('v'), [ViewContext::NOS, ViewContext::EU, ViewContext::PAR], true) ? $request->query('v') : ViewContext::EU;
        $statement = $statements->build(ViewContext::for($request->user(), $view), $all ? null : CarbonImmutable::createFromFormat('!Y-m', $m));

        $name = 'vale-alimentacao-'.($all ? 'todos-os-meses' : $m);

        return $format === 'pdf'
            ? response($pdf->render($statement), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$name}.pdf\""])
            : response($ofx->render($statement), 200, ['Content-Type' => 'application/x-ofx; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$name}.ofx\""]);
    }
}
