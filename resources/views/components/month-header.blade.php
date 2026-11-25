@props(['month', 'view', 'title' => null, 'subtitle' => null, 'switcher' => true])
@php
    $me = auth()->user();
    $partner = $me->partner();
    $label = str($month->translatedFormat('F \d\e Y'))->ucfirst();
    $options = [['nos', 'Nós'], ['eu', $me->firstName()], ['par', $partner?->firstName() ?? 'Par']];
@endphp
<header class="mb-4">
    @if ($title)
        <div class="mb-3">
            <h1 class="text-2xl font-extrabold">{{ $title }}</h1>
            @if ($subtitle) <p class="text-sm text-muted">{{ $subtitle }}</p> @endif
        </div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
            <button type="button" wire:click="previousMonth" aria-label="Mês anterior" class="flex size-11 items-center justify-center rounded-full bg-white text-xl">‹</button>
            <div class="min-w-36 text-center"><div class="text-base font-extrabold" data-testid="month-label">{{ $label }}</div></div>
            <button type="button" wire:click="nextMonth" aria-label="Próximo mês" class="flex size-11 items-center justify-center rounded-full bg-white text-xl">›</button>
        </div>

        @if ($switcher)
            <div role="tablist" aria-label="Ver dados de" class="ml-auto flex rounded-full bg-line p-1" data-testid="view-switcher">
                @foreach ($options as [$value, $text])
                    <button type="button" role="tab" wire:click="setView('{{ $value }}')" aria-selected="{{ $view === $value ? 'true' : 'false' }}"
                            class="min-h-10 rounded-full px-4 text-sm font-semibold {{ $view === $value ? 'bg-ink font-extrabold text-white' : 'text-muted' }}">{{ $text }}</button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($switcher && $view === 'par')
        <p class="mt-3 flex items-center gap-2 rounded-2xl bg-[#FFF1D6] px-4 py-2.5 text-sm font-semibold text-[#6B4600]" data-testid="readonly-badge">
            Você está vendo os dados de {{ $partner?->firstName() ?? 'seu par' }}. Somente leitura.
        </p>
    @endif
</header>
