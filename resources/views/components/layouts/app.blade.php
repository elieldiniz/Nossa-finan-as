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

        @fluxScripts
    </body>
</html>
