<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('jokiinlah:retention-evaluate --limit=200')
    ->dailyAt('01:10')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('jokiinlah:purge --limit=50')
    ->dailyAt('02:10')
    ->withoutOverlapping(60)
    ->onOneServer();

Schedule::command('jokiinlah:files-reconcile --limit=1000')
    ->weeklyOn(1, '03:10')
    ->withoutOverlapping(120)
    ->onOneServer();
