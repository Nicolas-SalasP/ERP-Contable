# README — Fase 7 Inventario: Toma física e inventario cíclico

## 1. Contexto del módulo

Proyecto: **ERP Contable**  
Rama de trabajo: **SLagos-dev**  
Dominio: `Backend-laravel/app/Domains/Inventario`  
Stack backend: Laravel 12, PHP 8.2+, MySQL/MariaDB, Sanctum, PHPUnit/Laravel.  
Stack frontend disponible: React + Vite + Tailwind.

Esta fase corresponde a la **Fase 7 del roadmap oficial del módulo Inventario**:

1. Productos, bodegas y stock base.
2. Movimientos y Kardex.
3. PMP y valorización.
4. Mermas y ajustes críticos.
5. Lotes, vencimientos y trazabilidad avanzada.
6. Reservas y disponibilidad comprometida.
7. **Toma física e inventario cíclico.**
8. Reglas de reposición y alertas.
9. Reportes avanzados y dashboard.
10. Frontend de Inventario.
11. Hardening, auditoría final y producto vendible.

La Fase 7 agrega una capa profesional para registrar conteos físicos, comparar contra el stock físico que tiene el sistema y generar ajustes controlados cuando existan diferencias.

---

## 2. Regla crítica permanente: Inventario no maneja DTE

El módulo de Inventario **no emite, no gestiona y no prepara DTE**.

No se introducen campos ni lógica como:

- `codigo_dte`
- `codigo_sii`
- `folio_dte`
- `xml_dte`
- `emitir_dte`
- lógica tributaria/SII

Cuando se necesita trazabilidad externa u operativa se usan campos genéricos:

- `referencia`
- `motivo`
- `observacion`
- `origen_modulo`
- `origen_id`

Esto mantiene Inventario desacoplado de tributación y evita mezclar responsabilidades con futuros módulos DTE/SII.

---

## 3. Objetivo de la Fase 7

Implementar el flujo backend para:

- Crear tomas físicas de inventario.
- Preparar snapshot de stock físico del sistema.
- Registrar conteos reales.
- Calcular diferencias.
- Cerrar tomas físicas para revisión.
- Ajustar tomas cerradas generando movimientos reales en Kardex.
- Cancelar tomas antes de afectar stock.
- Respetar lotes, bodegas, multiempresa, permisos y auditoría.

La fórmula central de la fase es:

```txt
diferencia = stock_contado - stock_sistema
```

Comportamiento:

```txt
diferencia > 0  => ajuste_positivo
diferencia < 0  => ajuste_negativo
diferencia = 0  => no genera movimiento
```

Principio central:

> La toma física compara contra **stock físico**, no contra stock disponible.

Por lo tanto, las reservas activas no reducen ni modifican el `stock_sistema` capturado en la toma física.

---

## 4. Alcance implementado

La Fase 7 quedó implementada como backend operativo 7.0.

Incluye:

- Migración de tablas de tomas físicas.
- Models Eloquent para cabecera y detalle.
- Service de dominio para reglas de negocio.
- Endpoints REST bajo `/api/inventario/tomas-fisicas`.
- RBAC granular.
- Actualización de seeders demo de permisos.
- Actualización de helper de tests.
- Test Feature/API completo.
- Flujo validado en Postman/cURL.
- Integración con movimientos reales, Kardex, PMP, valorización, stock consolidado y stock por lote.

No incluye:

- Frontend completo de Inventario.
- Programación automática de ciclos recurrentes.
- Reglas avanzadas ABC o conteo por familia/categoría.
- Reportes gráficos finales.
- DTE/SII.

---

## 5. Archivos principales agregados o actualizados

### Backend — Dominio Inventario

```txt
Backend-laravel/app/Domains/Inventario/Models/TomaFisicaInventario.php
Backend-laravel/app/Domains/Inventario/Models/TomaFisicaDetalleInventario.php
Backend-laravel/app/Domains/Inventario/Services/InventarioTomaFisicaService.php
Backend-laravel/app/Domains/Inventario/Controllers/InventarioController.php
```

### Migraciones

```txt
Backend-laravel/database/migrations/2026_05_06_120000_create_inventario_tomas_fisicas_tables.php
```

### Rutas

```txt
Backend-laravel/routes/api.php
```

### RBAC y demo

