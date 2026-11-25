@php use App\Support\Money; $months = collect($series['line']); $prev = $months->slice(-2, 1)->first(); $variation = \App\Domain\ReportCalculator::variation($current, (int) $prev); @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Painel de gastos" />

    <div role="tablist" aria-label="Período" class="flex rounded-full bg-line p-1 lg:w-fit">
        @foreach ([3 => '3 meses', 6 => '6 meses', 12 => 'Ano'] as $value => $label)
            <button type="button" role="tab" wire:click="setPeriod({{ $value }})" aria-selected="{{ $period === $value ? 'true' : 'false' }}" class="min-h-11 flex-1 rounded-full px-5 text-sm font-semibold lg:flex-none {{ $period === $value ? 'bg-ink font-extrabold text-white' : 'text-muted' }}">{{ $label }}</button>
        @endforeach
    </div>

    <section class="nf-hero p-6">
        <p class="text-xs font-bold uppercase tracking-widest text-white/85">Gastos de {{ str($this->monthDate()->translatedFormat('F'))->ucfirst() }}</p>
        <p class="mt-1 text-4xl font-extrabold" data-testid="current">{{ Money::format($current) }}</p>
        @if ($variation['percent'] !== null)
            <p class="mt-2 inline-block rounded-full bg-white/15 px-3 py-1 text-xs font-bold" data-testid="variation">{{ $variation['delta'] > 0 ? '▲' : ($variation['delta'] < 0 ? '▼' : '=') }} {{ ltrim($variation['percent'], '+') }} {{ $variation['delta'] >= 0 ? 'a mais' : 'a menos' }} que o mês anterior ({{ Money::format((int) $prev) }})</p>
        @else
            <p class="mt-2 text-xs text-white/85">Sem mês anterior para comparar ainda.</p>
        @endif
    </section>

    <div class="grid gap-4 lg:grid-cols-2" x-data="nfCharts(@js($series))" wire:key="charts-{{ $period }}-{{ $month }}-{{ $view }}">
        <section class="nf-card p-5" aria-label="Evolução dos gastos"><h2 class="text-base font-extrabold">Evolução dos gastos</h2><div class="relative mt-2 h-52"><canvas x-ref="line" role="img" aria-label="Gráfico de linha da evolução dos gastos"></canvas></div></section>
        <section class="nf-card p-5" aria-label="Mês anterior × este mês"><h2 class="text-base font-extrabold">Mês anterior × este mês, por categoria</h2><div class="relative mt-2 h-52"><canvas x-ref="bars" role="img" aria-label="Gráfico de barras por categoria"></canvas></div></section>
        <section class="nf-card p-5" aria-label="Para onde foi o dinheiro"><h2 class="text-base font-extrabold">Para onde foi o dinheiro</h2><div class="relative mx-auto mt-2 h-52 max-w-xs"><canvas x-ref="donut" role="img" aria-label="Rosca por categoria"></canvas></div></section>
        <section class="nf-card p-5" aria-label="Maiores gastos" data-testid="ranking">
            <h2 class="text-base font-extrabold">Maiores gastos do mês</h2>
            @php $max = max(1, (int) collect($series['ranking'])->max()); @endphp
            @forelse ($series['ranking'] as $name => $cents)
                <div class="mt-3"><div class="flex justify-between text-sm font-semibold"><span>{{ $loop->iteration }}. {{ $name }}</span><span>{{ Money::format($cents) }}</span></div><div class="mt-1 h-2.5 overflow-hidden rounded-full bg-line"><div class="h-full bg-brand" style="width: {{ intdiv($cents * 100, $max) }}%"></div></div></div>
            @empty
                <p class="mt-2 text-sm text-muted">Sem gastos neste mês.</p>
            @endforelse
        </section>
    </div>

    <details class="nf-card p-5"><summary class="cursor-pointer text-sm font-extrabold">Ver os números dos gráficos (tabela)</summary>
        <table class="mt-3 w-full text-sm" data-testid="line-table"><caption class="sr-only">Gastos por mês</caption><thead><tr class="text-left text-muted"><th scope="col" class="py-1">Mês</th><th scope="col" class="py-1 text-right">Gasto</th></tr></thead>
            <tbody>@foreach ($series['line'] as $key => $cents)<tr><td class="py-1">{{ \Carbon\Carbon::createFromFormat('!Y-m', $key)->translatedFormat('M/Y') }}</td><td class="py-1 text-right font-bold">{{ Money::format($cents) }}</td></tr>@endforeach</tbody></table>
    </details>
</div>
