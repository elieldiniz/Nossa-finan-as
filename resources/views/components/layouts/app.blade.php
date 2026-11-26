<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-ground font-sans text-ink antialiased">
        <div class="lg:flex">
            <x-nav.sidebar />

            <main class="min-w-0 flex-1 pb-28 lg:pb-12">
                <div class="mx-auto w-full max-w-md px-4 pt-6 lg:max-w-5xl lg:px-10 lg:pt-8">
                    {{ $slot }}
                </div>
            </main>
        </div>

        <x-nav.bottom-bar />

        <div x-data="{ offline: !navigator.onLine }" x-on:online.window="offline = false" x-on:offline.window="offline = true"
             x-show="offline" x-cloak role="status"
             class="fixed inset-x-4 bottom-24 z-50 rounded-2xl bg-ink px-4 py-3 text-center text-sm font-bold text-white lg:bottom-6 lg:left-auto lg:right-6 lg:w-80">
            Sem internet: dá para ver o último mês, mas é necessário internet para lançar.
        </div>

        {{-- Avisos de erro: sempre visíveis e em português (ver resources/js/errors.js) --}}
        <div x-data="{ open: false, text: '', reload: false, timer: null }"
             x-on:nf-toast.window="text = $event.detail.text; reload = $event.detail.reload; open = true; clearTimeout(timer); if (! reload) timer = setTimeout(() => open = false, 12000)"
             x-show="open" x-cloak role="alert" aria-live="assertive" data-testid="toast"
             class="fixed inset-x-4 bottom-24 z-[60] rounded-2xl bg-neg px-4 py-3 text-sm font-bold text-white shadow-lg lg:bottom-6 lg:left-auto lg:right-6 lg:w-96">
            <p x-text="text"></p>
            <div class="mt-2 flex gap-2">
                <button type="button" x-show="reload" x-on:click="location.reload()" class="rounded-xl bg-white px-3 py-1.5 text-xs font-extrabold text-neg">Recarregar</button>
                <button type="button" x-on:click="open = false" class="rounded-xl border border-white/60 px-3 py-1.5 text-xs font-extrabold">Fechar</button>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