```txt
Backend-laravel/app/Domains/Core/Controllers/AuthController.php
Backend-laravel/Frontend/src/Modulos/Administrador/GestionRoles.jsx
Backend-laravel/database/seeders/InventarioDemoPermisosSeeder.php
Backend-laravel/database/seeders/InventarioPostmanSeeder.php
Backend-laravel/tests/Concerns/PreparaInventarioTest.php
```

### Tests

```txt
Backend-laravel/tests/Feature/Inventario/InventarioTomaFisicaApiTest.php
```

---

## 6. Base de datos

### 6.1 Tabla `inventario_tomas_fisicas`

Tabla de cabecera de la toma física.

Campos principales:

| Campo | Descripción |
|---|---|
| `id` | Identificador interno. |
| `empresa_id` | Empresa propietaria del registro. |
| `codigo_toma` | Código único por empresa para trazabilidad. |
| `estado` | Estado de ciclo de vida de la toma. |
| `tipo` | GENERAL, BODEGA o CICLICA. |
| `bodega_id` | Bodega de cabecera cuando aplica. |
| `referencia` | Referencia operativa genérica. |
| `motivo` | Motivo operacional de la toma. |
| `observacion` | Observación general. |
| `origen_modulo` | Módulo de origen opcional. |
| `origen_id` | ID externo opcional del origen. |
| `creado_por` | Usuario que creó la toma. |
| `cerrado_por` | Usuario que cerró la toma. |
| `ajustado_por` | Usuario que aplicó ajuste. |
| `cancelado_por` | Usuario que canceló. |
| `fecha_inicio` | Fecha en que pasa a EN_CONTEO. |
| `fecha_cierre` | Fecha en que pasa a CERRADA. |
| `fecha_ajuste` | Fecha en que pasa a AJUSTADA. |
| `fecha_cancelacion` | Fecha en que pasa a CANCELADA. |
| `created_at`, `updated_at` | Timestamps Laravel. |

Estados permitidos:

```txt
BORRADOR
EN_CONTEO
CERRADA
AJUSTADA
CANCELADA
```

Tipos permitidos:

```txt
GENERAL
BODEGA
CICLICA
```

Índices relevantes:

- `empresa_id + codigo_toma` único.
- `empresa_id + estado`.
- `empresa_id + tipo`.
- `empresa_id + bodega_id`.
- `empresa_id + fecha_inicio`.
- `empresa_id + fecha_cierre`.
- `empresa_id + fecha_ajuste`.
- `empresa_id + origen_modulo + origen_id`.
- `empresa_id + referencia`.

---

### 6.2 Tabla `inventario_toma_fisica_detalles`

Tabla de detalle de la toma física. Representa el conteo por producto, bodega y lote opcional.

Campos principales:

| Campo | Descripción |
|---|---|
| `id` | Identificador interno. |
| `empresa_id` | Empresa propietaria. |
| `toma_fisica_id` | Cabecera asociada. |
| `producto_id` | Producto contado. |
| `bodega_id` | Bodega contada. |
| `lote_id` | Lote contado cuando aplica. |
| `stock_sistema` | Snapshot del stock físico del sistema. |
| `stock_contado` | Cantidad física registrada por usuario. |
| `diferencia` | `stock_contado - stock_sistema`. |
| `movimiento_ajuste_id` | Movimiento real generado al ajustar. |
| `observacion` | Observación del conteo. |
| `contado_por` | Usuario que registró el conteo. |
| `fecha_conteo` | Fecha del conteo. |
| `created_at`, `updated_at` | Timestamps Laravel. |

Índices relevantes:

- `empresa_id + toma_fisica_id`.
- `empresa_id + producto_id + bodega_id`.
- `empresa_id + producto_id + bodega_id + lote_id`.
- `empresa_id + lote_id`.
- `empresa_id + movimiento_ajuste_id`.
- `empresa_id + contado_por`.
- `empresa_id + fecha_conteo`.

Restricción lógica:

```txt
toma_fisica_id + producto_id + bodega_id + lote_id
```

En MySQL/MariaDB, un `UNIQUE` con `lote_id` nullable puede permitir duplicados cuando `lote_id` es `NULL`, por lo que el Service también refuerza la prevención de duplicados.

---

## 7. Models agregados

### 7.1 `TomaFisicaInventario`

Archivo:

