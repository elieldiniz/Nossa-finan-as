@php use App\Support\Money; @endphp
<div class="mx-auto flex max-w-2xl flex-col gap-4">
    <x-month-header :month="$this->monthDate()" :view="$view" title="Acerto do casal" subtitle="Gastos marcados como “do casal” no lançamento." :switcher="false" />

    <section class="nf-card p-5">
        <h2 class="text-base font-extrabold">Como dividir</h2>
        <div class="mt-3 flex h-3.5 overflow-hidden rounded-full bg-[#FFF1D6]" role="img" aria-label="Divisão {{ $firstPercent }}% e {{ 100 - $firstPercent }}%"><div class="bg-lime" style="width: {{ $firstPercent }}%"></div></div>
        <div class="mt-2 flex items-center justify-between text-sm font-bold"><span>{{ $members[0]->name }} {{ $firstPercent }}%</span><span>{{ $members->get(1)?->name ?? 'Par' }} {{ 100 - $firstPercent }}%</span></div>
        <label for="split" class="nf-label mt-3">% de {{ $members[0]->name }}</label>
        <input id="split" type="number" min="0" max="100" wire:model.live.debounce.400ms="firstPercent" class="nf-input" />
    </section>

    <section class="nf-card px-5 py-2" aria-label="Quem pagou">
        @foreach ($paid as $name => $cents)
            <div class="nf-row"><span class="flex size-11 items-center justify-center rounded-full bg-brand-soft font-extrabold text-brand">{{ mb_substr($name, 0, 1) }}</span><span class="flex-1"><span class="block text-sm font-extrabold">{{ $name }}</span><span class="text-xs text-muted">pagou</span></span><span class="text-base font-extrabold" data-testid="paid-{{ $name }}">{{ Money::format($cents) }}</span></div>
        @endforeach
    </section>

    <section class="nf-hero p-6" aria-live="polite">
        <p class="text-xs font-bold uppercase tracking-widest text-white/85">Para acertar</p>
        @if ($result->isSettled())
            <p class="mt-2 text-2xl font-extrabold" data-testid="settlement-text">Tudo acertado</p>
            <p class="mt-1 text-4xl font-extrabold text-lime" data-testid="settlement-amount">{{ Money::format(0) }}</p>
        @else
            <p class="mt-2 text-2xl font-extrabold" data-testid="settlement-text">{{ $result->debtor }} deve a {{ $result->creditor }}</p>
            <p class="mt-1 text-4xl font-extrabold text-lime" data-testid="settlement-amount">{{ Money::format($result->amount) }}</p>
        @endif
        <p class="mt-1 text-xs text-white/85">Total de gastos do casal {{ Money::format($total) }}</p>
    </section>

    @unless ($result->isSettled())
        <button type="button" wire:click="settle" class="nf-btn">Marcar como acertado</button>
    @endunless
</div>
