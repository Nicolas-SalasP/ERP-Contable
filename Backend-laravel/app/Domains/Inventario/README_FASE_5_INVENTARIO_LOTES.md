# README Fase 5 — Inventario: Lotes, Vencimientos y Trazabilidad Avanzada

## Estado de la fase

**Estado:** completada y validada.  
**Rama:** `SLagos-dev`  
**Dominio:** `Backend-laravel/app/Domains/Inventario`  
**Stack:** Laravel 12, PHP 8.2+, MySQL/MariaDB, Laravel Sanctum, PHPUnit/Laravel.

Validación final ejecutada:

```bash
php artisan optimize:clear
composer dump-autoload
php artisan route:list --path=inventario
php artisan test --filter=Inventario
npm run build
```

Resultado validado:

```txt
Backend: 86 tests passed / 434 assertions
Inventario routes: 23 rutas visibles
Frontend: vite build OK / PWA generada correctamente
```

---

## 1. Resumen ejecutivo

La Fase 5 agrega trazabilidad avanzada mediante **lotes**, **fechas de vencimiento** y **stock granular por lote**, manteniendo intacta la arquitectura ya construida en fases anteriores.

El objetivo principal fue permitir que un producto pueda manejar stock por lote sin romper:

- stock consolidado por producto/bodega;
- Kardex;
- PMP;
- valorización;
- multiempresa;
- RBAC;
- auditoría;
- contrato API actual.

La implementación mantiene `inventario_stock` como fuente del **stock físico consolidado**, y agrega `inventario_stock_lotes` como desglose granular. Los movimientos siguen registrándose en `inventario_movimientos`, mientras que la trazabilidad específica por lote queda en `inventario_movimiento_lotes`.

---

## 2. Alcance funcional

### Incluido en esta fase

- Configuración de producto para manejo de lotes.
- Configuración de producto para exigir fecha de vencimiento.
- CRUD básico de lotes.
- Consulta de stock por lote.
- Consulta de lotes por producto.
- Entrada con lote nuevo.
- Entrada con lote existente.
- Salida desde lote específico.
- Traspaso conservando lote entre bodegas.
- Kardex filtrado por lote.
- Trazabilidad auditable de stock antes/después por lote.
- RBAC para lotes.
- Seeders demo opcionales para Postman.
- Tests Feature/API de lotes.
- Tests Feature/API de movimientos con lote.
- Validación Postman mínima end-to-end.

### Fuera de alcance

- Emisión o gestión DTE.
- Campos tributarios o SII.
- FEFO/FIFO automático.
- Bloqueo automático de lotes vencidos.
- Pantallas frontend completas de lotes.
- Dashboard de vencimientos.
- Reglas de reposición.

---

## 3. Reglas permanentes respetadas

Inventario **NO emite, gestiona ni prepara DTE**.

No se agregó:

- `codigo_dte`
- `codigo_sii`
- `folio_dte`
- `xml_dte`
- `emitir_dte`
- lógica tributaria/SII

Para trazabilidad externa se mantienen campos genéricos:

- `referencia`
- `motivo`
- `observacion`
- `origen_modulo`
- `origen_id`

Reglas RBAC respetadas:

- Inventario no crea roles.
- Inventario no crea usuarios.
- Inventario no asigna permisos automáticamente en migraciones.
- Inventario no modifica `DatabaseSeeder`.
- Los permisos se exponen para gestión visual y seeders demo opcionales.

---

## 4. Diseño técnico aplicado

### Decisión central

Se decidió **extender el flujo existente de movimientos** en vez de crear un endpoint paralelo de movimientos por lote.

Endpoint extendido:

```http
POST /api/inventario/movimientos
```

Ahora puede recibir:

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

### Separación de responsabilidades

