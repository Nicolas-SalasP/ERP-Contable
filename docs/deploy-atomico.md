# Deploy atómico (releases + symlink)

**Fecha:** 2026-07-06
**Decisión tomada vía `/council`** (ver transcript en la sesión de auditoría del 2026-07-06, hallazgo original reportado por el usuario: "el pipeline si falla a mitad del deploy deja archivos a medias").

## Problema original

`.github/workflows/ci-cd.yml` (jobs `deploy` y `deploy-staging`) hacía `unzip -o backend.zip` **directo sobre el directorio en vivo** (`~/erp_backend`), archivo por archivo, sin atomicidad:

- Si el proceso se cortaba a mitad (disco lleno, conexión SSH caída), quedaban archivos de la release nueva mezclados con la vieja — código inconsistente sirviendo tráfico real.
- `php artisan migrate --force` corría después sin ningún resguardo: si el código quedó a medias, migraba con la app en estado inconsistente.
- Sin backup de base de datos antes de migrar.
- Sin ningún mecanismo de rollback si algo fallaba.

## Por qué era viable arreglarlo sin tocar la app

`Backend-laravel/public/index.php` ya resuelve la ruta base de forma **absoluta**, no relativa a `__DIR__`:

```php
$basePath = match(true) {
    str_contains(__DIR__, '/home/atlasdig') => '/home/atlasdig/erp_backend',
    default => __DIR__.'/../',
};
```

Esto significa que `~/erp_backend` puede convertirse en un **symlink** sin tener que tocar `index.php` en cada deploy — la pieza que normalmente hace más difícil este patrón (rutas relativas a la ubicación física del código) ya estaba resuelta de antes.

## Solución implementada: releases + symlink atómico

Mismo patrón que Capistrano/Deployer/Laravel Envoyer, adaptado a hosting compartido por SSH+SCP (sin herramientas de deploy de terceros):

```
~/erp_backend                    → symlink a la release activa
~/erp_backend_releases/
  ├── 20260706120000/            → release vieja
  └── 20260706153000/            → release nueva (recién activada)
~/erp_backend_shared/
  ├── .env                       → compartido entre releases, nunca se pisa
  ├── storage/                   → compartido (uploads, logs, sesiones, certificados SII cifrados)
  └── backups/
      └── db-20260706153000.sql.gz
```

### Orden de operaciones en cada deploy

1. Se arma la release nueva en `~/erp_backend_releases/<timestamp>/` — **aislada, producción no la ve todavía**.
2. Se symlinkea `.env` y `storage/` desde `shared/` (así los certificados digitales SII, uploads y logs sobreviven entre deploys — antes vivían dentro del zip excluido a mano, ahora es estructural).
3. `php artisan optimize:clear`.
4. **Backup de base de datos** (`mysqldump | gzip`) antes de migrar — *best-effort*: si `mysqldump` no está disponible en el servidor, el deploy continúa con una advertencia en vez de fallar (para no romper el pipeline por un supuesto no verificado sobre el hosting).
5. `php artisan migrate --force` + recacheo (`config:cache`, `route:cache`, `view:cache`, `event:cache`, `storage:link`).
6. Solo si **todo lo anterior terminó sin error** (`set -e` corta el script en el primer fallo): swap atómico del symlink —
   ```bash
   ln -sfn "$NEW_RELEASE" "${APP_LINK}.new"
   mv -Tf "${APP_LINK}.new" "$APP_LINK"
   ```
   `mv -T` entre dos symlinks en el mismo filesystem es una operación atómica a nivel de sistema de archivos — no hay ventana donde el servidor vea un estado intermedio.
7. Limpieza: se conservan las últimas 5 releases y los últimos 5 backups de BD; el resto se borra.

### Si algo falla a mitad de camino

El symlink `erp_backend` **nunca se toca** hasta el paso 6. Si el `unzip`, la migración, o cualquier paso previo falla, producción sigue sirviendo la release anterior sin ningún cambio visible — el peor caso es un deploy fallido que no rompe nada, en vez de un deploy fallido que deja el sitio caído.

### Migración desde el esquema viejo (primera vez)

El script detecta si `~/erp_backend` todavía es una carpeta real (no symlink) — el estado previo al fix — y automáticamente:
- Copia `.env` y `storage/` a `shared/` (una sola vez, `cp -n` no pisa si ya existe).
- Renombra la carpeta vieja como `erp_backend.pre-symlink-backup-<timestamp>` en vez de borrarla (reversible a mano si algo sale mal en el primer deploy con el patrón nuevo).

Esto corre automáticamente en el próximo push a `staging` o `main` — no requiere ningún paso manual antes.

## Qué NO se resolvió en este cambio (alcance deliberado, ver veredicto del council)

- **Rollback automático de datos**: si una migración falla a mitad (2 de 5 pasos aplicados), el symlink no se mueve (el código sigue siendo el viejo), pero la base de datos ya tiene esas 2 migraciones aplicadas. El backup pre-migración permite un rollback **manual** de la DB si hace falta, pero no se automatiza — la industria (Forge, Envoyer) tampoco lo hace, porque un rollback automático de datos es más peligroso que dejarlo como decisión humana informada por el backup.
- **Frontend (`public_html` de assets estáticos)**: sigue subiéndose vía SCP directo al docroot en vivo, sin symlink. Se dejó fuera del alcance porque no está confirmado si el hosting permite que el docroot del dominio sea un symlink (algunos paneles cPanel lo restringen) — hay que probarlo en staging antes de prometerlo. El riesgo es menor que el del backend: Vite genera assets con hash en el nombre de archivo, así que un deploy de frontend cortado a mitad deja como máximo una mezcla de JS/CSS con y sin hash nuevo, no rompe la API ni corrompe datos.

