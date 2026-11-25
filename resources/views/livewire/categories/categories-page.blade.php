<div class="mx-auto flex max-w-2xl flex-col gap-4">
    <header><h1 class="text-2xl font-extrabold">Categorias</h1><p class="text-sm text-muted">Valem para os dois. Para remover uma que já tem lançamentos, escolha outra para receber.</p></header>

    <section class="nf-card px-5 py-2" aria-label="Lista de categorias">
        @foreach ($categories as $category)
            <div class="nf-row flex-wrap" wire:key="c-{{ $category->id }}" data-testid="category-{{ $category->id }}">
                <input type="text" wire:model="names.{{ $category->id }}" aria-label="Nome da categoria" class="nf-input !min-h-10 min-w-32 flex-1" />
                <button type="button" wire:click="rename({{ $category->id }})" class="nf-chip">Renomear</button>
                <select wire:model="moveTo.{{ $category->id }}" aria-label="Mover lançamentos para" class="nf-input !min-h-10 !w-36"><option value="">Mover para…</option>@foreach ($categories as $other)@if ($other->id !== $category->id)<option value="{{ $other->id }}">{{ $other->name }}</option>@endif @endforeach</select>
                <button type="button" wire:click="remove({{ $category->id }})" class="nf-chip !text-neg">Remover</button>
            </div>
        @endforeach
    </section>

    @if ($error) <p role="alert" class="text-sm font-bold text-neg" data-testid="form-error">{{ $error }}</p> @endif

    <form wire:submit="add" class="nf-card flex items-end gap-3 p-5" aria-label="Nova categoria">
        <div class="flex-1"><label for="newcat" class="nf-label">Nova categoria</label><input id="newcat" type="text" wire:model="newName" class="nf-input" /></div>
        <button type="submit" class="nf-btn">Adicionar</button>
    </form>
</div>
