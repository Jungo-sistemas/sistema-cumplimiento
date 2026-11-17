<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('requirements:expire')
    ->dailyAt('00:05');

Schedule::command('requirements:notify-due-soon')
    ->dailyAt('08:00');

Schedule::command('documents:purge-trash')
    ->dailyAt('01:00');

Schedule::command('licenses:check')
    ->dailyAt('07:00');

Schedule::command('approvals:notify-reminders')
    ->dailyAt('08:00');

// Aviso "este mes te toca una auditoría" (el día 1, y luego solo las que se programen ya empezado el mes).
Schedule::command('audits:notify-month')
    ->dailyAt('07:30');