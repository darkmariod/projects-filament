<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cola de impresión: procesa pendientes cada minuto (sin solapamiento)
Schedule::command('print:process')->everyMinute()->withoutOverlapping();