## Hallazgo nuevo detectado de paso (sin confirmar, no corregido)

Revisando `index.php` para este fix se notó que el `match()` de `$basePath` solo distingue por la presencia del substring `/home/atlasdig` en `__DIR__` — **no distingue entre producción y staging**. Si ambos ambientes viven bajo la misma cuenta de hosting (`/home/atlasdig/...`), el `index.php` de staging resolvería `$basePath` hacia `/home/atlasdig/erp_backend` (producción), no hacia `erp_backend_staging`. Esto significaría que **staging podría estar sirviendo contra el backend/base de datos de producción** en vez del propio.

**No se pudo confirmar ni corregir en esta sesión** porque depende de la topología real del hosting (si staging y producción comparten cuenta o no), que no es verificable sin acceso directo al servidor. Pendiente: confirmar con el equipo de infraestructura y, si se confirma, corregir el `match()` para distinguir explícitamente por dominio/ruta de staging vs producción.

## Runbook para probar el fix

1. Push a la rama `staging` primero (el job `deploy-staging` ya usa el patrón nuevo).
2. Confirmar en el log de GitHub Actions que aparece `🔧 Primer deploy con releases+symlink` (migración del esquema viejo) y luego `✅ Deploy ERP staging completado -- release activa: <timestamp>`.
3. Verificar por SSH que `~/erp_backend_staging` ahora es un symlink (`ls -la ~/erp_backend_staging`) apuntando a `~/erp_backend_staging_releases/<timestamp>`.
4. Prueba de fallo simulado (recomendada por el council antes de confiar en el fix): cortar la conexión SSH a mitad de un deploy de prueba (o forzar un error temporal en una migración) y confirmar que el symlink sigue apuntando a la release anterior y el sitio staging sigue respondiendo con normalidad.
5. Recién después de validar en staging, dejar que el próximo push a `main` ejercite el mismo patrón en producción.

## Incidente real — primer deploy a producción (2026-07-03, ~11:34 UTC)

No había ambiente de staging real disponible en el hosting (confirmado por SSH: solo existen `admin.tenri.cl`, `api.tenri.cl`, `booking.tenri.cl`, `erp.tenri.cl`, `tenri.cl` bajo la cuenta — ningún `staging.erp.tenri.cl`). El primer deploy real con este patrón corrió directo contra producción vía PR a `main`.

**Qué pasó:**

1. El bootstrap del patrón viejo→nuevo corrió correctamente: `.env` y `storage/` se copiaron a `erp_backend_shared/`, y `~/erp_backend` se renombró a `erp_backend.pre-symlink-backup-20260703113424` (tal como está diseñado, no destructivo).
2. `php artisan optimize:clear` corrió bien.
3. El script murió en el paso de backup de BD: `mapfile -t DBCFG < <(php -r '...')` falló con `bash: line 54: /dev/fd/63: No such file or directory`. El contenedor Docker que usa `appleboy/ssh-action@v1.0.3` para ejecutar el script remoto no expone `/dev/fd` en esa sesión, así que la *process substitution* (`<(...)`) no funciona ahí aunque sí funciona en una sesión SSH normal.
4. `set -e` cortó el script en ese punto — **correcto, funcionó como estaba diseñado**: el swap del symlink nunca se ejecutó porque el script no llegó a esa línea.
5. **Pero** el paso 1 (bootstrap) ya había corrido y renombrado `erp_backend` fuera de su lugar antes de que el script muriera — como el símlink nunca se creó, `~/erp_backend` quedó **inexistente**, y `index.php` (que apunta a esa ruta absoluta) empezó a devolver 500 en todo `/api/*`. El frontend estático (`/`) seguía respondiendo 200 porque no depende del backend para servirse.
6. Downtime real de la API: desde el fallo del deploy hasta la restauración manual (unos minutos, verificado por SSH en vivo mientras se diagnosticaba).
7. **Restauración:** `mv ~/erp_backend.pre-symlink-backup-20260703113424 ~/erp_backend` — un solo comando, sin pérdida de datos (el bootstrap nunca borra, solo renombra). Confirmado `/api/health` → 200 inmediatamente después.

**Causa raíz y fix:** reemplazado `mapfile -t DBCFG < <(php -r '...')` por un archivo temporal (`php -r '...' > "$DBCFG_FILE"; mapfile -t DBCFG < "$DBCFG_FILE"`) en ambos jobs — evita `/dev/fd` por completo. Verificado el fix corriendo el snippet exacto contra el servidor real (subido como script vía SFTP, no inline, para evitar el mismo problema de proceso) — leyó la configuración de BD correctamente.

**Lección para el runbook:** el "probar primero en staging" del veredicto del council no se pudo cumplir porque el ambiente no existe — el primer test real terminó siendo contra producción. La razón por la que el incidente fue recuperable rápido es exactamente la razón por la que se hizo este cambio: el bootstrap no destruye nada (renombra), y `set -e` cortó antes del swap. Un pipeline que hubiera seguido con el `unzip -o` directo sobre el directorio viejo (el diseño original que se estaba reemplazando) habría fallado de forma mucho menos diagnosticable. Aun así, **el bootstrap en sí mismo genera una ventana de riesgo real** la primera vez que corre (deja el directorio viejo momentáneamente renombrado antes de que exista el symlink) — pendiente de evaluar si conviene mover el rename del directorio viejo a *después* de confirmar que la release nueva completó todos sus pasos, en vez de al principio.
