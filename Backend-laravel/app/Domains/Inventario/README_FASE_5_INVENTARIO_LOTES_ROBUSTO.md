# README Fase 5 — Inventario: Lotes, Vencimientos y Trazabilidad Avanzada

## 1. Contexto técnico

Esta fase pertenece al módulo de Inventario del ERP Contable, rama `SLagos-dev`.

Stack actual:

- Laravel 12
- PHP 8.2+
- MySQL/MariaDB
- Laravel Sanctum
- PHPUnit / Laravel Test Suite
- Arquitectura por dominios en `Backend-laravel/app/Domains`
- Dominio trabajado: `Backend-laravel/app/Domains/Inventario`
- Arquitectura actual del dominio: Controllers + Services + Eloquent Models
- No existe capa Repository física en Inventario
- No se introdujeron librerías nuevas
- No se modificó el manejo global de errores
- No se tocó lógica de otros dominios salvo integración RBAC/visual mínima

Contrato API respetado:

- `success: true/false`
- `422` para errores de dominio, validación o permisos de negocio
- `401` sin token por `auth:sanctum`
- Respuestas JSON consistentes con las fases anteriores

Regla crítica:

Inventario **NO emite, gestiona ni prepara DTE**.

No se agregó ni se debe agregar lógica tributaria/SII dentro de Inventario:

- No `codigo_dte`
- No `codigo_sii`
- No `folio_dte`
- No `xml_dte`
- No `emitir_dte`
- No lógica SII

Cuando se necesita trazabilidad externa, se mantienen campos genéricos:

- `referencia`
- `motivo`
- `observacion`
- `origen_modulo`
- `origen_id`

---

## 2. Objetivo de la Fase 5

Agregar trazabilidad granular al inventario mediante **lotes** y **fechas de vencimiento**, manteniendo el stock consolidado existente y agregando un desglose por lote.

La fase permite:

- Crear lotes por producto
- Listar lotes
- Editar lotes
- Consultar detalle de lote
- Consultar stock por lote
- Asociar lotes a productos de la empresa
- Manejar fecha de fabricación opcional
- Manejar fecha de vencimiento opcional u obligatoria según configuración del producto
- Registrar entradas con lote
- Registrar salidas desde lote
- Registrar traspasos conservando lote
- Filtrar Kardex por lote
- Mantener PMP y valorización consistentes
- Mantener `inventario_stock` como stock consolidado
- Agregar `inventario_stock_lotes` como desglose granular
- Mantener auditoría vía `inventario_movimiento_lotes`
- Mantener multiempresa, RBAC y permisos existentes

---

## 3. Decisión de arquitectura

Se decidió **extender el flujo actual de movimientos** en vez de crear endpoints paralelos.

### Endpoint extendido

```http
POST /api/inventario/movimientos
```

Ahora acepta opcionalmente:

```json
{
  "lote_id": 1,
  "lote": {
    "codigo_lote": "LOT-2026-001",
    "fecha_fabricacion": "2026-01-01",
    "fecha_vencimiento": "2026-12-31",
    "observacion": "Lote inicial"
  }
}
```

### Motivo de la decisión

No se crearon endpoints separados de movimientos por lote porque eso duplicaría lógica ya resuelta en Fase 2 y Fase 3:

- Validación de producto activo
- Validación de bodega activa
- Validación multiempresa
- Validación de stock suficiente
- `DB::transaction()`
- `lockForUpdate()`
- Kardex
- PMP
- Valorización
- Auditoría de movimientos
- Permisos por tipo de movimiento

La responsabilidad quedó separada así:

| Componente | Responsabilidad |
|---|---|
| `InventarioMovimientoService` | Movimiento consolidado, Kardex, PMP, stock principal |
| `InventarioLoteService` | Resolución de lote, stock granular por lote y detalle de lote |
| `inventario_stock` | Stock consolidado oficial por producto/bodega |
| `inventario_stock_lotes` | Stock granular por producto/bodega/lote |
| `inventario_movimientos` | Cabecera principal de Kardex |
| `inventario_movimiento_lotes` | Trazabilidad granular del lote afectado |