| Componente | Responsabilidad |
|---|---|
| `InventarioMovimientoService` | Stock consolidado, Kardex, PMP, valorización y movimiento físico principal |
| `InventarioLoteService` | Resolución de lote, stock granular por lote y detalle auditable |
| `inventario_stock` | Stock consolidado oficial por producto/bodega |
| `inventario_stock_lotes` | Stock granular por producto/bodega/lote |
| `inventario_movimientos` | Cabecera principal de Kardex |
| `inventario_movimiento_lotes` | Detalle trazable del lote afectado |

### Invariante crítica

Para productos con lote, la suma granular debe coincidir con el stock consolidado:

```txt
inventario_stock.stock_actual == SUM(inventario_stock_lotes.stock_actual)
por empresa + producto + bodega
```

Esta invariante quedó cubierta por tests.

---

## 5. Base de datos

### Migración creada

```txt
database/migrations/2026_05_05_100000_create_inventario_lotes_tables.php
```

### Cambios en `inventario_productos`

| Campo | Tipo | Default | Descripción |
|---|---:|---:|---|
| `maneja_lotes` | boolean | false | Define si el producto exige trazabilidad por lote |
| `requiere_fecha_vencimiento` | boolean | false | Define si sus lotes deben tener vencimiento |

Ambos campos quedan en `false` por defecto para no romper productos existentes.

### Tabla `inventario_lotes`

Tabla maestra de lotes.

| Campo | Descripción |
|---|---|
| `id` | Identificador del lote |
| `empresa_id` | Empresa dueña del lote |
| `producto_id` | Producto asociado |
| `codigo_lote` | Código único del lote dentro de empresa/producto |
| `fecha_fabricacion` | Fecha opcional de fabricación |
| `fecha_vencimiento` | Fecha opcional u obligatoria según producto |
| `observacion` | Observación interna |
| `activo` | Estado del lote |
| `created_at`, `updated_at` | Auditoría base |

Restricción principal:

```txt
unique(empresa_id, producto_id, codigo_lote)
```

### Tabla `inventario_stock_lotes`

Tabla de stock granular por lote/bodega.

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

### Tabla `inventario_movimiento_lotes`

Tabla auditable de detalle por lote en movimientos.

| Campo | Descripción |
|---|---|
| `movimiento_inventario_id` | Movimiento principal asociado |
| `producto_id` | Producto afectado |
| `lote_id` | Lote afectado |
| `bodega_origen_id` | Bodega origen si aplica |
| `bodega_destino_id` | Bodega destino si aplica |
| `cantidad` | Cantidad afectada |
| `stock_lote_origen_antes` | Stock antes en origen |
| `stock_lote_origen_despues` | Stock después en origen |
| `stock_lote_destino_antes` | Stock antes en destino |
| `stock_lote_destino_despues` | Stock después en destino |
| `costo_unitario` | Costo unitario del movimiento |
| `costo_total` | Costo total del movimiento |

### Cambio en `inventario_ajustes_criticos`

Se agregó:

| Campo | Descripción |
|---|---|
| `lote_id` nullable | Permite asociar ajustes críticos, mermas o vencimientos a lote cuando aplique |

---

## 6. Models

### Models nuevos

```txt
app/Domains/Inventario/Models/LoteInventario.php
app/Domains/Inventario/Models/StockLoteInventario.php
app/Domains/Inventario/Models/MovimientoLoteInventario.php
```

### Models modificados

```txt
app/Domains/Inventario/Models/Producto.php
app/Domains/Inventario/Models/MovimientoInventario.php
app/Domains/Inventario/Models/AjusteCriticoInventario.php
app/Domains/Inventario/Models/StockProducto.php
app/Domains/Inventario/Models/Bodega.php
```

### Relaciones relevantes

`Producto`:

- `lotes()`
- `stockLotes()`
- `movimientosLotes()`
- `ajustesCriticos()`
- `manejaLotes()`
- `requiereFechaVencimiento()`

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

## 7. Services

### Nuevo service: `InventarioLoteService`

Archivo:

```txt
app/Domains/Inventario/Services/InventarioLoteService.php
```

Responsabilidades:

- Listar lotes.
- Obtener detalle de lote.
- Crear lote.
- Actualizar lote.
- Listar lotes por producto.
- Consultar stock por lote.
- Validar multiempresa.
- Validar producto activo.
- Validar lote activo.
- Validar duplicidad de lote por empresa/producto.
- Validar fecha de vencimiento requerida.
- Resolver lote para entrada/salida/traspaso.
- Crear stock por lote si no existe.
- Bloquear stock granular con `lockForUpdate()`.
- Actualizar stock granular.
- Registrar detalle en `inventario_movimiento_lotes`.

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

### Service modificado: `InventarioMovimientoService`

Archivo:

```txt
app/Domains/Inventario/Services/InventarioMovimientoService.php
```

Se inyectó `InventarioLoteService`:

```php
public function __construct(
    private readonly InventarioValorizacionService $valorizacionService,
    private readonly InventarioLoteService $loteService
) {
}
```

Cambios funcionales:

- Entrada con lote nuevo.
- Entrada con lote existente.
- Salida desde lote.
- Traspaso conservando lote.
- Ajuste positivo con lote.
- Ajuste negativo con lote.
- Filtro `lote_id` en movimientos y Kardex.
- Carga de relaciones de lote en respuestas.
- Validación de `maneja_lotes`.
- Rechazo de payload de lote si el producto no maneja lotes.

### Service modificado: `InventarioService`

Archivo:

```txt
app/Domains/Inventario/Services/InventarioService.php
```

Se corrigió la persistencia de:

```php
'maneja_lotes' => $datos['maneja_lotes'] ?? false,
'requiere_fecha_vencimiento' => $datos['requiere_fecha_vencimiento'] ?? false,
```

En:

- `crearProducto()`
- `actualizarProducto()`

También se agregó validación de coherencia:

```php
if ($requiereFechaVencimiento && !$manejaLotes) {
    throw new Exception('Un producto que requiere fecha de vencimiento debe manejar lotes.');
}
```

Esta corrección fue validada durante Postman, porque inicialmente el producto se creaba pero quedaba con `maneja_lotes = false` y `requiere_fecha_vencimiento = false`.

---

## 8. Controller y rutas

### Controller modificado

```txt
app/Domains/Inventario/Controllers/InventarioController.php
```

Cambios:

- Inyección de `InventarioLoteService`.
- Validación de productos con `maneja_lotes` y `requiere_fecha_vencimiento`.
- Validación de movimientos con `lote_id` y `lote`.
- Filtro `lote_id` en movimientos y Kardex.
- Respuesta de movimientos con relaciones de lote.
- Métodos públicos para endpoints de lotes.

Métodos agregados:

```php
lotes(Request $request)
storeLote(Request $request)
showLote(Request $request, $id)
updateLote(Request $request, $id)
lotesProducto(Request $request, $id)
stockLote(Request $request, $id)
```

### Rutas agregadas

Archivo:

```txt
routes/api.php
```

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

---

## 9. Endpoints

