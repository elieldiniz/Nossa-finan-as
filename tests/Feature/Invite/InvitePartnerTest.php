<?php

use App\Livewire\Invite\InvitePartner;
use App\Mail\PartnerInvitation;
use App\Models\User;
use App\Services\CoupleBootstrap;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Livewire\Volt\Volt;

function conviteDe(User $quem, string $email = 'ana@example.com'): string
{
    Mail::fake();
    Livewire::actingAs($quem)->test(InvitePartner::class)->set('email', $email)->call('send')->assertHasNoErrors();

    $url = null;
    Mail::assertSent(PartnerInvitation::class, function ($mail) use (&$url) {
        $url = $mail->url;

        return true;
    });

    return $url;
}

it('envia o convite por e-mail com link assinado', function () {
    $eliel = User::factory()->create();
    app(CoupleBootstrap::class)->createFor($eliel);

    $url = conviteDe($eliel->fresh());

    expect($url)->toContain('/convite/')->toContain('signature=');
});

it('depois do aceite os dois ficam no mesmo casal e veem os mesmos dados', function () {
    $eliel = User::factory()->create();
    app(CoupleBootstrap::class)->createFor($eliel);
    $eliel = $eliel->fresh();
    $url = conviteDe($eliel);
    $token = basename(parse_url($url, PHP_URL_PATH));

    $ana = app(InvitationService::class)->accept($token, ['name' => 'Ana', 'password' => 'senha-forte-123']);

    expect($ana->couple_id)->toBe($eliel->couple_id)
        ->and($eliel->partner()->id)->toBe($ana->id)
        ->and($ana->partner()->id)->toBe($eliel->id);
});

it('o link não pode ser reutilizado', function () {
    $eliel = User::factory()->create();
    app(CoupleBootstrap::class)->createFor($eliel);
    $token = basename(parse_url(conviteDe($eliel->fresh()), PHP_URL_PATH));
    $service = app(InvitationService::class);

    $service->accept($token, ['name' => 'Ana', 'password' => 'senha-forte-123']);

    expect(fn () => $service->accept($token, ['name' => 'Outra', 'password' => 'senha-forte-123']))
        ->toThrow(InvalidArgumentException::class);
});

it('convite expirado não dá acesso', function () {
    $eliel = User::factory()->create();
    app(CoupleBootstrap::class)->createFor($eliel);
    $token = basename(parse_url(conviteDe($eliel->fresh()), PHP_URL_PATH));

    $this->travel(8)->days();

    expect(fn () => app(InvitationService::class)->accept($token, ['name' => 'Ana', 'password' => 'senha-forte-123']))
        ->toThrow(InvalidArgumentException::class);
});

it('casal com 2 membros recusa novo convite', function () {
    [$eliel] = makeCouple();

    expect(fn () => app(InvitationService::class)->invite($eliel, 'terceiro@example.com'))
        ->toThrow(InvalidArgumentException::class);
});

it('a tela de aceite cria a conta e entra', function () {
    $eliel = User::factory()->create();
    app(CoupleBootstrap::class)->createFor($eliel);
    $token = basename(parse_url(conviteDe($eliel->fresh()), PHP_URL_PATH));

    Volt::test('auth.accept-invite', ['token' => $token])
        ->set('name', 'Ana')
        ->set('password', 'senha-forte-123')
        ->set('password_confirmation', 'senha-forte-123')
        ->call('accept')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'ana@example.com')->first()->couple_id)->toBe($eliel->fresh()->couple_id);
});
