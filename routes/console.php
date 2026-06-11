<?php

use Illuminate\Support\Facades\Schedule;

// Cola de impresión: procesa pendientes cada minuto (sin solapamiento)
Schedule::command('print:process')->everyMinute()->withoutOverlapping();
