@php use App\Support\Money; @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" :title="'Olá, '.auth()->user()->name" />

    <div class="lg:grid lg:grid-cols-3 lg:gap-5">
        <div class="lg:col-span-2"><x-closing-banner :closing="$summary['closing']" /></div>
        <section class="nf-card mt-4 p-5 lg:mt-0" aria-label="Gastos do mês">
            <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Gastos do mês</h2><a href="{{ route('charts') }}" wire:navigate class="text-sm font-bold text-brand">Ver gráficos ›</a></div>
            <p class="mt-1 text-3xl font-extrabold" data-testid="spent">{{ Money::format($summary['closing']->expense) }}</p>
            @php $total = max($byCategory->sum(), 1); @endphp
            <div class="mt-3 flex h-3.5 overflow-hidden rounded-full bg-line" role="img" aria-label="Divisão dos gastos por categoria">
                @foreach ($byCategory as $name => $cents)
                    <div style="width: {{ intdiv($cents * 100, $total) }}%" class="{{ ['bg-brand', 'bg-[#5BB89A]', 'bg-[#E8A34B]', 'bg-neg'][$loop->index % 4] }}"></div>
                @endforeach
            </div>
            <ul class="mt-2 text-sm">
                @forelse ($byCategory->take(3) as $name => $cents)
                    <li class="flex justify-between py-0.5"><span class="font-semibold">{{ $name }}</span><span>{{ Money::percent($cents, $total) }}</span></li>
                @empty
                    <li class="text-muted">Nenhum gasto neste mês ainda.</li>
                @endforelse
            </ul>
        </section>
    </div>

    @if ($goalAlert['level'] !== 'nenhum')
        <x-goal-banner :level="$goalAlert['level']" />
    @endif

    @if (count($due))
        <section class="nf-card p-5" aria-label="Vencimentos próximos" data-testid="due-banner">
            <h2 class="text-base font-extrabold">Vence nos próximos 7 dias</h2>
            @foreach ($due as $item)
                <div class="nf-row"><span class="flex-1 text-sm font-bold">{{ $item['name'] }}</span><span class="text-sm text-muted">{{ \Carbon\Carbon::parse($item['due'])->format('d/m') }}</span><span class="text-sm font-extrabold">{{ Money::format($item['amount']) }}</span></div>
            @endforeach
        </section>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="nf-card p-5" aria-label="Cartões" data-testid="cards-list">
            <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Cartões</h2><a href="{{ route('cards') }}" wire:navigate class="text-sm font-bold text-brand">Ver todos</a></div>
            @forelse ($summary['cards'] as $c)
                <a href="{{ route('cards') }}" wire:navigate class="nf-row">
                    <span class="flex size-10 items-center justify-center rounded-2xl bg-ink text-lime"><x-nav.icon name="card" class="size-5" /></span>
                    <span class="flex-1"><span class="block text-sm font-bold">{{ $c['card']->name }}</span><span class="text-xs text-muted">Fatura do mês</span></span>
                    <span class="text-sm font-extrabold">{{ Money::format($c['total']) }}</span>
                </a>
            @empty
                <p class="mt-2 text-sm text-muted">Nenhum cartão cadastrado. <a href="{{ route('cards') }}" class="font-bold text-brand">Cadastrar</a></p>
            @endforelse
        </section>

        <section class="nf-card p-5" aria-label="Pessoas" data-testid="people-list">
            <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Pessoas</h2><a href="{{ route('people') }}" wire:navigate class="text-sm font-bold text-brand">Ver todas</a></div>
            @forelse ($summary['people'] as $p)
                <a href="{{ route('people') }}" wire:navigate class="nf-row">
                    <span class="flex size-10 items-center justify-center rounded-full bg-pos-soft font-extrabold text-pos">{{ mb_substr($p['person']->name, 0, 1) }}</span>
                    <span class="flex-1 text-sm font-bold">{{ $p['person']->name }}</span>
                    <span class="text-sm font-extrabold {{ $p['net'] > 0 ? 'text-pos' : ($p['net'] < 0 ? 'text-neg' : 'text-muted') }}">{{ Money::signed($p['net']) }}</span>
                </a>
            @empty
                <p class="mt-2 text-sm text-muted">Nenhuma pessoa cadastrada. <a href="{{ route('people') }}" class="font-bold text-brand">Cadastrar</a></p>
            @endforelse
        </section>
    </div>

    <a href="{{ route('vale') }}" wire:navigate class="nf-card block p-5" aria-label="Vale" data-testid="vale-card">
        <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Vale</h2><span class="text-base font-extrabold text-pos">{{ Money::format($summary['vale']['remaining']) }}</span></div>
        @php $v = $summary['vale']; $pct = $v['amount'] > 0 ? max(0, min(100, intdiv($v['remaining'] * 100, $v['amount']))) : 0; @endphp
        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-line"><div class="h-full bg-brand" style="width: {{ $pct }}%"></div></div>
        <p class="mt-1 text-xs text-muted">restante de {{ Money::format($v['amount']) }}</p>
    </a>

    <section class="nf-card p-5" aria-label="Últimas movimentações" data-testid="recent">
        <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Últimas movimentações</h2><a href="{{ route('statement') }}" wire:navigate class="text-sm font-bold text-brand">Extrato</a></div>
        @forelse ($recent as $row)
            <div class="nf-row">
                <span class="flex size-10 items-center justify-center rounded-2xl {{ $row['signed'] >= 0 ? 'bg-pos-soft text-pos' : 'bg-neg-soft text-neg' }}"><x-nav.icon :name="$row['source'] === 'card' ? 'card' : ($row['source'] === 'person' ? 'people' : 'list')" class="size-5" /></span>
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $row['name'] }}</span><span class="text-xs text-muted">{{ $row['sub'] }}</span></span>
                <span class="text-sm font-extrabold {{ $row['signed'] >= 0 ? 'text-pos' : '' }}">{{ Money::signed($row['signed']) }}</span>
            </div>
        @empty
            <p class="mt-2 text-sm text-muted">Nada por aqui ainda. Toque no + para lançar.</p>
        @endforelse
    </section>
</div>
