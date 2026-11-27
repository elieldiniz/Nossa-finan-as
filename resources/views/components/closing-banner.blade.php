@props(['closing', 'title' => 'Saldo do mês', 'pendingIncome' => 0])
@php
    $negative = $closing->sign() === 'negativo';
@endphp
<section class="nf-hero p-6" aria-label="{{ $title }}">
    <p class="text-xs font-bold uppercase tracking-widest text-white/85">{{ $title }}</p>
    <p class="mt-1 text-4xl font-extrabold tracking-tight {{ $negative ? 'text-[#FFB9AA]' : 'text-lime' }}" data-testid="closing-value">{{ \App\Support\Money::signed($closing->closing) }}</p>
    <p class="mt-1 inline-block rounded-full bg-white/15 px-3 py-1 text-xs font-bold {{ $negative ? 'text-[#FFD3C8]' : 'text-[#E3F7A8]' }}" data-testid="closing-label">{{ $closing->label() }}</p>
    <div class="mt-4 grid grid-cols-2 gap-3">
        <div class="rounded-2xl bg-white/12 px-4 py-3"><div class="text-xs text-white/85">Entradas</div><div class="text-base font-extrabold text-lime" data-testid="income">{{ \App\Support\Money::signed($closing->income) }}</div>@if ($pendingIncome > 0)<div class="text-[11px] font-bold text-white/85" data-testid="pending-income">{{ \App\Support\Money::format($pendingIncome) }} a receber</div>@endif</div>
        <div class="rounded-2xl bg-white/12 px-4 py-3"><div class="text-xs text-white/85">Saídas</div><div class="text-base font-extrabold" data-testid="expense">{{ $closing->expense > 0 ? '−' : '' }}{{ \App\Support\Money::format($closing->expense) }}</div></div>
    </div>
    @if ($closing->invested > 0)
        <p class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-white/12 px-4 py-2 text-sm" data-testid="invested-line">
            <span><span class="text-white/85">Investido no mês:</span> <b data-testid="invested">{{ \App\Support\Money::format($closing->invested) }}</b></span>
            <span><span class="text-white/85">Sobra depois de investir:</span> <b data-testid="after-investing">{{ \App\Support\Money::signed($closing->afterInvesting()) }}</b></span>
        </p>
    @endif
</section>
