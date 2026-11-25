<?php

use App\Livewire\Cards\CardsPage;
use App\Livewire\Categories\CategoriesPage;
use App\Livewire\Charts\ChartsPage;
use App\Livewire\Entry\EditEntry;
use App\Livewire\Entry\EntryForm;
use App\Livewire\Goals\GoalsPage;
use App\Livewire\Home\HomePage;
use App\Livewire\Import\ImportPage;
use App\Livewire\Invite\InvitePartner;
use App\Livewire\More\MorePage;
use App\Livewire\People\PeoplePage;
use App\Livewire\Recurring\RecurringPage;
use App\Livewire\Reports\ReportPage;
use App\Livewire\Settlement\SettlementPage;
use App\Livewire\Statement\StatementPage;
use App\Livewire\Vale\ValePage;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', HomePage::class)->name('dashboard');
    Route::get('extrato', StatementPage::class)->name('statement');
    Route::get('lancar', EntryForm::class)->name('entry.create');
    Route::get('lancamentos/{entry}', EditEntry::class)->name('entry.show');
    Route::get('cartoes', CardsPage::class)->name('cards');
    Route::get('pessoas', PeoplePage::class)->name('people');
    Route::get('vale', ValePage::class)->name('vale');
    Route::get('recorrentes', RecurringPage::class)->name('recurring');
    Route::get('acerto', SettlementPage::class)->name('settlement');
    Route::get('metas', GoalsPage::class)->name('goals');
    Route::get('graficos', ChartsPage::class)->name('charts');
    Route::get('relatorio', ReportPage::class)->name('report');
    Route::get('categorias', CategoriesPage::class)->name('categories');
    Route::get('mais', MorePage::class)->name('more');
    Route::get('importar', ImportPage::class)->name('import');
});

Volt::route('convite/{token}', 'auth.accept-invite')
    ->middleware(['guest', 'signed'])
    ->name('invitation.accept');

Route::middleware(['auth'])->group(function () {
    Route::get('convidar', InvitePartner::class)->name('invite');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
});

require __DIR__.'/auth.php';
