<?php

use App\Livewire\NotificationBell;
use App\Notifications\DueBillsTodayMail;
use App\Services\DueNotifier;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-25 08:00')));

it('avisa só as contas que vencem hoje, não pagas, e não repete', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['name' => 'Gazin', 'month' => '2026-11-01', 'due_on' => '2026-11-25', 'amount_cents' => 22000]);
    makeEntry($eliel, ['name' => 'Amanhã', 'month' => '2026-11-01', 'due_on' => '2026-11-26']);
    makeEntry($eliel, ['name' => 'Já paga', 'month' => '2026-11-01', 'due_on' => '2026-11-25', 'paid' => true]);
    $this->actingAs($eliel);

    $notifier = app(DueNotifier::class);

    expect($notifier->syncToday($eliel))->toBe(1)
        ->and($notifier->syncToday($eliel))->toBe(0)
        ->and($eliel->unreadNotifications()->count())->toBe(1)
        ->and($eliel->unreadNotifications()->first()->data['name'])->toBe('Gazin');
});

it('cada um é avisado das contas dele; a fatura do cartão vai para os dois', function () {
    [$eliel, $ana] = makeCouple();
    makeEntry($eliel, ['name' => 'Do Eliel', 'month' => '2026-11-01', 'due_on' => '2026-11-25']);
    makeEntry($ana, ['name' => 'Da Ana', 'month' => '2026-11-01', 'due_on' => '2026-11-25']);
    $card = makeCard($eliel);
    $card->update(['due_day' => 25]);
    makeEntry($ana, ['card_id' => $card->id, 'month' => '2026-10-01', 'amount_cents' => 50000]);

    $this->actingAs($eliel);
    app(DueNotifier::class)->syncToday($eliel);
    $this->actingAs($ana);
    app(DueNotifier::class)->syncToday($ana);

    expect($eliel->unreadNotifications->pluck('data.name')->sort()->values()->all())->toBe(['Do Eliel', 'Fatura Bradesco'])
        ->and($ana->unreadNotifications->pluck('data.name')->sort()->values()->all())->toBe(['Da Ana', 'Fatura Bradesco']);
});

it('conta paga por outro caminho some do sininho', function () {
    [$eliel] = makeCouple();
    $conta = makeEntry($eliel, ['name' => 'Luz', 'month' => '2026-11-01', 'due_on' => '2026-11-25']);
    $this->actingAs($eliel);
    app(DueNotifier::class)->syncToday($eliel);
    expect($eliel->unreadNotifications()->count())->toBe(1);

    $conta->update(['paid' => true]);
    app(DueNotifier::class)->resolvePaid($eliel);

    expect($eliel->unreadNotifications()->count())->toBe(0);
});

it('sininho mostra o aviso e o número; tocar no aviso o faz sumir e leva ao Início', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['name' => 'Parcela da casa', 'month' => '2026-11-01', 'due_on' => '2026-11-25', 'amount_cents' => 137000]);

    $tela = Livewire::actingAs($eliel)->test(NotificationBell::class)
        ->assertSeeHtml('data-testid="bell-count">1<')->assertSee('Parcela da casa')->assertSee('vence hoje · R$ 1.370,00');

    $id = $eliel->unreadNotifications()->first()->id;
    $tela->call('open', $id)->assertRedirect(route('dashboard'));

    expect($eliel->unreadNotifications()->count())->toBe(0);
    Livewire::actingAs($eliel)->test(NotificationBell::class)->assertDontSeeHtml('data-testid="bell-count"')->assertSee('Nenhum aviso');
});

it('"Limpar todos" dá todos como vistos', function () {
    [$eliel] = makeCouple();
    makeEntry($eliel, ['name' => 'A', 'month' => '2026-11-01', 'due_on' => '2026-11-25']);
    makeEntry($eliel, ['name' => 'B', 'month' => '2026-11-01', 'due_on' => '2026-11-25']);

    Livewire::actingAs($eliel)->test(NotificationBell::class)->assertSeeHtml('data-testid="bell-count">2<')->call('markAllRead')->assertDontSeeHtml('data-testid="bell-count"');
});

it('liga e desliga o aviso por e-mail pelo sininho', function () {
    [$eliel] = makeCouple();

    expect($eliel->notify_email)->toBeTrue();
    Livewire::actingAs($eliel)->test(NotificationBell::class)->assertSee('Ligado')->call('toggleEmail')->assertSee('Desligado');
    expect($eliel->fresh()->notify_email)->toBeFalse();
});

it('o sininho aparece nas telas do app', function () {
    [$eliel] = makeCouple();

    $this->actingAs($eliel)->get('/dashboard')->assertSee('data-testid="bell"', false);
    $this->actingAs($eliel)->get('/extrato')->assertSee('data-testid="bell"', false);
});

function emailsEnviados(): array
{
    return collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => [
        'to' => $m->getOriginalMessage()->getTo()[0]->getAddress(),
        'subject' => $m->getOriginalMessage()->getSubject(),
        'body' => $m->getOriginalMessage()->getHtmlBody(),
    ])->all();
}

it('comando envia um e-mail por pessoa com as contas de hoje e não repete', function () {
    [$eliel, $ana] = makeCouple();
    makeEntry($eliel, ['name' => 'Gazin', 'month' => '2026-11-01', 'due_on' => '2026-11-25', 'amount_cents' => 22000]);
    makeEntry($eliel, ['name' => 'Havan', 'month' => '2026-11-01', 'due_on' => '2026-11-25', 'amount_cents' => 16300]);
    makeEntry($ana, ['name' => 'Da Ana', 'month' => '2026-11-01', 'due_on' => '2026-11-25']);
    $ana->forceFill(['notify_email' => false])->save();

    $this->artisan('contas:avisar')->assertSuccessful();
    $emails = emailsEnviados();

    expect($emails)->toHaveCount(1)->and($emails[0]['to'])->toBe($eliel->email)->and($emails[0]['subject'])->toBe('Vencem hoje: 2 contas')
        ->and($emails[0]['body'])->toContain('Gazin')->toContain('R$ 220,00')->toContain('Havan')->not->toContain('Da Ana');

    // rodar de novo no mesmo dia não manda outro
    $this->artisan('contas:avisar')->assertSuccessful();
    expect(emailsEnviados())->toHaveCount(1);

    // o sininho da Ana continua funcionando mesmo sem e-mail
    expect($ana->unreadNotifications()->count())->toBe(1);
});

it('o e-mail traz assunto e lista das contas', function () {
    [$eliel] = makeCouple();
    $mail = (new DueBillsTodayMail([['name' => 'Gazin', 'amount' => 22000]]))->toMail($eliel);

    expect($mail->subject)->toBe('Vence hoje: Gazin')->and(implode(' ', $mail->introLines))->toContain('Gazin')->toContain('R$ 220,00');
});
