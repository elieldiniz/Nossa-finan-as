<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Todo dia cedo: e-mail com as contas que vencem hoje (o servidor precisa rodar `php artisan schedule:run` a cada minuto)
Schedule::command('contas:avisar')->dailyAt('07:30');