### Lotes

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/api/inventario/lotes` | Lista lotes |
| POST | `/api/inventario/lotes` | Crea lote |
| GET | `/api/inventario/lotes/{id}` | Detalle de lote |
| PUT | `/api/inventario/lotes/{id}` | Actualiza lote |
| GET | `/api/inventario/lotes/{id}/stock` | Consulta stock por lote |
| GET | `/api/inventario/productos/{id}/lotes` | Lista lotes de un producto |

### Movimientos/Kardex extendidos

| Método | Endpoint | Cambio |
|---|---|---|
| POST | `/api/inventario/movimientos` | Acepta `lote_id` o payload `lote` |
| GET | `/api/inventario/movimientos?lote_id=1` | Filtra movimientos por lote |
| GET | `/api/inventario/kardex?lote_id=1` | Filtra Kardex por lote |
| GET | `/api/inventario/productos/{id}/kardex?lote_id=1` | Filtra Kardex del producto por lote |

---

## 10. Permisos RBAC

Permisos nuevos:

```txt
inventario.lotes.ver
inventario.lotes.crear
inventario.lotes.editar
```

Archivos ajustados:

| Archivo | Cambio |
|---|---|
| `Frontend/src/Modulos/Administrador/GestionRoles.jsx` | Permisos visibles para asignación visual |
| `app/Domains/Core/Controllers/AuthController.php` | Permisos runtime para Administrador |
| `database/seeders/InventarioDemoPermisosSeeder.php` | Permisos demo opcionales |
| `tests/Concerns/PreparaInventarioTest.php` | Permisos disponibles para tests |

Permisos demo esperados:

| Rol | Permisos de lote |
|---|---|
| Administrador | ver, crear, editar |
| Contador | ver, crear, editar |
| Auditor | ver |

---

## 11. Reglas de negocio implementadas

1. Un lote pertenece a una empresa y un producto.
2. `codigo_lote` es único por `empresa_id + producto_id`.
3. El mismo código de lote puede existir en otra empresa.
4. No se permite usar lote de otra empresa.
5. No se permite usar lote de otro producto.
6. No se permite usar lote inactivo.
7. No se permite stock negativo por lote.
8. Si `producto.maneja_lotes = true`, los movimientos deben informar lote.
9. Si `producto.maneja_lotes = false`, no se debe enviar `lote_id` ni payload `lote`.
10. Si `producto.requiere_fecha_vencimiento = true`, el lote debe tener `fecha_vencimiento`.
11. Entrada puede crear lote nuevo.
12. Entrada puede usar lote existente.
13. Salida requiere lote existente.
14. Traspaso conserva el mismo lote entre bodegas.
15. Stock consolidado y stock granular deben quedar consistentes.
16. Ajustes positivos/negativos pueden operar con lote mediante el flujo extendido.
17. No se introduce lógica DTE/SII.
18. No se rompe Fase 1, 2, 3 ni 4.

---

## 12. Ejemplos JSON

### Crear producto que maneja lotes

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

### Crear lote

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

### Entrada con lote existente

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

### Salida desde lote

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

### Traspaso con lote

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

### Consultar stock por lote

```http
GET /api/inventario/lotes/1/stock
```

Resultado esperado después de entrada de 10, salida de 2 y traspaso de 3:

```txt
stock_total = 8
bodega_origen = 5
bodega_destino = 3
```

### Kardex filtrado por lote

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

## 13. Tests agregados

### `InventarioLoteApiTest`

```txt
tests/Feature/Inventario/InventarioLoteApiTest.php
```

Cubre:

- Crear lote válido.
- Rechazar lote duplicado por empresa/producto.
- Permitir mismo código de lote en otra empresa.
- Rechazar producto ajeno.
- Rechazar producto inactivo.
- Exigir vencimiento si el producto lo requiere.
- Listar lotes.
- Ver detalle de lote.
- Editar lote.
- Listar lotes por producto.
- Consultar stock por lote.
- Auditor puede consultar lotes.
- Auditor no puede crear ni editar lotes.
- Multiempresa en listado y detalle.
- 401 sin token.

### `InventarioMovimientoLoteApiTest`

```txt
tests/Feature/Inventario/InventarioMovimientoLoteApiTest.php
```

Cubre:

- Entrada con lote nuevo.
- Entrada con lote existente.
- Creación de `inventario_stock_lotes`.
- Creación de `inventario_movimiento_lotes`.
- Salida desde lote.
- Rollback cuando el lote no tiene stock suficiente.
- Traspaso con lote entre bodegas.
- Conservación del lote en traspaso.
- Producto con `maneja_lotes = true` exige lote.
- Producto con `maneja_lotes = false` rechaza payload de lote.
- Kardex filtrado por lote.
- Invariante `inventario_stock == SUM(inventario_stock_lotes)`.

### Resultado final

```bash
php artisan test --filter=Inventario
```

```txt
Tests: 86 passed (434 assertions)
```

---

## 14. Pruebas Postman mínimas validadas

### Seeders demo

```bash
php artisan db:seed --class=InventarioPostmanSeeder
php artisan db:seed --class=InventarioDemoPermisosSeeder
```

Si faltan roles:

```bash
php artisan db:seed --class=RolSeeder
```

### Usuarios demo

```txt
contador@example.com / password
auditor@example.com / password
```

### Flujo validado

1. Login contador.
2. Crear producto con `maneja_lotes = true`.
3. Crear lote.
4. Crear/usar dos bodegas activas.
5. Entrada con lote existente.
6. Consultar stock por lote.
7. Salida desde lote.
8. Consultar stock por lote nuevamente.
9. Traspaso conservando lote.
10. Kardex filtrado por lote.
11. Auditor puede listar lotes.
12. Auditor no puede crear lotes.

### Datos de prueba usados

```txt
producto_id = 2
lote_id = 1
bodega_origen_id = 2
bodega_destino_id = 3
```

### Resultado esperado final

```txt
stock_total_lote = 8
stock_bodega_2 = 5
stock_bodega_3 = 3
```

---

## 15. Comandos de validación

### Backend

```bash
cd Backend-laravel
php artisan optimize:clear
composer dump-autoload
php artisan route:list --path=inventario
php artisan test --filter=Inventario
```

### Sintaxis PHP

```bash
php -l app/Domains/Inventario/Controllers/InventarioController.php
php -l app/Domains/Inventario/Services/InventarioService.php
php -l app/Domains/Inventario/Services/InventarioMovimientoService.php
php -l app/Domains/Inventario/Services/InventarioLoteService.php
php -l app/Domains/Core/Controllers/AuthController.php
php -l database/seeders/InventarioDemoPermisosSeeder.php
php -l tests/Concerns/PreparaInventarioTest.php
```

### Frontend

```bash
cd Frontend
npm install --legacy-peer-deps
npm run build
```

Nota: no ejecutar `npm audit fix` durante esta fase, porque puede actualizar dependencias y romper compatibilidad. El build pasó usando `npm install --legacy-peer-deps`.

---

## 16. Archivos tocados

### Migraciones

```txt
database/migrations/2026_05_05_100000_create_inventario_lotes_tables.php
```

### Models

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

### Services

```txt
app/Domains/Inventario/Services/InventarioLoteService.php
app/Domains/Inventario/Services/InventarioMovimientoService.php
app/Domains/Inventario/Services/InventarioService.php
```

### Controller/rutas

```txt
app/Domains/Inventario/Controllers/InventarioController.php
routes/api.php
```

### RBAC, demo y tests

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

## 17. Checklist técnico final

- [x] Productos soportan `maneja_lotes`.
- [x] Productos soportan `requiere_fecha_vencimiento`.
- [x] `InventarioService` persiste ambos campos.
- [x] Se valida coherencia entre vencimiento y manejo de lotes.
- [x] Se creó tabla `inventario_lotes`.
- [x] Se creó tabla `inventario_stock_lotes`.
- [x] Se creó tabla `inventario_movimiento_lotes`.
- [x] Se agregó `lote_id` nullable en ajustes críticos.
- [x] Se creó `InventarioLoteService`.
- [x] `InventarioMovimientoService` integra lote sin duplicar Kardex/PMP.
- [x] Entrada puede crear lote nuevo.
- [x] Entrada puede usar lote existente.
- [x] Salida descuenta lote específico.
- [x] Traspaso conserva lote entre bodegas.
- [x] Kardex puede filtrar por lote.
- [x] Stock consolidado se mantiene en `inventario_stock`.
- [x] Stock granular se mantiene en `inventario_stock_lotes`.
- [x] Detalle auditable se registra en `inventario_movimiento_lotes`.
- [x] RBAC de lotes agregado.
- [x] GestionRoles muestra permisos de lotes.
- [x] Seeder demo opcional incluye permisos de lotes.
- [x] Tests API de lotes agregados.
- [x] Tests movimientos con lote agregados.
- [x] Tests de Inventario pasan completos.
- [x] Postman mínimo validado.
- [x] Frontend build validado.
- [x] Sin DTE/SII en Inventario.
- [x] Sin migraciones que asignen permisos automáticamente.
- [x] Sin cambios en `DatabaseSeeder`.

---

## 18. Decisiones técnicas y valor profesional para el ERP

### 1. Mantener `inventario_stock` como stock consolidado oficial

**Qué se decidió:**  
El stock consolidado sigue viviendo en `inventario_stock`. La tabla `inventario_stock_lotes` solo agrega desglose granular.

**Por qué se decidió:**  
Las fases anteriores ya usan `inventario_stock` para movimientos, Kardex, PMP y valorización. Cambiar la fuente principal habría arriesgado regresiones.

**Cómo aporta a un ERP profesional:**  
Mantiene una fuente de verdad estable para stock físico y permite agregar trazabilidad avanzada sin romper procesos existentes.

---

### 2. Crear `inventario_stock_lotes` como desglose granular

**Qué se decidió:**  
Se creó una tabla independiente para stock por empresa/producto/bodega/lote.

**Por qué se decidió:**  
Un producto puede tener múltiples lotes en una misma bodega. Guardar lote directamente en `inventario_stock` habría duplicado o desordenado el stock consolidado.

**Cómo aporta a un ERP profesional:**  
Permite trazabilidad sanitaria, logística y operativa por lote, manteniendo reportes consolidados simples y confiables.

---

### 3. Crear `inventario_movimiento_lotes` como detalle auditable

**Qué se decidió:**  
Los movimientos siguen en `inventario_movimientos`, pero el detalle de lote queda en `inventario_movimiento_lotes`.

**Por qué se decidió:**  
Un movimiento debe seguir siendo la cabecera del Kardex, y el lote debe ser detalle granular. Esto evita mezclar responsabilidades.

**Cómo aporta a un ERP profesional:**  
Permite auditoría clara de qué lote se movió, desde qué bodega, hacia cuál y con qué stock antes/después.

---

### 4. Extender `InventarioMovimientoService` en vez de crear flujo paralelo

**Qué se decidió:**  
Los movimientos con lote usan el mismo endpoint y service base de movimientos.

**Por qué se decidió:**  
Ya existía lógica robusta de transacciones, `lockForUpdate()`, stock suficiente, Kardex y PMP.

**Cómo aporta a un ERP profesional:**  
Evita duplicación de lógica crítica, reduce riesgo de inconsistencias y mantiene una única vía oficial para movimientos físicos.

---

### 5. Separar `InventarioLoteService` para trazabilidad granular

**Qué se decidió:**  
Se creó un service específico para lotes.

**Por qué se decidió:**  
La resolución de lotes, validación de vencimiento, stock por lote y detalle granular son responsabilidades especializadas.

**Cómo aporta a un ERP profesional:**  
Mejora mantenibilidad, testabilidad y escalabilidad. Además deja preparada la base para reservas por lote, vencimientos, FEFO/FIFO y reportes avanzados.

---

### 6. Validar `maneja_lotes` y `requiere_fecha_vencimiento` desde producto

**Qué se decidió:**  
El producto define si requiere lote y si exige fecha de vencimiento.

**Por qué se decidió:**  
No todos los productos necesitan trazabilidad por lote. La regla debe ser configurable por producto.

**Cómo aporta a un ERP profesional:**  
Permite que el ERP se adapte a distintos rubros: retail, alimentos, farmacia, insumos, repuestos o servicios internos.

---

### 7. Rechazar payload de lote en productos que no manejan lotes

**Qué se decidió:**  
Si `maneja_lotes = false`, el sistema rechaza `lote_id` o payload `lote`.

**Por qué se decidió:**  
Aceptar lote en productos no configurados generaría trazabilidad inconsistente.

**Cómo aporta a un ERP profesional:**  
Protege la integridad del dato y evita errores operativos difíciles de corregir.

---

### 8. Validar stock por lote además del stock consolidado

**Qué se decidió:**  
Las salidas y traspasos validan stock suficiente a nivel consolidado y a nivel lote.

**Por qué se decidió:**  
Un producto puede tener stock total suficiente, pero no necesariamente en el lote seleccionado.

**Cómo aporta a un ERP profesional:**  
Evita stock negativo por lote y mejora la precisión logística del sistema.

---

### 9. Usar `DB::transaction()` y `lockForUpdate()` en flujos críticos

**Qué se decidió:**  
Las actualizaciones de stock consolidado y stock por lote se ejecutan dentro de transacciones y con bloqueo.

**Por qué se decidió:**  
El inventario es información crítica y sensible a concurrencia.

**Cómo aporta a un ERP profesional:**  
Reduce riesgo de sobreventa, doble salida, stock negativo y errores por operaciones simultáneas.

---

### 10. Mantener RBAC externo al módulo Inventario

**Qué se decidió:**  
La fase agrega permisos, pero no crea roles ni usuarios ni asigna permisos en migraciones.

**Por qué se decidió:**  
La gestión de seguridad pertenece al sistema de roles/permisos, no al dominio Inventario.

**Cómo aporta a un ERP profesional:**  
Mantiene separación de responsabilidades y facilita administración segura en ambientes reales.

---

### 11. Validar con tests API y Postman

**Qué se decidió:**  
La fase se cerró solo después de pruebas automáticas y validación manual mínima.

**Por qué se decidió:**  
Los tests cubren regresión y Postman valida flujo real de uso.

**Cómo aporta a un ERP profesional:**  
Aumenta confianza para demo, reduce fallos en producción y entrega evidencia de calidad técnica.

---

### 12. Documentar decisiones al cierre de la fase

**Qué se decidió:**  
Se agrega esta sección para registrar decisiones técnicas y valor profesional.

**Por qué se decidió:**  
A medida que el ERP crece, es fácil perder el motivo de una decisión si no queda documentada.

**Cómo aporta a un ERP profesional:**  
Facilita mantenimiento, onboarding, defensa técnica ante evaluadores/clientes y futura normalización de documentación.

---

## 19. Riesgos y mejoras futuras

1. Agregar FEFO/FIFO para sugerencia automática de lote en salidas.
2. Agregar endpoints de lotes vencidos y por vencer.
3. Evaluar bloqueo de salida de lotes vencidos según regla de negocio.
4. Agregar pruebas profundas de ajustes críticos con `lote_id`.
5. Crear pantallas frontend específicas para lotes.
6. Crear dashboard de vencimientos.
7. Mejorar colección Postman exportable.
8. Optimizar frontend con lazy loading o code splitting.
9. Revisar vulnerabilidades npm en fase separada.
10. Normalizar todos los README con esta sección de decisiones.

---

## 20. Commit sugerido

Commit principal:

```txt
feat(inventario): agregar lotes vencimientos y trazabilidad avanzada
```

Descripción sugerida:

```txt
- Agrega configuración de productos para manejo de lotes y vencimientos.
- Crea tablas inventario_lotes, inventario_stock_lotes e inventario_movimiento_lotes.
- Agrega modelos y relaciones para trazabilidad por lote.
- Implementa InventarioLoteService.
- Integra lotes en InventarioMovimientoService sin duplicar Kardex/PMP.
- Extiende movimientos con lote_id y payload lote.
- Agrega endpoints CRUD/consulta de lotes.
- Agrega filtro por lote en movimientos y Kardex.
- Agrega permisos RBAC de lotes en UI, Auth runtime y seeders demo.
- Agrega tests Feature/API de lotes.
- Agrega tests de movimientos con lote e invariantes de stock.
- Valida flujo mínimo Postman de entrada, salida y traspaso con lote.
- Documenta decisiones técnicas y valor profesional para el ERP.
```

---

## 21. Estado final de la Fase 5

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
✅ README técnico refactorizado
```

Resultado de calidad:

```txt
86 tests passed
434 assertions
Frontend build OK
```