---

## 4. Migraciones aplicadas

Archivo creado:

```txt
database/migrations/2026_05_05_100000_create_inventario_lotes_tables.php
```

### 4.1. Cambios en `inventario_productos`

Se agregaron campos:

| Campo | Tipo | Default | Descripción |
|---|---:|---:|---|
| `maneja_lotes` | boolean | false | Indica si el producto exige trazabilidad por lote |
| `requiere_fecha_vencimiento` | boolean | false | Indica si sus lotes deben tener fecha de vencimiento |

Estos campos no rompen productos existentes porque ambos quedan en `false` por defecto.

### 4.2. Nueva tabla `inventario_lotes`

Tabla maestra de lotes.

Campos principales:

| Campo | Descripción |
|---|---|
| `id` | Identificador del lote |
| `empresa_id` | Empresa dueña del lote |
| `producto_id` | Producto asociado |
| `codigo_lote` | Código del lote |
| `fecha_fabricacion` | Fecha opcional de fabricación |
| `fecha_vencimiento` | Fecha opcional u obligatoria según producto |
| `observacion` | Observación interna |
| `activo` | Estado del lote |
| `created_at`, `updated_at` | Auditoría base |

Restricción principal:

```txt
unique(empresa_id, producto_id, codigo_lote)
```

Esto permite que otra empresa pueda usar el mismo código de lote sin conflicto.

### 4.3. Nueva tabla `inventario_stock_lotes`

Guarda el desglose de stock por lote.

Campos principales:

| Campo | Descripción |
|---|---|
| `empresa_id` | Empresa |
| `producto_id` | Producto |
| `bodega_id` | Bodega |
| `lote_id` | Lote |
| `stock_actual` | Cantidad actual del lote en esa bodega |

Restricción principal:

```txt
unique(empresa_id, producto_id, bodega_id, lote_id)
```

### 4.4. Nueva tabla `inventario_movimiento_lotes`

Guarda el detalle auditable de cada movimiento que afecta un lote.

Campos relevantes:

| Campo | Descripción |
|---|---|
| `movimiento_inventario_id` | Movimiento principal del Kardex |
| `producto_id` | Producto afectado |
| `lote_id` | Lote afectado |
| `bodega_origen_id` | Bodega origen en salidas/traspasos |
| `bodega_destino_id` | Bodega destino en entradas/traspasos |
| `cantidad` | Cantidad afectada |
| `stock_lote_origen_antes` | Stock del lote antes en bodega origen |
| `stock_lote_origen_despues` | Stock del lote después en bodega origen |
| `stock_lote_destino_antes` | Stock del lote antes en bodega destino |
| `stock_lote_destino_despues` | Stock del lote después en bodega destino |
| `costo_unitario` | Costo unitario del movimiento |
| `costo_total` | Costo total del movimiento |

### 4.5. Cambio en `inventario_ajustes_criticos`

Se agregó:

| Campo | Descripción |
|---|---|
| `lote_id` nullable | Permite asociar ajustes críticos/mermas/vencimientos a un lote cuando aplique |

---

## 5. Models creados o modificados

### 5.1. Nuevos models

```txt
app/Domains/Inventario/Models/LoteInventario.php
app/Domains/Inventario/Models/StockLoteInventario.php
app/Domains/Inventario/Models/MovimientoLoteInventario.php
```

### 5.2. Models modificados

```txt
app/Domains/Inventario/Models/Producto.php
app/Domains/Inventario/Models/MovimientoInventario.php
app/Domains/Inventario/Models/AjusteCriticoInventario.php
```

Opcional/recomendado según implementación aplicada:

```txt
app/Domains/Inventario/Models/StockProducto.php
app/Domains/Inventario/Models/Bodega.php
```

### 5.3. Relaciones principales

`Producto`:

