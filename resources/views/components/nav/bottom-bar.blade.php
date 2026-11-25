@php
    $tabs = [
        ['dashboard', 'Início', 'home'],
        ['statement', 'Extrato', 'list'],
        null,
        ['cards', 'Cartões', 'card'],
        ['more', 'Mais', 'dots'],
    ];
    $moreRoutes = ['more', 'people', 'vale', 'recurring', 'settlement', 'goals', 'report', 'import', 'categories', 'invite', 'charts'];
@endphp
<nav aria-label="Navegação" data-testid="bottom-bar"
     class="fixed inset-x-0 bottom-0 z-40 flex h-[84px] items-start border-t border-line bg-white px-2 pt-1.5 lg:hidden">
    @foreach ($tabs as $tab)
        @if ($tab === null)
            <div class="flex flex-1 justify-center">
                <a href="{{ route('entry.create') }}" aria-label="Lançar" wire:navigate
                   class="-mt-7 flex size-[58px] items-center justify-center rounded-full border-4 border-ground bg-brand text-white shadow-[0_10px_22px_rgba(20,97,79,.4)]">
                    <x-nav.icon name="plus" class="size-7" />
                </a>
            </div>
        @else
            @php
                [$route, $label, $icon] = $tab;
                $current = $route === 'more' ? request()->routeIs($moreRoutes) : request()->routeIs($route);
            @endphp
            <a href="{{ route($route) }}" wire:navigate @if($current) aria-current="page" @endif
               class="flex flex-1 flex-col items-center gap-0.5 pt-1.5 text-xs font-semibold {{ $current ? 'font-extrabold text-brand' : 'text-muted' }}">
                <x-nav.icon :name="$icon" />
                <span>{{ $label }}</span>
            </a>
        @endif
    @endforeach
</nav>
