@php
    $items = [
        ['dashboard', 'Início', 'home'],
        ['statement', 'Extrato', 'list'],
        ['cards', 'Cartões', 'card'],
        ['people', 'Pessoas', 'people'],
        ['vale', 'Vale', 'vale'],
        ['recurring', 'Recorrentes', 'repeat'],
        ['settlement', 'Acerto do casal', 'swap'],
        ['goals', 'Metas e vencimentos', 'target'],
        ['charts', 'Gráficos', 'chart'],
        ['report', 'Relatório', 'pie'],
        ['categories', 'Categorias', 'tag'],
        ['import', 'Importar planilha', 'upload'],
        ['invite', 'Convidar par', 'invite'],
    ];
@endphp
<aside aria-label="Menu lateral" data-testid="sidebar"
       class="hidden w-64 shrink-0 flex-col gap-1 bg-ink px-4 py-7 lg:flex lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto">
    <a href="{{ route('dashboard') }}" wire:navigate class="mb-5 flex items-center gap-2.5 px-2 text-lg font-extrabold text-white">
        <x-app-logo-icon class="size-10 rounded-xl" />
        Nossa Finanças
    </a>

    @foreach ($items as [$route, $label, $icon])
        @php $current = request()->routeIs($route); @endphp
        <a href="{{ route($route) }}" wire:navigate @if($current) aria-current="page" @endif
           class="flex min-h-11 items-center gap-3 rounded-full px-4 text-sm font-semibold {{ $current ? 'bg-lime font-extrabold text-ink' : 'text-[#C3D0CA] hover:bg-white/10' }}">
            <x-nav.icon :name="$icon" class="size-5" />
            {{ $label }}
        </a>
    @endforeach

    <div class="flex-1"></div>

    <div class="rounded-2xl bg-white/10 p-3 text-white">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-full bg-lime font-extrabold text-ink">{{ auth()->user()->initials() }}</span>
            <div class="min-w-0"><div class="truncate text-sm font-extrabold">{{ auth()->user()->name }}</div><div class="text-xs text-[#C3D0CA]">Casal com {{ auth()->user()->partner()?->name ?? 'seu par' }}</div></div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">
            @csrf
            <button type="submit" class="flex min-h-10 w-full items-center gap-2 rounded-full px-3 text-sm font-semibold text-[#C3D0CA] hover:bg-white/10"><x-nav.icon name="out" class="size-5" />Sair</button>
        </form>
    </div>
</aside>
