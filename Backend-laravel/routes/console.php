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

// Colas (QUEUE_CONNECTION=database): el hosting compartido no tiene systemd/Supervisor
// (sin acceso root, ver docs/COLAS.md) para mantener vivo un `queue:work` como daemon --
// si se cayera nadie lo reinicia. En su lugar se usa el patron estandar de Laravel para
// este escenario: un `queue:work` efimero que el mismo cron que ya dispara `schedule:run`
// cada minuto relanza, procesa lo que haya encolado (sii, reportes, inventario, default)
// y se apaga solo -- por cola vacia (--stop-when-empty) o por tiempo (--max-time=58,
// deja margen antes de que el cron del minuto siguiente dispare otro). runInBackground()
// evita que esto bloquee al resto de las tareas programadas ese mismo minuto;
// withoutOverlapping() evita que un run que se demore se pise con el del minuto siguiente.
Schedule::command('queue:work', [
    '--queue' => 'sii,reportes,inventario,default',
    '--stop-when-empty',
    '--max-time' => 58,
    '--tries' => 3,
    '--sleep' => 1,
])
    ->everyMinute()
    ->name('queue-work-supervisado')
    ->onOneServer()
    ->withoutOverlapping(2)
    ->runInBackground();

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
