@php use App\Support\Money; @endphp
<div class="flex flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" :title="'Olá, '.auth()->user()->firstName()" />

    <div class="lg:grid lg:grid-cols-3 lg:gap-5">
        <div class="lg:col-span-2"><x-closing-banner :closing="$summary['closing']" /></div>
        <section class="nf-card mt-4 p-5 lg:mt-0" aria-label="Gastos do mês">
            <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Gastos do mês</h2><a href="{{ route('charts') }}" wire:navigate class="text-sm font-bold text-brand">Ver gráficos ›</a></div>
            <p class="mt-1 text-3xl font-extrabold" data-testid="spent">{{ Money::format($summary['closing']->expense) }}</p>
            @php $total = max($byCategory->sum(), 1); @endphp
            <div class="mt-3 flex h-3.5 overflow-hidden rounded-full bg-line" role="img" aria-label="Divisão dos gastos por categoria">
                @foreach ($byCategory as $name => $cents)
                    <div style="width: {{ intdiv($cents * 100, $total) }}%" class="{{ ['bg-brand', 'bg-brand-light', 'bg-[#E8A34B]', 'bg-neg'][$loop->index % 4] }}"></div>
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

    <section class="nf-card p-5" aria-label="Contas a vencer" data-testid="due-bills">
        @php $pendentes = collect($due)->where('paid', false); $pagas = collect($due)->where('paid', true); @endphp
        <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Contas a vencer</h2><span class="text-xs text-muted" data-testid="due-count">{{ $this->monthDate()->translatedFormat('F') }} · {{ $pagas->count() }} de {{ count($due) }} pagas</span></div>
        @forelse ($pendentes as $bill)
            <div class="nf-row" wire:key="bill-{{ $bill['type'] }}-{{ $bill['id'] }}-{{ $bill['month'] }}" data-testid="bill">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl {{ $bill['overdue'] || ($bill['days_left'] !== null && $bill['days_left'] <= 1) ? 'bg-neg-soft text-neg' : 'bg-[#FFF1D6] text-[#6B4600]' }}"><x-nav.icon :name="$bill['type'] === 'invoice' ? 'card' : 'list'" class="size-5" /></span>
                <span class="min-w-0 flex-1">@if ($bill['type'] === 'entry')<a href="{{ route('entry.show', $bill['id']) }}" wire:navigate class="block truncate text-sm font-bold" data-testid="bill-edit">{{ $bill['name'] }}</a>@else<span class="block truncate text-sm font-bold">{{ $bill['name'] }}</span>@endif
                    <span class="text-xs {{ $bill['overdue'] || ($bill['days_left'] !== null && $bill['days_left'] <= 1) ? 'font-bold text-neg' : 'text-muted' }}" data-testid="bill-when">@if ($bill['overdue']) venceu {{ \Carbon\Carbon::parse($bill['due'])->format('d/m') }} (atrasada) @elseif ($bill['days_left'] === 0) vence hoje @elseif ($bill['days_left'] === 1) vence amanhã @else vence dia {{ \Carbon\Carbon::parse($bill['due'])->format('d/m') }} @endif</span></span>
                <span class="text-sm font-extrabold">{{ Money::format($bill['amount']) }}</span>
                @if ($bill['owner_id'] !== null && $bill['owner_id'] !== auth()->id())
                    <span class="text-xs font-semibold text-muted" data-testid="bill-owner">de {{ \Illuminate\Support\Str::before($owners[$bill['owner_id']] ?? 'outra pessoa', ' ') }}</span>
                @elseif ($view !== 'par')
                    <button type="button" wire:click="payBill('{{ $bill['type'] }}', {{ $bill['id'] }}, {{ $bill['month'] ? "'".$bill['month']."'" : 'null' }})" class="nf-chip !min-h-9 !px-3 !text-xs">Pago</button>
                @endif
            </div>
        @empty
            <p class="mt-2 text-sm text-muted" data-testid="no-bills">@if (count($due) > 0) Tudo pago neste mês. 🎉 @else Nenhuma conta com vencimento neste mês. <a href="{{ route('cards') }}" wire:navigate class="font-bold text-brand">Cadastre o “vence dia” dos cartões</a> e use “Vence em” ao lançar uma conta para ela aparecer aqui. @endif</p>
        @endforelse

        @foreach ($pagas as $bill)
            <div class="nf-row opacity-70" wire:key="paid-{{ $bill['type'] }}-{{ $bill['id'] }}-{{ $bill['month'] }}" data-testid="bill-paid">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-pos-soft text-pos">✓</span>
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold line-through">{{ $bill['name'] }}</span><span class="text-xs text-muted">paga · dia {{ \Carbon\Carbon::parse($bill['due'])->format('d/m') }}</span></span>
                <span class="text-sm font-extrabold">{{ Money::format($bill['amount']) }}</span>
                @if ($view !== 'par' && ($bill['owner_id'] === null || $bill['owner_id'] === auth()->id()))
                    <button type="button" wire:click="unpayBill('{{ $bill['type'] }}', {{ $bill['id'] }}, {{ $bill['month'] ? "'".$bill['month']."'" : 'null' }})" class="nf-chip !min-h-9 !px-3 !text-xs">Desfazer</button>
                @endif
            </div>
        @endforeach
    </section>

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

    <a href="{{ route('vale') }}" wire:navigate class="nf-card block p-5" aria-label="Vale alimentação" data-testid="vale-card">
        <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Vale alimentação</h2><span class="text-base font-extrabold text-pos">{{ Money::format($summary['vale']['remaining']) }}</span></div>
        @php $v = $summary['vale']; $pct = $v['amount'] > 0 ? max(0, min(100, intdiv($v['remaining'] * 100, $v['amount']))) : 0; @endphp
        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-line"><div class="h-full bg-brand" style="width: {{ $pct }}%"></div></div>
        <p class="mt-1 text-xs text-muted">restante de {{ Money::format($v['amount']) }}</p>
    </a>

    <a href="{{ route('goals') }}" wire:navigate class="nf-card block p-5" aria-label="Investimentos" data-testid="investing-card">
        <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Investimentos</h2><span class="text-sm font-bold text-brand">Ver metas ›</span></div>
        <div class="mt-2 grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-brand-soft px-3 py-2"><div class="text-xs font-semibold text-brand">Investido no mês</div><div class="font-extrabold text-brand" data-testid="invested-month">{{ Money::format($investing['month']) }}</div></div>
            <div class="rounded-2xl bg-ground px-3 py-2"><div class="text-xs font-semibold text-muted">Total já investido</div><div class="font-extrabold" data-testid="invested-total">{{ Money::format($investing['accumulated']) }}</div></div>
        </div>
        @if ($investing['goal'])
            @php $ipct = min(100, intdiv($investing['month'] * 100, max($investing['goal']->limit_cents, 1))); @endphp
            <div class="mt-3 h-2.5 overflow-hidden rounded-full bg-line"><div class="h-full bg-brand" style="width: {{ $ipct }}%"></div></div>
            <p class="mt-1 text-xs text-muted">{{ $ipct }}% da meta de {{ Money::format($investing['goal']->limit_cents) }} por mês</p>
        @else
            <p class="mt-2 text-xs text-muted">Toque no + e escolha “Investimento” para registrar um aporte.</p>
        @endif
    </a>

    <section class="nf-card p-5" aria-label="Últimas movimentações" data-testid="recent">
        <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Últimas movimentações</h2><a href="{{ route('statement') }}" wire:navigate class="text-sm font-bold text-brand">Extrato</a></div>
        @forelse ($recent as $row)
            <div class="nf-row">
                <span class="flex size-10 items-center justify-center rounded-2xl {{ ($row['kind'] ?? '') === 'aporte' ? 'bg-brand-soft text-brand' : ($row['signed'] >= 0 ? 'bg-pos-soft text-pos' : 'bg-neg-soft text-neg') }}"><x-nav.icon :name="$row['source'] === 'card' ? 'card' : ($row['source'] === 'person' ? 'people' : (($row['kind'] ?? '') === 'aporte' ? 'chart' : 'list'))" class="size-5" /></span>
                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $row['name'] }}</span><span class="text-xs text-muted">{{ $row['sub'] }}</span></span>
                <span class="text-sm font-extrabold {{ $row['signed'] >= 0 ? 'text-pos' : '' }}">{{ Money::signed($row['signed']) }}</span>
            </div>
        @empty
            <p class="mt-2 text-sm text-muted">Nada por aqui ainda. Toque no + para lançar.</p>
        @endforelse
    </section>
</div>
