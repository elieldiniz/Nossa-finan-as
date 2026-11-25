<div class="mx-auto flex max-w-xl flex-col gap-4">
    <header class="flex items-center gap-3">
        <span class="flex size-12 items-center justify-center rounded-full bg-lime text-lg font-extrabold text-ink">{{ auth()->user()->initials() }}</span>
        <div><h1 class="text-xl font-extrabold">{{ auth()->user()->name }}</h1><p class="text-sm text-muted">Casal com {{ auth()->user()->partner()?->name ?? 'seu par (convide!)' }}</p></div>
    </header>

    <nav class="nf-card px-5 py-2" aria-label="Mais opções" data-testid="more-items">
        @foreach ($items as [$route, $label, $icon])
            <a href="{{ route($route) }}" wire:navigate class="nf-row"><span class="flex size-10 items-center justify-center rounded-2xl bg-brand-soft text-brand"><x-nav.icon :name="$icon" class="size-5" /></span><span class="flex-1 text-sm font-bold">{{ $label }}</span><span class="text-muted" aria-hidden="true">›</span></a>
        @endforeach
    </nav>

    <section class="nf-card px-5 py-2">
        <a href="{{ route('settings.profile') }}" wire:navigate class="nf-row"><span class="flex-1 text-sm font-bold">Meu perfil e senha</span><span class="text-muted" aria-hidden="true">›</span></a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="nf-row w-full text-left text-sm font-bold text-neg"><x-nav.icon name="out" class="size-5" /> <span class="flex-1">Sair</span></button></form>
    </section>
</div>