```txt
app/Domains/Inventario/Models/TomaFisicaInventario.php
```

Responsabilidades:

- Representar la cabecera de la toma física.
- Declarar constantes de estados.
- Declarar constantes de tipos.
- Exponer relaciones con empresa, bodega, usuarios y detalles.
- Exponer scopes de consulta.
- Exponer helpers de estado y tipo.
- Centralizar reglas simples como `puedeIniciarse`, `puedeContarse`, `puedeCerrarse`, `puedeAjustarse` y `puedeCancelarse`.

Relaciones principales:

```txt
empresa()
bodega()
creadoPor()
cerradoPor()
ajustadoPor()
canceladoPor()
detalles()
```

Helpers relevantes:

```txt
estaBorrador()
estaEnConteo()
estaCerrada()
estaAjustada()
estaCancelada()
puedeIniciarse()
puedeContarse()
puedeCerrarse()
puedeAjustarse()
puedeCancelarse()
esGeneral()
esPorBodega()
esCiclica()
requiereBodegaCabecera()
tieneDetallesPendientesDeConteo()
tieneDiferencias()
tieneDetallesAjustados()
```

---

### 7.2 `TomaFisicaDetalleInventario`

Archivo:

```txt
app/Domains/Inventario/Models/TomaFisicaDetalleInventario.php
```

Responsabilidades:

- Representar cada línea de conteo.
- Relacionar producto, bodega, lote y movimiento de ajuste.
- Calcular diferencia.
- Determinar si requiere movimiento de ajuste.
- Exponer scopes para filtros operativos.

Relaciones principales:

```txt
empresa()
tomaFisica()
producto()
bodega()
lote()
movimientoAjuste()
contadoPor()
```

Helpers relevantes:

```txt
tieneLote()
fueContado()
estaPendienteConteo()
fueAjustado()
tieneDiferencia()
tieneDiferenciaPositiva()
tieneDiferenciaNegativa()
tieneDiferenciaCero()
requiereMovimientoAjuste()
calcularDiferencia()
cantidadAbsolutaDiferencia()
```

---

## 8. Service de dominio

### `InventarioTomaFisicaService`

Archivo:

```txt
app/Domains/Inventario/Services/InventarioTomaFisicaService.php
```

Responsabilidades principales:

- `listar()`
- `obtener()`
- `crear()`
- `iniciar()`
- `registrarConteos()`
- `cerrar()`
- `ajustar()`
- `cancelar()`
- Preparar snapshot del stock físico.
- Validar estados del flujo.
- Validar permisos mediante `InventarioPermisoService`.
- Validar multiempresa.
- Validar producto, bodega y lote.
- Delegar ajustes reales a `InventarioMovimientoService`.

El service trabaja con `DB::transaction()` y bloqueos cuando corresponde para proteger consistencia en operaciones críticas.

---

## 9. Flujo funcional de toma física

### 9.1 Crear toma física

Endpoint:

```txt
POST /api/inventario/tomas-fisicas
```

Qué hace:

- Crea cabecera en estado `BORRADOR`.
- Genera `codigo_toma` único por empresa.
- Prepara detalles con snapshot de stock físico.
- No modifica stock.
- No genera movimientos.

Reglas:

- `GENERAL` puede no tener `bodega_id`.
- `BODEGA` requiere `bodega_id`.
- `CICLICA` requiere `bodega_id` en esta fase.
- Solo considera productos activos.
- Solo considera bodegas activas.
- Para productos sin lote usa `inventario_stock.stock_actual`.
- Para productos con lote usa `inventario_stock_lotes.stock_actual`.

---

### 9.2 Iniciar toma física

Endpoint:

```txt
POST /api/inventario/tomas-fisicas/{id}/iniciar
```

Qué hace:

- Cambia estado de `BORRADOR` a `EN_CONTEO`.
- Completa `fecha_inicio`.
- No modifica stock.
- No genera movimientos.

Regla:

- Solo una toma `BORRADOR` puede iniciarse.

---

### 9.3 Registrar conteos

Endpoint:

```txt
POST /api/inventario/tomas-fisicas/{id}/conteos
```

Qué hace:

- Recibe uno o más detalles contados.
- Registra `stock_contado`.
- Calcula `diferencia`.
- Completa `contado_por` y `fecha_conteo`.
- No modifica stock físico.
- No genera movimientos.