- `lotes()`
- `stockLotes()`
- `movimientosLotes()`
- helpers `manejaLotes()` y `requiereFechaVencimiento()`

`LoteInventario`:

- `empresa()`
- `producto()`
- `stocks()`
- `movimientos()`
- `ajustesCriticos()`

`StockLoteInventario`:

- `empresa()`
- `producto()`
- `bodega()`
- `lote()`

`MovimientoInventario`:

- `lotes()`

`MovimientoLoteInventario`:

- `movimiento()`
- `producto()`
- `lote()`
- `bodegaOrigen()`
- `bodegaDestino()`

`AjusteCriticoInventario`:

- `lote()`

---

## 6. Services trabajados

### 6.1. Nuevo service: `InventarioLoteService`

Archivo:

```txt
app/Domains/Inventario/Services/InventarioLoteService.php
```

Responsabilidades:

- Listar lotes
- Obtener detalle de lote
- Crear lote
- Actualizar lote
- Listar lotes por producto
- Consultar stock por lote
- Validar multiempresa
- Validar producto activo
- Validar lote activo
- Validar duplicidad de lote por empresa/producto
- Validar fecha de vencimiento requerida
- Resolver lote para entrada
- Resolver lote para salida
- Resolver lote para traspaso
- Crear stock por lote si no existe
- Bloquear stock por lote con `lockForUpdate()`
- Actualizar stock granular
- Registrar detalle en `inventario_movimiento_lotes`

Métodos principales:

```php
listarLotes(User $usuario, array $filtros = [])
obtenerLote(User $usuario, int $loteId)
listarLotesProducto(User $usuario, int $productoId, array $filtros = [])
consultarStockPorLote(User $usuario, int $loteId)
crearLote(User $usuario, array $datos)
actualizarLote(User $usuario, int $loteId, array $datos)
resolverLoteParaEntrada(Producto $producto, array $datos, int $empresaId)
resolverLoteParaSalida(Producto $producto, array $datos, int $empresaId)
resolverLoteParaTraspaso(Producto $producto, array $datos, int $empresaId)
aplicarEntradaLote(...)
aplicarSalidaLote(...)
aplicarTraspasoLote(...)
```

### 6.2. Service modificado: `InventarioMovimientoService`

Archivo:

```txt
app/Domains/Inventario/Services/InventarioMovimientoService.php
```

Se integró `InventarioLoteService` por inyección de dependencias:

```php
public function __construct(
    private readonly InventarioValorizacionService $valorizacionService,
    private readonly InventarioLoteService $loteService
) {
}
```

Cambios funcionales:

- Entrada con lote nuevo
- Entrada con lote existente
- Salida desde lote
- Traspaso conservando lote
- Ajuste positivo con lote
- Ajuste negativo con lote
- Filtro `lote_id` en movimientos/Kardex
- Carga de relaciones de lote en respuestas
- Validación de producto con `maneja_lotes = true`
- Rechazo de payload de lote si el producto no maneja lotes

La lógica consolidada sigue perteneciendo a `InventarioMovimientoService`:

- `inventario_stock`
- `inventario_movimientos`
- PMP
- Kardex
- costo unitario
- costo total

La lógica granular queda en `InventarioLoteService`:

- `inventario_stock_lotes`
- `inventario_movimiento_lotes`

### 6.3. Service modificado: `InventarioService`

Archivo:

```txt
app/Domains/Inventario/Services/InventarioService.php
```

Corrección importante aplicada durante Postman:

Se agregó persistencia de:

```php
'maneja_lotes' => $datos['maneja_lotes'] ?? false,
'requiere_fecha_vencimiento' => $datos['requiere_fecha_vencimiento'] ?? false,
```

en:

- `crearProducto()`
- `actualizarProducto()`

También se agregó regla de coherencia:

```php
if ($requiereFechaVencimiento && !$manejaLotes) {
    throw new Exception('Un producto que requiere fecha de vencimiento debe manejar lotes.');
}
```

Motivo:

