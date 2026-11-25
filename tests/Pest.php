<?php

use App\Models\Card;
use App\Models\Category;
use App\Models\Couple;
use App\Models\Entry;
use App\Models\Person;
use App\Models\User;
use App\Models\Vale;
use App\Services\CoupleBootstrap;
use App\Services\EntryService;
use App\Services\PersonDebtService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Helpers do Nossa Finanças
|--------------------------------------------------------------------------
*/

/** Cria um casal com 2 usuários e as categorias iniciais: [$eliel, $ana, $couple]. */
function makeCouple(string $first = 'Eliel', string $second = 'Ana'): array
{
    $a = User::factory()->create(['name' => $first]);
    $couple = app(CoupleBootstrap::class)->createFor($a);
    $b = User::factory()->create(['name' => $second, 'couple_id' => $couple->id]);

    return [$a->fresh(), $b, $couple];
}

function categoryOf(Couple|int $couple, string $name = 'Outros'): Category
{
    $id = $couple instanceof Couple ? $couple->id : $couple;

    return Category::withoutGlobalScopes()->where('couple_id', $id)->where('name', $name)->firstOrFail();
}

/** Cria um lançamento direto no banco (sem passar pelas regras de tela). */
function makeEntry(User $owner, array $attrs = []): Entry
{
    $entry = new Entry($attrs + [
        'owner_id' => $owner->id,
        'kind' => 'saida',
        'name' => 'Item',
        'amount_cents' => 10000,
        'month' => '2026-09-01',
        'category_id' => categoryOf($owner->couple_id)->id,
    ]);
    $entry->couple_id = $owner->couple_id;
    $entry->saveQuietly();

    return $entry;
}

function makeCard(User $owner, string $name = 'Bradesco'): Card
{
    $card = new Card(['name' => $name, 'owner_id' => $owner->id]);
    $card->couple_id = $owner->couple_id;
    $card->saveQuietly();

    return $card;
}

function makePerson(User $owner, string $name = 'Madrinha'): Person
{
    $person = new Person(['name' => $name]);
    $person->couple_id = $owner->couple_id;
    $person->saveQuietly();

    return $person;
}

function makeVale(User $owner, int $cents = 225000): Vale
{
    $vale = new Vale(['owner_id' => $owner->id, 'amount_cents' => $cents]);
    $vale->couple_id = $owner->couple_id;
    $vale->saveQuietly();

    return $vale;
}

function fixo(User $eliel, array $repeat = ['frequency' => 'monthly'], int $valor = 30000, string $mes = '2026-01-01'): Entry
{
    return app(EntryService::class)->create($eliel, [
        'kind' => 'saida', 'name' => 'Fixo', 'amount_cents' => $valor, 'month' => $mes,
        'category_id' => categoryOf($eliel->couple_id, 'Moradia')->id, 'repeat' => $repeat,
    ]);
}

/** O mês de Setembro da planilha: Madrinha +115, Bradesco 2 fixos de 300, Vale 2.250. */
function cenarioPlanilha(): array
{
    [$eliel, $ana] = makeCouple();
    test()->actingAs($eliel);
    $setembro = CarbonImmutable::parse('2026-09-01');

    $bradesco = makeCard($eliel);
    foreach ([1, 2] as $i) {
        app(EntryService::class)->create($eliel, [
            'kind' => 'saida', 'name' => 'Fixo', 'amount_cents' => 30000, 'month' => '2026-09-01',
            'category_id' => categoryOf($eliel->couple_id, 'Moradia')->id, 'card_id' => $bradesco->id,
        ]);
    }

    $madrinha = makePerson($eliel);
    $service = app(PersonDebtService::class);
    $service->create($ana, $madrinha, 'pagar', 30000, $setembro);
    $service->create($ana, $madrinha, 'receber', 41500, $setembro);
    makePerson($eliel, 'Glória');
    makePerson($eliel, 'Mãe');
    makeVale($eliel);

    return [$eliel, $ana, $setembro];
}
