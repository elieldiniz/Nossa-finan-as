<?php

use App\Models\Card;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Person;
use App\Models\PersonDebt;
use App\Models\Recurrence;
use App\Models\User;
use App\Models\Vale;
use App\Models\ValeSpend;
use App\Services\AccountResetter;
use App\Services\ImportService;
use Carbon\CarbonImmutable;

function dados(User $u): void
{
    $card = makeCard($u, 'Nubank');
    makeEntry($u, ['name' => 'Luz', 'month' => '2026-09-01']);
    makeEntry($u, ['name' => 'Compra', 'month' => '2026-09-01', 'card_id' => $card->id]);
    $primeira = fixo($u, ['frequency' => 'monthly', 'charge_day' => 5], 3000, '2026-09-01');
    $person = makePerson($u, 'Madrinha');
    $debt = new PersonDebt(['person_id' => $person->id, 'owner_id' => $u->id, 'direction' => 'pagar', 'amount_cents' => 5000, 'created_month' => '2026-09-01']);
    $debt->couple_id = $u->couple_id;
    $debt->save();
    $vale = makeVale($u, 100000);
    $spend = new ValeSpend(['vale_id' => $vale->id, 'owner_id' => $u->id, 'name' => 'Mercado', 'amount_cents' => 1000, 'month' => '2026-09-01']);
    $spend->couple_id = $u->couple_id;
    $spend->save();
}

it('apaga tudo o que a pessoa cadastrou e só isso: o do parceiro, a conta, categorias e metas ficam', function () {
    [$eliel, $ana] = makeCouple();
    $this->actingAs($eliel);
    dados($eliel);
    $this->actingAs($ana);
    makeEntry($ana, ['name' => 'Da Ana']);
    $cartaoAna = makeCard($ana, 'Cartão da Ana');
    $this->actingAs($eliel);

    $r = app(AccountResetter::class)->reset($eliel);

    expect($r['cards'])->toBe(1)->and($r['debts'])->toBe(1)->and($r['vale_spends'])->toBe(1)->and($r['recurrences'])->toBe(1)
        ->and(Entry::where('owner_id', $eliel->id)->count())->toBe(0)
        ->and(Recurrence::count())->toBe(0)
        ->and(Card::pluck('name')->all())->toBe(['Cartão da Ana'])
        ->and(PersonDebt::count())->toBe(0)->and(Person::count())->toBe(0)->and(Vale::count())->toBe(0)->and(ValeSpend::count())->toBe(0)
        ->and(Entry::where('name', 'Da Ana')->count())->toBe(1)                 // do parceiro fica
        ->and(Category::count())->toBeGreaterThan(5)                  // categorias ficam
        ->and(User::count())->toBe(2);                                // as contas ficam
});

it('o que já tinha sido excluído antes também some de verdade (o mês fica limpo para importar)', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    $e = makeEntry($eliel, ['name' => 'Apagada antes', 'month' => '2026-09-01']);
    $e->delete();
    expect(Entry::withTrashed()->count())->toBe(1);

    app(AccountResetter::class)->reset($eliel);

    expect(Entry::withTrashed()->count())->toBe(0);
});

it('pessoa com pendências do parceiro continua cadastrada', function () {
    [$eliel, $ana] = makeCouple();
    $this->actingAs($ana);
    $person = makePerson($ana, 'Madrinha');
    foreach ([$eliel, $ana] as $dono) {
        $d = new PersonDebt(['person_id' => $person->id, 'owner_id' => $dono->id, 'direction' => 'pagar', 'amount_cents' => 1000, 'created_month' => '2026-09-01']);
        $d->couple_id = $eliel->couple_id;
        $d->save();
    }
    $this->actingAs($eliel);

    app(AccountResetter::class)->reset($eliel);

    expect(Person::count())->toBe(1)->and(PersonDebt::count())->toBe(1);
});

it('depois de apagar, o mês pode receber uma importação (excluídos não contam como "mês com dados")', function () {
    [$eliel] = makeCouple();
    $this->actingAs($eliel);
    makeEntry($eliel, ['month' => '2026-09-01']);
    $service = app(ImportService::class);
    expect($service->monthHasData(CarbonImmutable::parse('2026-09-01')))->toBeTrue();

    Entry::query()->delete(); // exclusão comum (suave), como a de "Excluir compras"
    expect($service->monthHasData(CarbonImmutable::parse('2026-09-01')))->toBeFalse()->and($service->monthEntryCount(CarbonImmutable::parse('2026-09-01')))->toBe(0);
});