Payload base:

```json
{
  "detalles": [
    {
      "detalle_id": 1,
      "stock_contado": 12,
      "observacion": "Conteo físico validado"
    }
  ]
}
```

Regla:

- Solo una toma `EN_CONTEO` permite registrar conteos.
- `stock_contado` debe ser numérico y mayor o igual a cero.

---

### 9.4 Cerrar toma física

Endpoint:

```txt
POST /api/inventario/tomas-fisicas/{id}/cerrar
```

Qué hace:

- Cambia estado de `EN_CONTEO` a `CERRADA`.
- Completa `cerrado_por` y `fecha_cierre`.
- Deja diferencias listas para revisión.
- No modifica stock físico.
- No genera movimientos.

Payload opcional:

```json
{
  "observacion": "Conteo revisado por supervisor"
}
```

Reglas:

- Solo una toma `EN_CONTEO` puede cerrarse.
- No se puede cerrar si existen detalles sin conteo.

---

### 9.5 Ajustar toma física

Endpoint:

```txt
POST /api/inventario/tomas-fisicas/{id}/ajustar
```

Qué hace:

- Recorre detalles de la toma cerrada.
- Si la diferencia es positiva, genera `ajuste_positivo`.
- Si la diferencia es negativa, genera `ajuste_negativo`.
- Si la diferencia es cero, no genera movimiento.
- Guarda `movimiento_ajuste_id` en cada detalle ajustado.
- Cambia estado a `AJUSTADA`.
- Completa `ajustado_por` y `fecha_ajuste`.

Payload base:

```json
{
  "referencia": "AJ-TF-POSTMAN-001",
  "motivo": "correccion_stock",
  "observacion": "Ajuste generado desde toma física",
  "costo_unitario": 2500
}
```

También se permite usar costos unitarios por detalle:

```json
{
  "referencia": "AJ-TF-POSTMAN-001",
  "motivo": "correccion_stock",
  "observacion": "Ajuste generado desde toma física",
  "costos_unitarios": {
    "1": 2500,
    "2": 1800
  }
}
```

Reglas:

- Solo una toma `CERRADA` puede ajustarse.
- No se permite ajustar dos veces.
- No se permite ajustar una toma cancelada.
- No se permite ajustar si hay detalles pendientes de conteo.
- El ajuste real se delega a `InventarioMovimientoService`.
- El movimiento generado conserva Kardex, PMP, valorización, stock consolidado y stock por lote.
- Para diferencia positiva se requiere resolver costo unitario.

---

### 9.6 Cancelar toma física

Endpoint:

```txt
POST /api/inventario/tomas-fisicas/{id}/cancelar
```

Qué hace:

- Cambia estado a `CANCELADA`.
- Completa `cancelado_por` y `fecha_cancelacion`.
- No modifica stock físico.
- No genera movimientos.

Payload opcional:

```json
{
  "observacion": "Toma cancelada por recuento duplicado"
}
```

Reglas:

- Solo se puede cancelar una toma en `BORRADOR` o `EN_CONTEO`.
- No se puede cancelar una toma `CERRADA`, `AJUSTADA` o ya `CANCELADA`.

---

## 10. Endpoints agregados

Todos los endpoints están bajo middleware `auth:sanctum`.

```txt
GET    /api/inventario/tomas-fisicas
POST   /api/inventario/tomas-fisicas
GET    /api/inventario/tomas-fisicas/{id}
POST   /api/inventario/tomas-fisicas/{id}/iniciar
POST   /api/inventario/tomas-fisicas/{id}/conteos
POST   /api/inventario/tomas-fisicas/{id}/cerrar
POST   /api/inventario/tomas-fisicas/{id}/ajustar
POST   /api/inventario/tomas-fisicas/{id}/cancelar
```

---

## 11. Listado y filtros

Endpoint:

```txt
GET /api/inventario/tomas-fisicas
```

Filtros soportados:

```txt
estado
 tipo
bodega_id
referencia
desde
hasta
page
per_page
```

Ejemplo:

```txt
GET /api/inventario/tomas-fisicas?estado=CERRADA&tipo=BODEGA&per_page=15
```

