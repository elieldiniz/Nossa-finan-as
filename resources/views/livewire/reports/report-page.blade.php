@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-3xl flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Relatório por categoria" />

    <section class="nf-card overflow-x-auto p-5">
        <table class="w-full text-sm" data-testid="report">
            <caption class="sr-only">Gastos por categoria, comparados com o mês anterior</caption>
            <thead><tr class="text-left text-xs text-muted"><th scope="col" class="py-2">Categoria</th><th scope="col" class="py-2 text-right">Mês anterior</th><th scope="col" class="py-2 text-right">Este mês</th><th scope="col" class="py-2 text-right">Variação</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-t border-ground" data-testid="row-{{ \Illuminate\Support\Str::slug($row['name']) }}">
                        <th scope="row" class="py-3 text-left font-bold">{{ $row['name'] }}</th>
                        <td class="py-3 text-right">{{ Money::format($row['previous']) }}</td>
                        <td class="py-3 text-right font-extrabold">{{ Money::format($row['current']) }}</td>
                        <td class="py-3 text-right font-bold {{ $row['variation']['delta'] > 0 ? 'text-neg' : ($row['variation']['delta'] < 0 ? 'text-pos' : '') }}" data-testid="variation">{{ $row['variation']['percent'] ?? 'novo' }} <span class="text-xs font-semibold text-muted">({{ Money::signed($row['variation']['delta']) }})</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-6 text-center text-muted">Sem gastos neste mês.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot><tr class="border-t-2 border-line font-extrabold"><th scope="row" class="py-3 text-left">Total</th><td class="py-3 text-right">{{ Money::format($totalPrevious) }}</td><td class="py-3 text-right">{{ Money::format($totalCurrent) }}</td><td class="py-3 text-right">{{ $totalVariation['percent'] ?? '—' }}</td></tr></tfoot>
            @endif
        </table>
    </section>
</div>
