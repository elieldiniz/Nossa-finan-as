<?php

namespace App\Providers;

use App\Models;
use App\Policies;
use App\Support\Money;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            Models\Entry::class => Policies\EntryPolicy::class,
            Models\Recurrence::class => Policies\RecurrencePolicy::class,
            Models\PersonDebt::class => Policies\PersonDebtPolicy::class,
            Models\Vale::class => Policies\ValePolicy::class,
            Models\ValeSpend::class => Policies\ValeSpendPolicy::class,
            Models\Category::class => Policies\CategoryPolicy::class,
            Models\Card::class => Policies\CardPolicy::class,
            Models\Person::class => Policies\PersonPolicy::class,
            Models\Goal::class => Policies\GoalPolicy::class,
            Models\Settlement::class => Policies\SettlementPolicy::class,
            Models\CardInvoice::class => Policies\CardInvoicePolicy::class,
        ] as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // @brl($centavos) → "R$ 2.250,00"
        Blade::directive('brl', fn (string $expression) => '<?php echo e(\\'.Money::class."::format((int) ({$expression}))); ?>");
        // @brls($centavos) → "+R$ 115,00" / "−R$ 485,00"
        Blade::directive('brls', fn (string $expression) => '<?php echo e(\\'.Money::class."::signed((int) ({$expression}))); ?>");
    }
}
