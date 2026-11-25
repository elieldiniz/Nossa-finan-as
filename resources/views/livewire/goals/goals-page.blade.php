@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-2xl flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Metas e vencimentos" :switcher="false" />

    <section class="nf-card p-5" aria-label="Vencimentos" data-testid="due">
        <h2 class="text-base font-extrabold">Vence nos próximos 7 dias</h2>
        @forelse ($due as $item)
            <div class="nf-row"><span class="flex-1 text-sm font-bold">{{ $item['name'] }}</span><span class="text-sm text-muted">{{ \Carbon\Carbon::parse($item['due'])->format('d/m') }}</span><span class="text-sm font-extrabold">{{ Money::format($item['amount']) }}</span></div>
        @empty
            <p class="mt-2 rounded-2xl border-2 border-dashed border-zinc-300 p-4 text-sm text-muted">Nada com vencimento nos próximos 7 dias. Defina o dia de cobrança nos cartões e recorrentes para ver aqui.</p>
        @endforelse
    </section>

    <section class="nf-card p-5" aria-label="Meta do mês" data-testid="general-goal">
        <div class="flex items-center justify-between"><h2 class="text-base font-extrabold">Meta do mês</h2><span class="text-xs text-muted">gasto {{ Money::format($totalSpent) }}</span></div>
        @if ($general)
            @php $pct = min(100, intdiv($totalSpent * 100, max($general->limit_cents, 1))); @endphp
            <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-line"><div class="h-full {{ $pct >= 100 ? 'bg-neg' : ($pct >= 80 ? 'bg-[#E8A34B]' : 'bg-brand') }}" style="width: {{ $pct }}%"></div></div>
            <p class="mt-1 text-sm font-bold">{{ Money::format($totalSpent) }} de {{ Money::format($general->limit_cents) }} ({{ $pct }}%)</p>
            @if ($generalLevel !== 'nenhum') <div class="mt-2"><x-goal-banner :level="$generalLevel" /></div> @endif
            <button type="button" wire:click="removeGoal({{ $general->id }})" class="nf-chip mt-2 !text-neg">Remover meta</button>
        @else
            <p class="mt-1 text-sm text-muted">Defina quanto vocês querem gastar. O app avisa em 80% e em 100%.</p>
        @endif
        <form wire:submit="saveGeneral" class="mt-3 flex gap-2"><input type="text" inputmode="decimal" wire:model="generalLimit" aria-label="Meta geral" placeholder="R$ 0,00" class="nf-input" /><button type="submit" class="nf-btn !px-5">{{ $general ? 'Alterar' : 'Definir' }}</button></form>
        @if ($error) <p role="alert" class="mt-1 text-sm font-bold text-neg" data-testid="form-error">{{ $error }}</p> @endif
    </section>

    <section class="nf-card p-5" aria-label="Limites por categoria">
        <h2 class="text-base font-extrabold">Por categoria</h2>
        @foreach ($categories as $c)
            <div class="nf-row flex-wrap" wire:key="cat-{{ $c['category']->id }}" data-testid="cat-{{ $c['category']->id }}">
                <span class="flex-1 text-sm font-bold">{{ $c['category']->name }}</span>
                <span class="text-sm {{ $c['level'] === '100' ? 'font-extrabold text-neg' : ($c['level'] === '80' ? 'font-extrabold text-[#8A5A00]' : 'text-muted') }}">{{ Money::format($c['spent']) }}@if ($c['goal']) de {{ Money::format($c['goal']->limit_cents) }}@endif</span>
                <form wire:submit="saveCategory({{ $c['category']->id }})" class="flex gap-1"><input type="text" inputmode="decimal" wire:model="categoryLimits.{{ $c['category']->id }}" aria-label="Limite de {{ $c['category']->name }}" placeholder="Limite" class="nf-input !min-h-10 !w-28" /><button type="submit" class="nf-chip">OK</button></form>
            </div>
        @endforeach
    </section>
</div>