En Postman se detectó que el producto se creaba correctamente, pero quedaba con:

```json
"maneja_lotes": false,
"requiere_fecha_vencimiento": false
```

Después de la corrección, el endpoint de productos guarda correctamente:

```json
"maneja_lotes": true,
"requiere_fecha_vencimiento": true
```

---

## 7. Controller y rutas

### 7.1. Controller modificado

Archivo:

```txt
app/Domains/Inventario/Controllers/InventarioController.php
```

Cambios:

- Se inyectó `InventarioLoteService`
- Se agregaron métodos públicos para lotes
- Se extendió validación de productos con `maneja_lotes` y `requiere_fecha_vencimiento`
- Se extendió validación de movimientos con `lote_id` y `lote`
- Se agregó filtro `lote_id` en movimientos/Kardex
- Se agregó carga de relaciones de lotes en respuesta de movimientos

Métodos agregados:

```php
lotes(Request $request)
storeLote(Request $request)
showLote(Request $request, $id)
updateLote(Request $request, $id)
lotesProducto(Request $request, $id)
stockLote(Request $request, $id)
```

### 7.2. Rutas agregadas

Archivo:

```txt
routes/api.php
```

Rutas nuevas:

```php
Route::get('/lotes', [InventarioController::class, 'lotes']);
Route::post('/lotes', [InventarioController::class, 'storeLote']);
Route::get('/lotes/{id}/stock', [InventarioController::class, 'stockLote']);
Route::get('/lotes/{id}', [InventarioController::class, 'showLote']);
Route::put('/lotes/{id}', [InventarioController::class, 'updateLote']);
Route::get('/productos/{id}/lotes', [InventarioController::class, 'lotesProducto']);
```

Importante:

```php
Route::get('/lotes/{id}/stock', ...)
```

debe ir antes de:

```php
Route::get('/lotes/{id}', ...)
```

para evitar que Laravel interprete `stock` como parámetro de `{id}`.

---

## 8. Endpoints disponibles

