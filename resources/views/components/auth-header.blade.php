@props([
    'title',
    'description' => null,
])

<div class="flex w-full flex-col gap-1">
    <h2 class="text-xl font-extrabold">{{ $title }}</h2>
    @if ($description)
        <p class="text-sm text-muted">{{ $description }}</p>
    @endif
</div>
