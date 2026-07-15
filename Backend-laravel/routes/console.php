<?php

use App\Domains\Inventario\Jobs\CalcularAlertasInventarioJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CalcularAlertasInventarioJob, 'inventario')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

// spatie/laravel-backup estaba instalado y configurado pero nunca programado: sin este
// scheduler, el unico respaldo real era el mysqldump best-effort del deploy (no corre si
// falta el binario, y no corre en absoluto entre deploys). runInBackground() evita que un
// backup lento bloquee el resto de las tareas programadas.
Schedule::command('backup:run')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('backup:clean')
    ->dailyAt('03:30')
    ->withoutOverlapping();

// Verifica que el backup de hoy exista y no supere el tamaño esperado; avisa por mail
// (BACKUP_NOTIFICATION_EMAIL) si no -- sin esto, un backup:run que falla en silencio
// (disco lleno, permisos) no se nota hasta que hace falta restaurar.
Schedule::command('backup:monitor')
    ->dailyAt('04:00');
