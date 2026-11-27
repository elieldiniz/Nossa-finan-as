@php
    use App\Support\Money;
    use Carbon\Carbon;
    $brand = \App\Support\Theme::palette(null)['brand'];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 34px 40px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #12211C; }
    h1 { font-size: 20px; margin: 0; }
    .muted { color: #5A6B64; }
    .head { border-bottom: 2px solid {{ $brand }}; padding-bottom: 8px; margin-bottom: 14px; }
    .box { border: 1px solid #D9E2DE; border-radius: 6px; padding: 8px 10px; margin: 6px 0 12px; }
    .resumo td { padding: 3px 0; }
    .right { text-align: right; }
    .mes { font-size: 13px; font-weight: bold; color: {{ $brand }}; margin: 16px 0 4px; }
    table.l { width: 100%; border-collapse: collapse; }
    table.l th { text-align: left; font-size: 9px; color: #5A6B64; border-bottom: 1px solid #D9E2DE; padding: 4px 2px; }
    table.l td { padding: 4px 2px; border-bottom: 1px solid #EEF2F0; }
    .cat td { background: #F3F6F4; font-weight: bold; padding: 5px 2px; }
    .sub td { font-weight: bold; border-bottom: 1px solid #D9E2DE; }
    .tot td { font-weight: bold; font-size: 11px; border-top: 2px solid {{ $brand }}; padding-top: 6px; }
    .neg { color: #B9442F; }
    .pos { color: #0B5A44; }
    .foot { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 8px; color: #5A6B64; text-align: center; }
</style>
</head>
<body>
<div class="foot">Gerado em {{ Carbon::parse($s['generated_at'])->format('d/m/Y H:i') }} pelo Nossa Finanças</div>

<div class="head">
    <h1>{{ $s['title'] }}</h1>
    <div class="muted">Extrato · {{ $s['period'] }}</div>
</div>

@forelse ($s['vales'] as $vale)
    <div class="box">
        <b>{{ $vale['owner'] }}</b>
        <table class="resumo" width="100%">
            <tr><td>Valor do vale (todo mês)</td><td class="right">{{ Money::format($vale['amount']) }}</td></tr>
            <tr><td>Gastos {{ count($vale['months']) > 1 ? 'no período' : 'no mês' }}</td><td class="right neg">{{ Money::format($vale['spent']) }}</td></tr>
            <tr><td><b>Saldo restante {{ count($vale['months']) > 1 ? 'no último mês' : '' }}</b></td><td class="right pos"><b>{{ Money::format($vale['remaining']) }}</b></td></tr>
        </table>
    </div>

    @foreach ($vale['months'] as $month)
        <div class="mes">{{ $month['label'] }}</div>
        <table class="l">
            <tr><th style="width: 12%">Data</th><th>Descrição</th><th class="right" style="width: 22%">Valor</th></tr>
            <tr><td>{{ Carbon::parse($month['credit_date'])->format('d/m') }}</td><td>Crédito do vale alimentação</td><td class="right pos">{{ Money::format($month['credit']) }}</td></tr>
            @forelse ($month['groups'] as $group)
                <tr class="cat"><td colspan="3">{{ $group['category'] }}</td></tr>
                @foreach ($group['rows'] as $row)
                    <tr><td>{{ Carbon::parse($row['date'])->format('d/m') }}</td><td>{{ $row['name'] }}</td><td class="right">{{ Money::format($row['amount']) }}-</td></tr>
                @endforeach
                <tr class="sub"><td></td><td class="right">Subtotal</td><td class="right">{{ Money::format($group['subtotal']) }}</td></tr>
            @empty
                <tr><td colspan="3" class="muted">Nenhum gasto neste mês.</td></tr>
            @endforelse
            <tr class="tot"><td></td><td class="right">Gastos do mês</td><td class="right">{{ Money::format($month['spent']) }}</td></tr>
            <tr class="tot" style="border-top: 0"><td></td><td class="right">Sobra (não passa para o próximo mês)</td><td class="right pos">{{ Money::format($month['remaining']) }}</td></tr>
        </table>
    @endforeach
@empty
    <p class="muted">Nenhum vale cadastrado.</p>
@endforelse
</body>
</html>