Respuesta paginada sigue el patrón actual del backend:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 0
  }
}
```

---

## 12. Contrato API mantenido

Respuestas exitosas:

```json
{
  "success": true,
  "data": {},
  "message": "Operación realizada correctamente."
}
```

Errores de dominio, validación o permisos:

```json
{
  "success": false,
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "campo": ["Mensaje de error"]
  }
}
```

HTTP esperado:

```txt
200 OK       => consultas y acciones correctas
201 Created  => creación correcta
401          => sin token Sanctum
422          => errores de validación, dominio o permisos
```

---

## 13. Permisos RBAC agregados

```txt
inventario.tomas_fisicas.ver
inventario.tomas_fisicas.crear
inventario.tomas_fisicas.contar
inventario.tomas_fisicas.cerrar
inventario.tomas_fisicas.ajustar
inventario.tomas_fisicas.cancelar
```

Regla de perfiles demo:

| Perfil | Permisos |
|---|---|
| Administrador demo | Todos los permisos de toma física. |
| Contador demo | Todos los permisos de toma física. |
| Auditor demo | Solo `inventario.tomas_fisicas.ver`. |

Reglas permanentes de RBAC:

- Inventario no crea roles.
- Inventario no crea usuarios.
- Inventario no asigna permisos automáticamente en migraciones.
- Inventario no asigna permisos automáticamente en seeders base.
- Inventario solo valida permisos existentes mediante `InventarioPermisoService`.
- La gestión de roles/permisos queda fuera del dominio Inventario.
- Los seeders demo no deben agregarse a `DatabaseSeeder`.

---

## 14. Reglas de negocio implementadas

- Crear toma física no modifica stock.
- Iniciar toma física no modifica stock.
- Registrar conteos no modifica stock.
- Cerrar toma física no modifica stock.
- Solo ajustar modifica stock real.
- Solo se puede ajustar una toma `CERRADA`.
- No se permite ajustar dos veces.
- No se permite ajustar una toma `CANCELADA`.
- No se permite contar una toma `AJUSTADA`.
- No se permite cerrar con detalles pendientes de conteo.
- Diferencia positiva genera `ajuste_positivo`.
- Diferencia negativa genera `ajuste_negativo`.
- Diferencia cero no genera movimiento.
- El movimiento generado se guarda en `movimiento_ajuste_id`.
- El ajuste real se delega a `InventarioMovimientoService`.
- Se mantiene Kardex.
- Se mantiene PMP.
- Se mantiene valorización.
- Se mantiene stock consolidado.
- Se mantiene stock por lote.
- Las reservas no se cancelan, liberan ni consumen desde toma física.
- Las reservas activas no alteran `stock_sistema`.
- Producto con lote conserva `lote_id` en detalle.
- Producto sin lote no usa `lote_id`.
- El snapshot de `stock_sistema` se captura desde stock físico.
- Multiempresa se obtiene desde el usuario autenticado y no desde payload.

---

## 15. Integración con fases anteriores

### Fase 1 — Productos, bodegas y stock base

La toma física usa:

- Productos activos.
- Bodegas activas.
- Stock físico base.
- Multiempresa.

### Fase 2 — Movimientos y Kardex

El ajuste de toma física no actualiza stock directamente. Delega a `InventarioMovimientoService`, por lo que se preserva el Kardex.

### Fase 3 — PMP y valorización

Los ajustes positivos resuelven costo unitario para no romper PMP ni valorización.

### Fase 4 — Mermas y ajustes críticos

La toma física no reemplaza ajustes críticos. Es un flujo distinto: primero conteo, luego diferencias, luego ajuste controlado.

### Fase 5 — Lotes y vencimientos

Si el producto maneja lote, el detalle de toma conserva `lote_id` y el ajuste respeta `inventario_stock_lotes`.

### Fase 6 — Reservas y disponibilidad comprometida

La toma física compara contra stock físico, no contra stock disponible. Las reservas activas no alteran el snapshot de `stock_sistema`.

---

## 16. Flujo Postman validado

### 16.1 Login contador

```txt
POST /api/auth/login
```

Guardar token en variable:

```txt
{{token_contador}}
```

Header para requests protegidos:

```txt
Authorization: Bearer {{token_contador}}
Accept: application/json
Content-Type: application/json
```

---

### 16.2 Crear producto

Usar endpoint de productos de Fase 1.

---

### 16.3 Crear bodega

Usar endpoint de bodegas/catalogos de Fase 1.

---

### 16.4 Registrar entrada inicial

Endpoint:

```txt
POST /api/inventario/movimientos
```

Payload:

```json
{
  "tipo": "entrada",
  "producto_id": "{{producto_id}}",
  "bodega_destino_id": "{{bodega_id}}",
  "cantidad": 10,
  "costo_unitario": 2500,
  "referencia": "ENT-TF-001",
  "motivo": "compra",
  "observacion": "Entrada inicial para prueba de toma física"
}
```

Importante:

Los tipos de movimientos van en minúscula:

```txt
entrada
salida
traspaso
ajuste_positivo
ajuste_negativo
```

---

### 16.5 Crear toma física por bodega

Endpoint:

```txt
POST /api/inventario/tomas-fisicas
```

Payload:

```json
{
  "tipo": "BODEGA",
  "bodega_id": "{{bodega_id}}",
  "referencia": "TF-POSTMAN-001",
  "motivo": "inventario_ciclico",
  "observacion": "Toma física demo por bodega"
}
```

Importante:

Los tipos de toma física van en mayúscula:

```txt
GENERAL
BODEGA
CICLICA
```

Resultado esperado:

```txt
estado = BORRADOR
stock_sistema = 10
stock físico no modificado
```

---

### 16.6 Iniciar toma física

Endpoint:

```txt
POST /api/inventario/tomas-fisicas/{{toma_fisica_id}}/iniciar
```

Resultado esperado:

```txt
estado = EN_CONTEO
```

---

### 16.7 Registrar conteo

Endpoint:

```txt
POST /api/inventario/tomas-fisicas/{{toma_fisica_id}}/conteos
```

Payload:

```json
{
  "detalles": [
    {
      "detalle_id": "{{detalle_toma_id}}",
      "stock_contado": 12,
      "observacion": "Conteo físico validado por Postman"
    }
  ]
}
```

Resultado esperado:

```txt
stock_sistema = 10
stock_contado = 12
diferencia = 2
stock físico no modificado
```

---

### 16.8 Cerrar toma

Endpoint:

```txt
POST /api/inventario/tomas-fisicas/{{toma_fisica_id}}/cerrar
```

Payload opcional:

```json
{
  "observacion": "Toma cerrada desde Postman"
}
```

Resultado esperado:

```txt
estado = CERRADA
stock físico no modificado
```

---

### 16.9 Ajustar toma

Endpoint:

```txt
POST /api/inventario/tomas-fisicas/{{toma_fisica_id}}/ajustar
```

Payload:

```json
{
  "referencia": "AJ-TF-POSTMAN-001",
  "motivo": "correccion_stock",
  "observacion": "Ajuste generado desde toma física Postman",
  "costo_unitario": 2500
}
```

Resultado esperado:

```txt
estado = AJUSTADA
movimiento_ajuste_id generado
movimiento_ajuste.tipo = ajuste_positivo
movimiento_ajuste.cantidad = 2
Kardex contiene referencia AJ-TF-POSTMAN-001
```

---

### 16.10 Validar doble ajuste

Repetir:

```txt
POST /api/inventario/tomas-fisicas/{{toma_fisica_id}}/ajustar
```

Resultado esperado:

```json
{
  "success": false,
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "estado": [
      "Solo una toma física CERRADA puede ajustarse."
    ]
  }
}
```

HTTP esperado:

```txt
422 Unprocessable Entity
```

---

### 16.11 Validar sin token

Comando validado:

```bash
curl.exe -i \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  http://127.0.0.1:8000/api/inventario/tomas-fisicas
```

Resultado esperado:

```txt
HTTP/1.1 401 Unauthorized
{"message":"Unauthenticated."}
```

Nota:

En Postman puede aparecer 500 si el request sin token no envía `Accept: application/json` o si hereda Authorization desde la colección. El comportamiento correcto fue validado por cURL con 401.

---

## 17. Tests implementados

Archivo:

```txt
tests/Feature/Inventario/InventarioTomaFisicaApiTest.php
```

Casos cubiertos:

- Crear toma física por bodega.
- Crear toma física general.
- Preparar snapshot `stock_sistema`.
- Registrar conteo sin modificar stock físico.
- Calcular diferencia positiva.
- Calcular diferencia negativa.
- Cerrar toma física.
- Aplicar ajuste positivo delegando a `InventarioMovimientoService`.
- Aplicar ajuste negativo delegando a `InventarioMovimientoService`.
- Ajuste con lote actualiza `stock_lote` correctamente.
- Diferencia cero no genera movimiento.
- No permite ajustar dos veces.
- No permite ajustar toma cancelada.
- No permite contar toma ajustada.
- Producto con lote conserva `lote_id` en detalle.
- Producto sin lote no debe usar `lote_id`.
- Multiempresa en listado y detalle.
- Auditor puede consultar.
- Auditor no puede crear, contar, cerrar, ajustar ni cancelar.
- 401 sin token.
- Reservas activas no alteran `stock_sistema`.
- Stock consolidado sigue igual a suma de stock por lotes.

Comandos recomendados:

```bash
php artisan test --filter=InventarioTomaFisicaApiTest
php artisan test --filter=Inventario
```

---

## 18. Validaciones técnicas realizadas

Se validaron rutas con:

```bash
php artisan route:list --path=api/inventario/tomas-fisicas -v
```

Resultado esperado:

- Todas las rutas aparecen bajo `auth:sanctum`.
- Los endpoints quedan disponibles dentro de `/api/inventario`.

También se validó sintaxis PHP de los archivos clave:

```bash
php -l app/Domains/Inventario/Models/TomaFisicaInventario.php
php -l app/Domains/Inventario/Models/TomaFisicaDetalleInventario.php
php -l app/Domains/Inventario/Services/InventarioTomaFisicaService.php
php -l app/Domains/Inventario/Controllers/InventarioController.php
php -l database/migrations/2026_05_06_120000_create_inventario_tomas_fisicas_tables.php
php -l tests/Feature/Inventario/InventarioTomaFisicaApiTest.php
```

Resultado:

```txt
No syntax errors detected
```

---

## 19. Checklist final Fase 7

- [x] Migración de tomas físicas creada.
- [x] Tabla de cabecera creada.
- [x] Tabla de detalle creada.
- [x] Índices multiempresa agregados.
- [x] Estados definidos.
- [x] Tipos definidos.
- [x] Models Eloquent creados.
- [x] Relaciones agregadas.
- [x] Service de dominio creado.
- [x] Snapshot de stock físico implementado.
- [x] Conteos implementados.
- [x] Cálculo de diferencias implementado.
- [x] Cierre implementado.
- [x] Ajuste real delegado a movimientos.
- [x] Cancelación implementada.
- [x] RBAC granular agregado.
- [x] Auditor limitado a consulta.
- [x] Multiempresa respetado.
- [x] Reservas no alteran snapshot.
- [x] Lotes respetados.
- [x] Kardex conservado.
- [x] PMP/valorización conservados.
- [x] Stock consolidado y stock por lote conservados.
- [x] Endpoints agregados.
- [x] Tests Feature/API agregados.
- [x] Postman/cURL validado.
- [x] Sin DTE/SII.

---

## 20. Observación de higiene del repositorio

En el ZIP revisado aparecen archivos/carpetas que normalmente no deberían ir en commit:

```txt
Backend-laravel/.env
Backend-laravel/.phpunit.result.cache
Backend-laravel/bootstrap/cache/packages.php
Backend-laravel/bootstrap/cache/services.php
Backend-laravel/node_modules/
Frontend/dist/
```

Recomendación:

- Mantener `.env` fuera del repositorio.
- Mantener `node_modules/` fuera del repositorio.
- Mantener `dist/` fuera si el despliegue no exige versionarlo.
- Mantener cachés fuera del commit.
- Confirmar `.gitignore` antes de subir cambios.

Esto no afecta la lógica de la Fase 7, pero sí ayuda a mantener el repositorio liviano, limpio y profesional.

---

## 21. Decisiones técnicas y valor profesional para el ERP

### 21.1 Se separó cabecera y detalle de toma física

**Qué se decidió:**  
Crear `inventario_tomas_fisicas` como cabecera y `inventario_toma_fisica_detalles` como detalle.

**Por qué:**  
Una toma física puede contener muchos productos, bodegas y lotes. Separar cabecera/detalle permite registrar el ciclo de vida general y el conteo granular por línea.

**Valor profesional:**  
Mejora trazabilidad, auditoría, escalabilidad y reportabilidad. Es un patrón común en ERP reales.

---

### 21.2 `stock_sistema` se guarda como snapshot

**Qué se decidió:**  
Guardar el stock físico del sistema al crear la toma.

**Por qué:**  
Durante una toma física pueden ocurrir movimientos posteriores. Si el stock teórico se recalculara dinámicamente, se perdería la fotografía real contra la cual se hizo el conteo.

**Valor profesional:**  
Permite auditoría histórica, comparación confiable y revisión posterior sin inconsistencias.

---

### 21.3 Crear, iniciar, contar y cerrar no modifican stock

**Qué se decidió:**  
Solo el ajuste final modifica stock real.

**Por qué:**  
El conteo físico es una etapa de levantamiento y revisión; no debe impactar inventario hasta que un responsable lo confirme.

**Valor profesional:**  
Reduce errores operativos y permite aprobación controlada antes de afectar Kardex.

---

### 21.4 El ajuste real se delega a `InventarioMovimientoService`

**Qué se decidió:**  
No modificar stock directamente desde `InventarioTomaFisicaService`.

**Por qué:**  
Ya existe una capa profesional para movimientos, Kardex, PMP, stock por lote y valorización.

**Valor profesional:**  
Evita duplicar lógica crítica y mantiene una única fuente de verdad para movimientos de inventario.

---

### 21.5 La toma física compara contra stock físico, no disponible

**Qué se decidió:**  
`stock_sistema` se toma desde stock físico, no desde disponibilidad comprometida.

**Por qué:**  
La toma física mide unidades reales en bodega. Las reservas son compromisos comerciales/operativos, no salidas físicas.

**Valor profesional:**  
Evita diferencias falsas y mantiene separación correcta entre stock físico y stock disponible.

---

### 21.6 Las reservas no se tocan automáticamente

**Qué se decidió:**  
La toma física no cancela, libera ni consume reservas.

**Por qué:**  
Reservas y toma física son procesos distintos. Alterar reservas automáticamente podría romper pedidos, compromisos o flujos comerciales futuros.

**Valor profesional:**  
Mantiene independencia de procesos y evita efectos colaterales peligrosos.

---

### 21.7 RBAC granular

**Qué se decidió:**  
Separar permisos para ver, crear, contar, cerrar, ajustar y cancelar.

**Por qué:**  
No todos los usuarios que cuentan inventario deberían poder aplicar ajustes reales.

**Valor profesional:**  
Permite control interno, segregación de funciones y auditoría por responsabilidad.

---

### 21.8 Auditor solo consulta

**Qué se decidió:**  
El auditor puede ver tomas físicas, pero no operarlas.

**Por qué:**  
Un perfil auditor debe revisar información sin modificar procesos operativos.

**Valor profesional:**  
Mejora cumplimiento, control interno y confianza para una demo empresarial.

---

### 21.9 Datos operacionales se crean por endpoints reales

**Qué se decidió:**  
No crear seeder operacional de tomas físicas.

**Por qué:**  
La toma física tiene reglas de negocio importantes que deben pasar por Service y Controller.

**Valor profesional:**  
Evita estados artificiales y valida el flujo como lo usará el frontend o Postman.

---

### 21.10 Se documentó Postman para preparar Fase 7.5

**Qué se decidió:**  
Dejar un flujo operativo completo con login, producto, bodega, entrada, toma, conteo, cierre, ajuste y Kardex.

**Por qué:**  
La siguiente fase frontend necesita consumir endpoints ya validados.

**Valor profesional:**  
Acelera la construcción de una demo visual y reduce incertidumbre técnica.

---

## 22. Estado final

La Fase 7 queda cerrada como backend 7.0:

```txt
Estado: completada backend
Tests: implementados y validados en flujo de Inventario
Postman/cURL: validado
Frontend: pendiente para Fase 7.5 demo-operativa
```

El módulo queda listo para iniciar:

```txt
Fase 7.5 — Frontend demo-operativo de Inventario
```

O continuar luego con:

```txt
Fase 8 — Reglas de reposición y alertas
```

---

## 23. Commit sugerido

Commit corto:

```bash
feat(inventario): agregar toma fisica e inventario ciclico
```

Commit extendido opcional:

```bash
feat(inventario): implementar toma fisica con conteos, diferencias y ajustes auditables
```
