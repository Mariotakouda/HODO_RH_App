<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Absences automatiques en fin de journée (nécessite : php artisan schedule:work, ou une tâche cron
// `* * * * * php artisan schedule:run`).
Schedule::command('app:mark-absences')->dailyAt('23:00')->timezone('Africa/Lome');
