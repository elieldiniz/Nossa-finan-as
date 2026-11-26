<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · Nossa Finanças</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-ground font-sans text-ink antialiased">
        <main class="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center gap-4 px-6 text-center">
            <img src="/icons/icon-192.png" alt="" class="size-16 rounded-2xl">
            <p class="text-sm font-extrabold uppercase tracking-wide text-muted">Erro {{ $code }}</p>
            <h1 class="text-2xl font-extrabold">{{ $title }}</h1>
            <p class="text-base text-muted" data-testid="error-message">{{ $message }}</p>
            @isset($hint) <p class="text-sm text-muted">{{ $hint }}</p> @endisset
            <div class="mt-2 flex flex-wrap justify-center gap-2">
                <a href="{{ url('/dashboard') }}" class="nf-btn">Ir para o Início</a>
                <a href="javascript:location.reload()" class="nf-btn-ghost">Tentar de novo</a>
            </div>
        </main>
    </body>
</html>