### 8.1. Lotes

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/api/inventario/lotes` | Lista lotes |
| POST | `/api/inventario/lotes` | Crea lote |
| GET | `/api/inventario/lotes/{id}` | Detalle de lote |
| PUT | `/api/inventario/lotes/{id}` | Actualiza lote |
| GET | `/api/inventario/lotes/{id}/stock` | Stock por lote |
| GET | `/api/inventario/productos/{id}/lotes` | Lotes de un producto |

### 8.2. Movimientos extendidos

| Método | Endpoint | Cambio |
|---|---|---|
| POST | `/api/inventario/movimientos` | Acepta `lote_id` o `lote` |
| GET | `/api/inventario/movimientos?lote_id=1` | Filtra por lote |
| GET | `/api/inventario/kardex?lote_id=1` | Filtra Kardex por lote |
| GET | `/api/inventario/productos/{id}/kardex?lote_id=1` | Filtra Kardex del producto por lote |

---

## 9. Permisos RBAC

Permisos nuevos:

```txt
inventario.lotes.ver
inventario.lotes.crear
inventario.lotes.editar
```

Reglas respetadas:

- Inventario no crea roles
- Inventario no crea usuarios
- Inventario no asigna permisos automáticamente en migraciones
- Inventario no modifica `DatabaseSeeder`
- Los permisos se exponen para asignación visual y seeders demo opcionales

### 9.1. Frontend

Archivo modificado:

```txt
Frontend/src/Modulos/Administrador/GestionRoles.jsx
```

Se agregaron permisos visuales en la categoría Inventario:

```js
'inventario.lotes.ver',
'inventario.lotes.crear',
'inventario.lotes.editar',
```

### 9.2. Auth runtime admin

Archivo modificado:

```txt
app/Domains/Core/Controllers/AuthController.php
```

Se agregaron los permisos de lotes al set runtime del rol Administrador.

### 9.3. Seeder demo opcional

Archivo modificado:

```txt
database/seeders/InventarioDemoPermisosSeeder.php
```

Permisos demo esperados:

| Rol | Permisos de lotes |
|---|---|
| Administrador | ver, crear, editar |
| Contador | ver, crear, editar |
| Auditor | ver |

Este seeder sigue siendo manual/opcional y no va en `DatabaseSeeder`.

### 9.4. Helper de tests

Archivo modificado:

```txt
tests/Concerns/PreparaInventarioTest.php
```

Se agregaron permisos de lote para usuarios demo de tests.

---

## 10. Reglas de negocio implementadas

1. Un lote pertenece a una empresa y a un producto.
2. `codigo_lote` es único por `empresa_id + producto_id`.
3. El mismo `codigo_lote` puede existir en otra empresa.
4. No se permite usar lote de otra empresa.
5. No se permite usar lote de otro producto.
6. No se permite usar lote inactivo.
7. No se permite stock negativo por lote.
8. Si `producto.maneja_lotes = true`, entrada/salida/traspaso deben informar lote.
9. Si `producto.maneja_lotes = false`, no debe enviarse `lote_id` ni `lote`.
10. Si `producto.requiere_fecha_vencimiento = true`, el lote debe tener `fecha_vencimiento`.
11. Entrada puede crear lote nuevo mediante payload `lote`.
12. Entrada puede usar lote existente mediante `lote_id`.
13. Salida requiere lote existente si el producto maneja lotes.
14. Traspaso conserva el mismo lote entre bodegas.
15. `inventario_stock` y `inventario_stock_lotes` deben quedar consistentes.
16. La suma de stock por lote de una bodega debe coincidir con el stock consolidado.
17. Ajustes positivos/negativos pueden funcionar con lote mediante el flujo extendido.
18. No se introduce lógica DTE/SII.
19. No se rompe Fase 1, 2, 3 ni 4.

---

## 11. Ejemplos JSON

### 11.1. Crear producto que maneja lotes

```http
POST /api/inventario/productos
```

```json
{
  "sku": "POSTMAN-LOTE-001",
  "nombre": "Producto Postman con Lote",
  "descripcion": "Producto demo Fase 5",
  "tipo_producto": "BIEN",
  "unidad_medida_id": 1,
  "metodo_valorizacion": "PMP",
  "costo_promedio": 0,
  "precio_venta_neto": 1000,
  "afecto_iva": true,
  "codigo_barra": "7800000000011",
  "stock_minimo": 0,
  "permite_merma": true,
  "maneja_lotes": true,
  "requiere_fecha_vencimiento": true,
  "activo": true
}
```

### 11.2. Crear lote

```http
POST /api/inventario/lotes
```

```json
{
  "producto_id": 2,
  "codigo_lote": "LOT-POSTMAN-02",
  "fecha_fabricacion": "2026-01-01",
  "fecha_vencimiento": "2026-12-31",
  "observacion": "Lote creado desde Postman"
}
```

### 11.3. Entrada con lote existente

```http
POST /api/inventario/movimientos
```

```json
{
  "tipo": "entrada",
  "producto_id": 2,
  "bodega_destino_id": 2,
  "cantidad": 10,
  "costo_unitario": 100,
  "lote_id": 1,
  "referencia": "PM-ENT-LOTE-001",
  "motivo": "compra",
  "observacion": "Entrada Postman con lote existente"
}
```

### 11.4. Salida desde lote

```http
POST /api/inventario/movimientos
```

```json
{
  "tipo": "salida",
  "producto_id": 2,
  "bodega_origen_id": 2,
  "cantidad": 2,
  "lote_id": 1,
  "referencia": "PM-SAL-LOTE-001",
  "motivo": "consumo",
  "observacion": "Salida Postman desde lote"
}
```

### 11.5. Traspaso con lote

```http
POST /api/inventario/movimientos
```

```json
{
  "tipo": "traspaso",
  "producto_id": 2,
  "bodega_origen_id": 2,
  "bodega_destino_id": 3,
  "cantidad": 3,
  "lote_id": 1,
  "referencia": "PM-TR-LOTE-001",
  "motivo": "traspaso_bodega",
  "observacion": "Traspaso Postman conservando lote"
}
```

### 11.6. Consultar stock por lote

```http
GET /api/inventario/lotes/1/stock
```

Resultado esperado después de entrada de 10:

```json
{
  "success": true,
  "data": {
    "stock_total": 10
  }
}
```

Resultado esperado después de salida de 2 y traspaso de 3:

```txt
stock_total = 8
Bodega origen = 5
Bodega destino = 3
```

### 11.7. Kardex filtrado por lote

```http
GET /api/inventario/productos/2/kardex?lote_id=1
```

Referencias esperadas:

```txt
PM-ENT-LOTE-001
PM-SAL-LOTE-001
PM-TR-LOTE-001
```

---

## 12. Tests agregados

### 12.1. Archivo `InventarioLoteApiTest`

```txt
tests/Feature/Inventario/InventarioLoteApiTest.php
```

Casos cubiertos:

- Crear lote válido
- Rechazar lote duplicado por empresa/producto
- Permitir mismo código de lote en otra empresa
- Rechazar producto ajeno
- Rechazar producto inactivo
- Exigir fecha de vencimiento si el producto lo requiere
- Listar lotes
- Ver detalle de lote
- Editar lote
- Listar lotes por producto
- Consultar stock por lote
- Auditor puede consultar lotes
- Auditor no puede crear ni editar lotes
- Multiempresa en listado y detalle
- 401 sin token

### 12.2. Archivo `InventarioMovimientoLoteApiTest`

```txt
tests/Feature/Inventario/InventarioMovimientoLoteApiTest.php
```

Casos cubiertos:

- Entrada con lote nuevo
- Entrada con lote existente
- Creación de `inventario_stock_lotes`
- Creación de `inventario_movimiento_lotes`
- Salida desde lote
- Salida falla si el lote no tiene stock suficiente
- Rollback si falla el stock por lote
- Traspaso con lote entre bodegas
- Conservación del lote en traspaso
- Producto con `maneja_lotes = true` exige lote
- Producto con `maneja_lotes = false` rechaza payload de lote
- Kardex por producto filtrado por lote
- Invariante `inventario_stock == SUM(inventario_stock_lotes)` por bodega

### 12.3. Resultado validado

Comando ejecutado:

```bash
php artisan test --filter=Inventario
```

Resultado validado:

```txt
Tests: 86 passed (434 assertions)
Duration: 2.96s
```

Suites relevantes que pasaron:

```txt
PASS InventarioAjusteCriticoApiTest
PASS InventarioApiTest
PASS InventarioLoteApiTest
PASS InventarioMovimientoApiTest
PASS InventarioMovimientoLoteApiTest
PASS InventarioPermisoServiceTest
PASS InventarioValorizacionApiTest
PASS InventarioValorizacionServiceTest
PASS MovimientoInventarioModelTest
```

---

## 13. Pruebas Postman mínimas validadas

### 13.1. Seeders demo usados

```bash
php artisan db:seed --class=InventarioPostmanSeeder
php artisan db:seed --class=InventarioDemoPermisosSeeder
```

Si faltan roles:

```bash
php artisan db:seed --class=RolSeeder
```

### 13.2. Usuarios demo esperados

```txt
contador@example.com
password

