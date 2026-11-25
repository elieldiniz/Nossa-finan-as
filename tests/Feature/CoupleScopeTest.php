<?php

use App\Models\Category;
use App\Models\Entry;

it('só enxerga registros do próprio casal', function () {
    [$a, $b] = makeCouple();
    [$c] = makeCouple('Carlos', 'Dora');
    makeEntry($a, ['name' => 'do casal 1']);
    makeEntry($c, ['name' => 'do casal 2']);

    $this->actingAs($a);
    expect(Entry::pluck('name')->all())->toBe(['do casal 1']);

    $this->actingAs($b);
    expect(Entry::pluck('name')->all())->toBe(['do casal 1']);

    $this->actingAs($c);
    expect(Entry::pluck('name')->all())->toBe(['do casal 2'])
        ->and(Category::count())->toBe(14);
});

it('sem usuário logado não enxerga nada', function () {
    [$a] = makeCouple();
    makeEntry($a);

    expect(Entry::count())->toBe(0);
});

it('o couple_id é definido no servidor, mesmo que o cliente mande outro', function () {
    [$a] = makeCouple();
    [$c, , $outro] = makeCouple('Carlos', 'Dora');
    $this->actingAs($a);

    $entry = Entry::create([
        'owner_id' => $a->id, 'kind' => 'saida', 'name' => 'x', 'amount_cents' => 100, 'month' => '2026-09-01',
        'category_id' => categoryOf($a->couple_id)->id, 'couple_id' => $outro->id,
    ]);

    expect($entry->couple_id)->toBe($a->couple_id);
});
