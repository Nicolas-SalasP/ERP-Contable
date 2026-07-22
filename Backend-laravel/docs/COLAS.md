# Colas (queues) — Tenri ERP

`QUEUE_CONNECTION=database` (ver `.env.example`), con `failed_jobs` para dead-letter basico
(`php artisan queue:failed` / `queue:retry`). Colas en uso hoy: `sii`, `reportes`,
`inventario` y `default` (jobs sin `onQueue()` explicito, ej. envio de correos de
factura/cotizacion, notificaciones de alertas).

## El problema: no hay daemon en el hosting compartido

`queue:work` como proceso persistente (lo que corre `composer dev` en local via
`queue:listen`) necesita algo que lo reinicie si muere — un crash, un OOM, o el propio
deploy, que reemplaza el symlink de la release y deja cualquier proceso viejo apuntando a
codigo que ya no existe. En un VPS eso lo resuelve systemd o Supervisor, pero este hosting
es compartido tipo DirectAdmin (mismo hosting del cron de `schedule:run`, ver
`docs/BACKUPS.md`) **sin acceso root** — no hay forma de instalar un servicio del sistema
operativo ni un supervisor de procesos.

## La solucion: `queue:work --stop-when-empty` programado en el scheduler

En vez de un daemon persistente, `routes/console.php` programa un `queue:work` **efimero**
que corre cada minuto, colgado del mismo cron que ya dispara `schedule:run`
(`docs/BACKUPS.md` explica como se activa ese cron en el panel):

```php
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
```

Este es el patron oficial de Laravel para colas en shared hosting sin daemon
(https://laravel.com/docs/queues#supervisor-configuration, seccion de alternativas sin
Supervisor). Como se comporta:

- **Cada minuto** el cron dispara `schedule:run`, que a su vez lanza este `queue:work`.
- Procesa las colas en el orden listado (`sii` primero, `default` al final) hasta vaciarlas
  (`--stop-when-empty`) o hasta 58 segundos (`--max-time`) — lo que ocurra primero. El
  proceso termina solo; no queda nada corriendo en background esperando el proximo job.
- Si el minuto siguiente el cron intenta lanzar otro antes de que el anterior termine,
  `withoutOverlapping(2)` lo evita (lock con expiracion de 2 minutos, por si el proceso
  muriera a la fuerza y no liberara el lock el solo).
- `runInBackground()` hace que `schedule:run` no se quede esperando a que `queue:work`
  termine — asi no retrasa las otras tareas programadas ese mismo minuto (backup diario,
  `CalcularAlertasInventarioJob` cada 15 min, monitoreo de certificados SII, etc.).
- Al ser un proceso nuevo cada minuto (no un daemon de larga duracion), tambien evita el
  problema clasico de memory leak de `queue:work` persistente sin `--max-time`, y siempre
  corre con el codigo de la release activa (symlink), nunca con una release vieja
  reemplazada por un deploy.

**Trade-off aceptado**: hay hasta ~60 segundos de latencia entre que un job se encola y que
se procesa (el peor caso es que se encole justo despues de que el `queue:work` del minuto
anterior ya arranco). Para el volumen y la naturaleza de los jobs actuales (envio de
correos, alertas, reportes, polling SII que de por si corre cada 5 min) esa latencia es
aceptable — si en el futuro se necesita procesamiento casi inmediato, la alternativa es
migrar a un plan/VPS con acceso a Supervisor.

## Que monitorear

- `php artisan queue:failed` — jobs que agotaron sus `--tries` y cayeron a `failed_jobs`.
  No hay alerta automatica todavia (a diferencia de `backup:monitor`); revisar
  periodicamente o `queue:retry all` tras investigar la causa.
- `storage/logs/laravel.log` — si el `queue:work` programado nunca corre (cron no
  configurado o roto), los jobs se acumulan en la tabla `jobs` sin procesarse y sin error
  visible; para diagnosticar, `SELECT COUNT(*) FROM jobs` creciendo sin bajar es la señal.
- `php artisan schedule:list` — confirma que `queue-work-supervisado` esta programado y
  ver su proxima ejecucion.
