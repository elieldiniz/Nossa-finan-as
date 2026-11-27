<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-ground font-sans text-ink antialiased">
        <div class="min-h-svh lg:grid lg:grid-cols-2">
            {{-- Faixa da marca: no celular fica no topo; no PC ocupa a metade da esquerda --}}
            <header class="relative overflow-hidden rounded-b-[40px] bg-gradient-to-br from-brand-dark to-brand-mid px-7 pb-12 pt-14 text-white lg:flex lg:min-h-svh lg:flex-col lg:justify-center lg:rounded-none lg:px-16">
                <span aria-hidden="true" class="absolute -right-16 -top-10 size-56 rounded-full bg-lime/15"></span>
                <span aria-hidden="true" class="absolute right-10 top-40 size-24 rounded-full bg-white/10"></span>

                <a href="{{ route('home') }}" wire:navigate class="relative block w-fit" aria-label="Nossa Finanças">
                    <x-app-logo-icon class="size-16 rounded-2xl shadow-lg" />
                </a>
                <h1 class="relative mt-7 text-4xl font-extrabold leading-[1.1] tracking-tight lg:text-5xl">Nossa<br>Finanças</h1>
                <p class="relative mt-3 max-w-xs text-base text-white/90">O mês do casal em um só lugar.</p>
            </header>

            <main class="mx-auto flex w-full max-w-md flex-col justify-center px-6 py-8 lg:max-w-lg lg:px-12">
                {{ $slot }}
            </main>
        </div>
        @fluxScripts
    </body>
</html>