auditor@example.com
password
```

### 13.3. Flujo mínimo validado

1. Login contador
2. Crear producto con `maneja_lotes = true`
3. Crear lote
4. Crear/usar dos bodegas activas
5. Entrada con lote existente
6. Consultar stock por lote
7. Salida desde lote
8. Consultar stock por lote nuevamente
9. Traspaso conservando lote
10. Kardex filtrado por lote
11. Auditor puede listar lotes
12. Auditor no puede crear lotes

### 13.4. Datos usados en Postman

```txt
producto_id = 2
lote_id = 1
bodega_origen_id = 2
bodega_destino_id = 3
```

### 13.5. Resultado validado manualmente

Entrada:

```txt
stock_total lote = 10
bodega 2 = 10
```

Salida de 2 unidades:

```txt
stock origen antes = 10
stock origen después = 8
stock lote origen antes = 10
stock lote origen después = 8
```

Traspaso de 3 unidades desde bodega 2 a bodega 3:

```txt
stock origen antes = 8
stock origen después = 5
stock destino antes = 0
stock destino después = 3
stock lote origen antes = 8
stock lote origen después = 5
stock lote destino antes = 0
stock lote destino después = 3
```

Resultado final esperado:

```txt
stock_total lote = 8
bodega 2 = 5
bodega 3 = 3
```

---

## 14. Comandos de validación

Backend:

```bash
cd Backend-laravel
php artisan optimize:clear
composer dump-autoload
php artisan route:list --path=inventario
php artisan test --filter=Inventario
```

Validación de sintaxis de archivos tocados:

```bash
php -l app/Domains/Inventario/Controllers/InventarioController.php
php -l app/Domains/Inventario/Services/InventarioService.php
php -l app/Domains/Inventario/Services/InventarioMovimientoService.php
php -l app/Domains/Inventario/Services/InventarioLoteService.php
php -l app/Domains/Core/Controllers/AuthController.php
php -l database/seeders/InventarioDemoPermisosSeeder.php
php -l tests/Concerns/PreparaInventarioTest.php
```

Frontend:

```bash
cd Frontend
npm install --legacy-peer-deps
npm run build
```

Resultado validado frontend:

```txt
vite build OK
PWA generado correctamente
```

Nota:

No ejecutar `npm audit fix` durante esta fase, porque puede actualizar dependencias y romper compatibilidad. El build pasó usando `npm install --legacy-peer-deps`.

---

## 15. Archivos tocados en la Fase 5

### Backend — migraciones

```txt
database/migrations/2026_05_05_100000_create_inventario_lotes_tables.php
```

### Backend — models

```txt
app/Domains/Inventario/Models/LoteInventario.php
app/Domains/Inventario/Models/StockLoteInventario.php
app/Domains/Inventario/Models/MovimientoLoteInventario.php
app/Domains/Inventario/Models/Producto.php
app/Domains/Inventario/Models/MovimientoInventario.php
app/Domains/Inventario/Models/AjusteCriticoInventario.php
app/Domains/Inventario/Models/StockProducto.php
app/Domains/Inventario/Models/Bodega.php
```

### Backend — services

```txt
app/Domains/Inventario/Services/InventarioLoteService.php
app/Domains/Inventario/Services/InventarioMovimientoService.php
app/Domains/Inventario/Services/InventarioService.php
app/Domains/Inventario/Services/InventarioPermisoService.php
```

Nota: `InventarioPermisoService` no cambia necesariamente su lógica si ya valida permisos dinámicos existentes. Solo se requiere que los permisos de lote estén disponibles en roles/demo/RBAC.

### Backend — controller/rutas

```txt
app/Domains/Inventario/Controllers/InventarioController.php
routes/api.php
```

### Backend — RBAC/demo/tests

```txt
app/Domains/Core/Controllers/AuthController.php
database/seeders/InventarioDemoPermisosSeeder.php
tests/Concerns/PreparaInventarioTest.php
tests/Feature/Inventario/InventarioLoteApiTest.php
tests/Feature/Inventario/InventarioMovimientoLoteApiTest.php
```

### Frontend

```txt
Frontend/src/Modulos/Administrador/GestionRoles.jsx
```

---

## 16. Checklist técnico final

- [x] Productos soportan `maneja_lotes`
- [x] Productos soportan `requiere_fecha_vencimiento`
- [x] `InventarioService` persiste ambos campos
- [x] Se valida que un producto con vencimiento requerido maneje lotes
- [x] Se creó tabla `inventario_lotes`
- [x] Se creó tabla `inventario_stock_lotes`
- [x] Se creó tabla `inventario_movimiento_lotes`
- [x] Se agregó `lote_id` nullable en ajustes críticos
- [x] Se creó `LoteInventario`
- [x] Se creó `StockLoteInventario`
- [x] Se creó `MovimientoLoteInventario`
- [x] Se creó `InventarioLoteService`
- [x] `InventarioMovimientoService` integra lote sin duplicar PMP/Kardex
- [x] Entrada puede crear lote nuevo
- [x] Entrada puede usar lote existente
- [x] Salida descuenta lote específico
- [x] Traspaso conserva lote entre bodegas
- [x] Kardex puede filtrar por lote
- [x] Stock consolidado se mantiene en `inventario_stock`
- [x] Stock granular se mantiene en `inventario_stock_lotes`
- [x] Detalle auditable se registra en `inventario_movimiento_lotes`
- [x] RBAC de lotes agregado
- [x] GestionRoles muestra permisos de lotes
- [x] Seeder demo opcional incluye permisos de lotes
- [x] Tests API de lotes agregados
- [x] Tests movimientos con lote agregados
- [x] Tests de Inventario pasan completos
- [x] Postman mínimo validado
- [x] Sin DTE/SII en Inventario
- [x] Sin migraciones que asignen permisos automáticamente
- [x] Sin cambios en `DatabaseSeeder`

---

## 17. Riesgos y mejoras futuras

Recomendado para próximas fases o hardening:

1. Estandarizar formato global de errores para todo el ERP.
2. Agregar endpoints avanzados de vencimientos:
   - lotes vencidos
   - lotes por vencer
   - dashboard de alertas
3. Agregar FEFO/FIFO para sugerencia automática de lote en salidas.
4. Agregar bloqueo de salida de lotes vencidos si negocio lo requiere.
5. Mejorar Postman collection formal con environments exportables.
6. Agregar paginación/filtros avanzados en UI de lotes.
7. Agregar pantallas frontend de administración/consulta de lotes.
8. Agregar pruebas de ajustes críticos con `lote_id` si se profundiza mermas/vencimiento.
9. Optimizar chunks del frontend con lazy loading/code splitting.
10. Revisar vulnerabilidades npm en una fase separada, sin usar `npm audit fix` a ciegas.

---

## 18. Commit sugerido

Commit principal recomendado:

```txt
feat(inventario): agregar lotes vencimientos y trazabilidad avanzada
```

Descripción sugerida:

```txt
- Agrega configuración de productos para manejo de lotes y vencimientos
- Crea tablas inventario_lotes, inventario_stock_lotes e inventario_movimiento_lotes
- Agrega modelos y relaciones para trazabilidad por lote
- Implementa InventarioLoteService
- Integra lotes en InventarioMovimientoService sin duplicar Kardex/PMP
- Extiende movimientos con lote_id y payload lote
- Agrega endpoints CRUD/consulta de lotes
- Agrega filtro por lote en movimientos y Kardex
- Agrega permisos RBAC de lotes en UI, Auth runtime y seeders demo
- Agrega tests Feature/API de lotes
- Agrega tests de movimientos con lote e invariantes de stock
- Valida flujo mínimo Postman de entrada, salida y traspaso con lote
```

Commit opcional si separas frontend:

```txt
feat(frontend): exponer permisos de lotes en gestion de roles
```

Commit opcional si separas documentación:

```txt
docs(inventario): documentar fase 5 lotes y trazabilidad avanzada
```

---

## 19. Estado final de la Fase 5

Fase 5 queda funcional para demo técnica y validación backend.

Estado:

```txt
✅ Diseño técnico aprobado
✅ Migraciones aplicadas
✅ Models creados/modificados
✅ Services integrados
✅ Controller/rutas funcionando
✅ RBAC agregado
✅ Seeders demo opcionales actualizados
✅ Tests automáticos aprobados
✅ Postman mínimo aprobado
✅ README técnico generado
```

Resultado de calidad validado:

```txt
86 tests passed
434 assertions
```

