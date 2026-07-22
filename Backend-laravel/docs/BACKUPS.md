# Backups y restauración — Tenri ERP

Backups con [`spatie/laravel-backup`](https://spatie.be/docs/laravel-backup).

## Configuración

- Config: `config/backup.php`. Disco destino vía `BACKUP_DESTINATION_DISK` (default `local`),
  aviso de fallos por mail vía `BACKUP_NOTIFICATION_EMAIL`.
- Dump de MySQL: en prod (Linux) `mysqldump` está en el PATH. En hosts donde no
  (p. ej. XAMPP/Windows) se indica con `DB_DUMP_BINARY_PATH` (ver `.env.example`).

## Comandos

```bash
php artisan backup:run            # backup completo (BD + archivos)
php artisan backup:run --only-db  # solo base de datos
php artisan backup:list           # estado y tamaño de los backups
php artisan backup:clean          # aplica la política de retención
```

Programado en `routes/console.php` (desde 2026-07-14): `backup:run` diario a las 03:00,
`backup:clean` a las 03:30, `backup:monitor` a las 04:00 (avisa por mail si el backup del
día no existe o supera el tamaño esperado — `config/backup.php:monitor_backups`).

**Esto no hace nada solo: requiere una entrada de cron real en el servidor de hosting**
apuntando al scheduler de Laravel. Sin eso, ni este backup ni ninguna otra tarea programada
del proyecto (alertas de inventario, monitoreo de certificados SII, polling de envíos SII)
corre en producción — Laravel no tiene su propio daemon, `Schedule::` solo define QUÉ correr,
algo externo tiene que ejecutar `schedule:run` cada minuto.

### Activar el cron en el hosting (DirectAdmin/cPanel)

1. Entrar al panel de hosting (DirectAdmin) → sección **Cron Jobs**.
2. Agregar una tarea nueva con esta configuración:
   - **Minuto**: `*` (cada minuto)
   - **Hora / día / mes / día de semana**: `*` (todos)
   - **Comando**:
     ```bash
     cd /ruta/real/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
     ```
     (reemplazar `/ruta/real/al/proyecto` por el path del symlink `$APP_LINK` del deploy
     atómico — ver `docs/deploy-atomico.md` — NO el path de una release versionada
     especifica, porque esa cambia en cada deploy).
3. Guardar. Confirmar que corre: esperar 1-2 minutos y revisar
   `storage/logs/laravel.log`, o correr `php artisan schedule:list` por SSH para ver los
   próximos horarios, y `php artisan backup:run` a mano una vez para confirmar que el zip
   aparece en el disco destino.
4. Si el hosting no da acceso a un panel de cron (algunos planes compartidos no lo
   incluyen), alternativa: pedir soporte del hosting que agregue la línea de crontab
   directamente, o migrar a un plan/VPS que sí lo permita — no hay forma de que Laravel
   se auto-programe sin un cron del sistema operativo por debajo.

## Prueba de restauración (obligatoria)

Un backup no cuenta como válido hasta verificar que **restaura**. Procedimiento verificado
en local (2026-06-04) contra MySQL `tenri_erp_db`:

1. `php artisan backup:run --only-db` → zip creado (28.4 KB) en el disco destino con
   `db-dumps_mysql-tenri_erp_db.sql`.
2. Se extrajo el dump y se importó en una base scratch:
   ```bash
   mysql -u root -e "CREATE DATABASE tenri_erp_restore_test;"
   mysql -u root tenri_erp_restore_test < db-dumps_mysql-tenri_erp_db.sql
   ```
3. Verificación de integridad (conteos origen vs restaurado):

   | Tabla     | Origen | Restaurado |
   |-----------|:------:|:----------:|
   | roles     |   5    |     5      |
   | usuarios  |   4    |     4      |
   | empresas  |   3    |     3      |

   **Resultado: idénticos → restauración correcta.** La base scratch se eliminó tras verificar.

Repetir esta verificación tras cambios mayores de esquema o de proveedor de hosting.
