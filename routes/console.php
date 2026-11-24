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

// Aviso "tienes una auditoría": unos días antes de la fecha que fijó el auditor (auditorias.aviso_dias_antes).
Schedule::command('audits:notify-upcoming')
    ->dailyAt('07:30');

// Hallazgos con el plazo de su etapa vencido (días hábiles): aviso a quien le toca y a "Avisos de auditorías".
Schedule::command('audits:findings-overdue')
    ->dailyAt('08:15');