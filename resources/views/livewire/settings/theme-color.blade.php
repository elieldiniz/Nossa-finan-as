<section class="my-8 w-full" aria-label="Cor do app" data-testid="theme-color">
    <h2 class="text-base font-semibold">Cor do app</h2>
    <p class="mt-1 text-sm text-zinc-500">Escolha a cor das telas. Vale só para a sua conta.</p>

    <div role="radiogroup" aria-label="Cor do app" class="mt-3 grid grid-cols-4 gap-3 sm:grid-cols-7">
        @foreach ($options as $key => $palette)
            <button type="button" role="radio" aria-checked="{{ $color === $key ? 'true' : 'false' }}" wire:click="choose('{{ $key }}')" data-testid="theme-{{ $key }}"
                    class="flex flex-col items-center gap-1.5 rounded-2xl border-2 p-2 text-xs font-bold {{ $color === $key ? 'border-ink' : 'border-transparent' }}">
                <span class="flex size-12 items-center justify-center rounded-full text-white" style="background: linear-gradient(135deg, {{ $palette['dark'] }}, {{ $palette['brand'] }} 55%, {{ $palette['mid'] }})">
                    @if ($color === $key) <span aria-hidden="true" class="text-lg">✓</span> @endif
                </span>
                {{ $palette['label'] }}
            </button>
        @endforeach
    </div>
    @error('color') <p class="mt-2 text-sm font-bold text-red-600">{{ $message }}</p> @enderror
</section>
