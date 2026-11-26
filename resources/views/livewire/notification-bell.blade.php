@php use App\Support\Money; @endphp
<div class="relative shrink-0" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false" wire:poll.120s data-testid="bell">
    <button type="button" x-on:click="open = ! open" aria-label="Avisos{{ $items->count() ? ': '.$items->count().' novo(s)' : '' }}" aria-haspopup="true" x-bind:aria-expanded="open"
            class="relative flex size-11 items-center justify-center rounded-full bg-white text-xl shadow-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6" aria-hidden="true"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        @if ($items->count())
            <span class="absolute -right-0.5 -top-0.5 flex min-w-5 items-center justify-center rounded-full bg-neg px-1 text-[11px] font-extrabold leading-5 text-white" data-testid="bell-count">{{ $items->count() }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right role="dialog" aria-label="Avisos" data-testid="bell-panel"
         class="absolute right-0 top-13 z-50 w-[min(20rem,calc(100vw-2rem))] rounded-3xl bg-white p-3 shadow-[0_8px_30px_rgba(18,33,28,.18)]">
        <div class="flex items-center justify-between px-2 pb-1">
            <h2 class="text-sm font-extrabold">Avisos</h2>
            @if ($items->count() > 1) <button type="button" wire:click="markAllRead" class="text-xs font-bold text-brand" data-testid="mark-all">Limpar todos</button> @endif
        </div>

        @forelse ($items as $n)
            <button type="button" wire:click="open('{{ $n->id }}')" wire:key="n-{{ $n->id }}" class="nf-row w-full text-left" data-testid="bell-item">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-2xl bg-neg-soft text-neg"><x-nav.icon :name="($n->data['type'] ?? '') === 'invoice' ? 'card' : 'list'" class="size-5" /></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-bold">{{ $n->data['name'] }}</span>
                    <span class="text-xs font-bold text-neg">vence hoje · {{ Money::format($n->data['amount']) }}</span>
                </span>
            </button>
        @empty
            <p class="px-2 py-4 text-center text-sm text-muted" data-testid="bell-empty">Nenhum aviso. Quando uma conta vencer, ela aparece aqui.</p>
        @endforelse

        <div class="mt-1 border-t border-line px-2 pt-2">
            <button type="button" wire:click="toggleEmail" class="flex w-full items-center justify-between text-xs font-semibold text-muted" data-testid="toggle-email" role="switch" aria-checked="{{ $emailOn ? 'true' : 'false' }}">
                <span>Avisar também por e-mail</span>
                <span class="rounded-full px-2.5 py-1 font-extrabold {{ $emailOn ? 'bg-brand-soft text-brand' : 'bg-line text-muted' }}">{{ $emailOn ? 'Ligado' : 'Desligado' }}</span>
            </button>
        </div>
    </div>
</div>
