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

Programación recomendada (en `routes/console.php` o el scheduler): `backup:run` diario y
`backup:clean` semanal. Requiere el cron de Laravel (`schedule:run`) activo en el host.

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
