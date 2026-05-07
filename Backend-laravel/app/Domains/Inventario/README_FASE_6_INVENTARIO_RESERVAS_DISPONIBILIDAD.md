# ERP Contable — Inventario Fase 6
## Reservas y disponibilidad comprometida

**Rama de trabajo:** `SLagos-dev`  
**Backend:** Laravel 12 / PHP 8.2+ / MySQL-MariaDB / Sanctum  
**Arquitectura:** dominios en `Backend-laravel/app/Domains`  
**Dominio:** `Backend-laravel/app/Domains/Inventario`

---

## 1. Contexto general del módulo Inventario

El módulo de Inventario del ERP Contable se está desarrollando por fases, manteniendo una arquitectura profesional, incremental y validada con pruebas automáticas y pruebas manuales en Postman.

Roadmap oficial actual:

1. Catálogos, productos, bodegas y stock base.
2. Movimientos de inventario y Kardex.
3. PMP y valorización.
4. Mermas y ajustes críticos.
5. Lotes, vencimientos y trazabilidad avanzada.
6. Reservas y disponibilidad comprometida.
7. Toma física e inventario cíclico.
8. Reglas de reposición y alertas.
9. Reportes avanzados y dashboard.
10. Frontend de Inventario.
11. Hardening, auditoría final y producto vendible.

### Nota sobre Fase 7 y presentación demo

No se debe establecer formalmente que la Fase 7 sea un “MVP final” o una etiqueta cerrada de producto vendible.  
A partir de Fase 6/Fase 7, el módulo puede quedar **demo-presentable o enseñable**, porque ya tiene flujos reales de inventario, lotes, valorización, ajustes críticos, reservas y disponibilidad comprometida.

Sin embargo, el producto vendible y profesional se considera más cercano al cierre completo del roadmap, especialmente después de:

- reportes avanzados;
- dashboard;
- frontend completo;
- hardening;
- auditoría final;
- revisión integral de seguridad y consistencia.

---

## 2. Fase 7.5 opcional — Frontend parcial demo-operativo

Se propone agregar una subfase opcional:

### 7.5. Frontend parcial demo-operativo de Inventario

Esta fase no reemplaza la Fase 10.  
Su objetivo sería construir una interfaz parcial para operar y presentar los flujos principales antes del frontend completo.

### Objetivo

Permitir demostrar el módulo Inventario con pantallas funcionales básicas para:

- productos;
- bodegas;
- movimientos;
- Kardex;
- lotes;
- reservas;
- disponibilidad.

### Alcance recomendado

La Fase 7.5 podría incluir:

- listado de productos;
- creación/edición básica de productos;
- listado de bodegas;
- registro de entrada/salida/traspaso;
- consulta Kardex;
- listado y creación de lotes;
- creación de reservas;
- cancelación/liberación/consumo de reservas;
- consulta de disponibilidad física, reservada y disponible;
- manejo básico de errores visuales;
- uso de permisos ya expuestos en `GestionRoles.jsx`.

### Fuera de alcance de Fase 7.5

- dashboard avanzado;
- reportes ejecutivos;
- optimización visual final;
- experiencia completa mobile;
- hardening frontend;
- producto vendible final.

### Valor

Esta subfase permitiría presentar el ERP de forma más clara sin esperar a la Fase 10 completa.  
Sería una interfaz operativa parcial para demo, validación con usuarios y exposición académica/profesional.

---

## 3. Regla crítica permanente: Inventario no maneja DTE

El módulo Inventario **no emite, gestiona ni prepara DTE**.

No se deben usar dentro de Inventario:

- `codigo_dte`
- `codigo_sii`
- `folio_dte`
- `xml_dte`
- `emitir_dte`
- lógica tributaria/SII

Si se requiere trazabilidad externa, se usan campos genéricos:

- `referencia`
- `motivo`
- `observacion`
- `origen_modulo`
- `origen_id`

Esto permite que Inventario se relacione con módulos futuros, como ventas, compras o despacho, sin contaminar el dominio con lógica tributaria.

---

## 4. Objetivo de la Fase 6

La Fase 6 agrega una capa profesional de reservas de stock para diferenciar entre:

- **stock físico**;
- **stock reservado o comprometido**;
- **stock disponible real**.

La reserva **no descuenta inventario físico inmediatamente**.

El stock físico solo se descuenta cuando la reserva se consume mediante una salida real delegada a:

```txt
InventarioMovimientoService
```

Esto permite controlar stock comprometido antes de despachar, consumir o mover inventario real.

---

## 5. Conceptos principales

### 5.1. Stock físico

Es el stock real guardado en:

```txt
inventario_stock
inventario_stock_lotes
```

Para productos sin lote se consulta principalmente `inventario_stock`.

Para productos con lote se mantiene:

- stock consolidado en `inventario_stock`;
- stock granular por lote en `inventario_stock_lotes`.

---

### 5.2. Stock reservado

Es el stock comprometido por reservas activas o parciales.

Se calcula desde:

```txt
inventario_reserva_detalles
```

Considerando solo reservas en estados que comprometen disponibilidad:

```txt
ACTIVA
PARCIALMENTE_LIBERADA
PARCIALMENTE_CONSUMIDA
```

---

### 5.3. Stock disponible

Fórmula general:

```txt
stock_disponible = stock_fisico - stock_reservado_activo
```

Para lotes:

```txt
stock_disponible_lote = stock_fisico_lote - stock_reservado_activo_lote
```

---

## 6. Multiempresa

La lógica actual de multiempresa es correcta para el ERP.

El flujo esperado es:

```txt
Usuario autenticado
↓
Token Sanctum
↓
usuario->empresa_id
↓
Todas las consultas y operaciones filtran por empresa_id
↓
El frontend NO manda empresa_id
```

### Regla importante

El frontend y Postman **no deben mandar `empresa_id` en el body**.

El backend toma la empresa desde el usuario autenticado:

```php
$request->user()->empresa_id
```

### Por qué esto es correcto

Evita que un usuario manipule el payload e intente operar sobre datos de otra empresa.

Incorrecto:

```json
{
  "empresa_id": 5,
  "producto_id": 2
}
```

Correcto:

```json
{
  "producto_id": 2,
  "bodega_id": 2,
  "lote_id": 1,
  "cantidad": 3
}
```

El backend valida internamente que:

- el producto pertenece a la empresa del usuario;
- la bodega pertenece a la empresa del usuario;
- el lote pertenece a la empresa del usuario;
- la reserva pertenece a la empresa del usuario;
- el movimiento pertenece a la empresa del usuario.

### Error esperado si se cruza empresa

```json
{
  "success": false,
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "producto_id": [
      "El producto no existe o no pertenece a la empresa."
    ]
  }
}
```

Este error es correcto y demuestra que la protección multiempresa funciona.

---

## 7. Tablas agregadas en Fase 6

### 7.1. `inventario_reservas`

Tabla cabecera de reservas.

Campos principales:

| Campo | Descripción |
|---|---|
| `id` | Identificador interno. |
| `empresa_id` | Empresa dueña de la reserva. |
| `codigo_reserva` | Código único por empresa. |
| `estado` | Estado operativo. |
| `referencia` | Referencia externa genérica. |
| `motivo` | Motivo de la reserva. |
| `observacion` | Observación auditable. |
| `origen_modulo` | Módulo externo opcional. |
| `origen_id` | ID externo opcional. |
| `reservado_por` | Usuario que creó la reserva. |
| `fecha_reserva` | Fecha lógica de reserva. |
| `fecha_expiracion` | Fecha opcional de expiración. |
| `created_at` / `updated_at` | Auditoría técnica Laravel. |

Estados:

```txt
ACTIVA
PARCIALMENTE_LIBERADA
PARCIALMENTE_CONSUMIDA
CONSUMIDA
CANCELADA
EXPIRADA
```

Estados que comprometen disponibilidad:

```txt
ACTIVA
PARCIALMENTE_LIBERADA
PARCIALMENTE_CONSUMIDA
```

Estados que no comprometen disponibilidad:

```txt
CONSUMIDA
CANCELADA
EXPIRADA
```

---

### 7.2. `inventario_reserva_detalles`

Cada detalle compromete stock para:

```txt
empresa + producto + bodega + lote opcional
```

Campos principales:

| Campo | Descripción |
|---|---|
| `id` | Identificador interno. |
| `empresa_id` | Empresa dueña del detalle. |
| `reserva_id` | Reserva cabecera. |
| `producto_id` | Producto reservado. |
| `bodega_id` | Bodega desde donde se reserva. |
| `lote_id` | Lote reservado, obligatorio si el producto maneja lotes. |
| `cantidad_reservada` | Cantidad originalmente reservada. |
| `cantidad_consumida` | Cantidad consumida mediante salida real. |
| `cantidad_liberada` | Cantidad liberada sin consumir. |

Cantidad pendiente:

```txt
cantidad_pendiente = cantidad_reservada - cantidad_consumida - cantidad_liberada
```

---

### 7.3. `inventario_reserva_consumos`

Tabla de trazabilidad entre reserva y movimiento real.

Permite relacionar:

```txt
Reserva -> Detalle de reserva -> Movimiento real de inventario
```

Campos principales:

| Campo | Descripción |
|---|---|
| `id` | Identificador interno. |
| `empresa_id` | Empresa dueña del consumo. |
| `reserva_id` | Reserva cabecera. |
| `reserva_detalle_id` | Detalle consumido. |
| `movimiento_inventario_id` | Movimiento real generado. |
| `producto_id` | Producto consumido. |
| `bodega_id` | Bodega origen. |
| `lote_id` | Lote consumido si aplica. |
| `cantidad_consumida` | Cantidad consumida. |
| `consumido_por` | Usuario que ejecutó el consumo. |
| `fecha_consumo` | Fecha del consumo. |

---

## 8. Models agregados

```txt
app/Domains/Inventario/Models/ReservaInventario.php
app/Domains/Inventario/Models/ReservaDetalleInventario.php
app/Domains/Inventario/Models/ReservaConsumoInventario.php
```

### 8.1. `ReservaInventario`

Responsabilidades:

- representar cabecera de reserva;
- definir estados;
- exponer helpers de estado;
- definir estados que comprometen disponibilidad;
- relacionar detalles y consumos;
- validar pertenencia a empresa.

Relaciones:

- `empresa`
- `reservadoPor`
- `detalles`
- `consumos`

---

### 8.2. `ReservaDetalleInventario`

Responsabilidades:

- representar cada línea de reserva;
- calcular cantidad pendiente;
- determinar si tiene lote;
- validar si puede liberar o consumir;
- relacionar producto, bodega, lote y consumos.

Relaciones:

- `empresa`
- `reserva`
- `producto`
- `bodega`
- `lote`
- `consumos`

---

### 8.3. `ReservaConsumoInventario`

Responsabilidades:

- registrar cada consumo generado desde una reserva;
- relacionar reserva, detalle y movimiento;
- mantener snapshot operativo para auditoría.

Relaciones:

- `empresa`
- `reserva`
- `detalle`
- `movimiento`
- `producto`
- `bodega`
- `lote`
- `consumidoPor`

---

## 9. Services agregados

```txt
app/Domains/Inventario/Services/InventarioReservaService.php
app/Domains/Inventario/Services/InventarioDisponibilidadService.php
```

### 9.1. `InventarioReservaService`

Responsabilidades:

- listar reservas;
- obtener detalle de reserva;
- crear reservas;
- cancelar reservas;
- liberar reservas;
- consumir reservas;
- marcar reservas expiradas;
- actualizar estados;
- validar producto, bodega, lote, stock disponible y multiempresa;
- delegar salidas reales a `InventarioMovimientoService`.

Regla clave:

```txt
Crear, cancelar o liberar reservas NO modifica stock físico.
Solo consumir reserva genera salida real.
```

---

### 9.2. `InventarioDisponibilidadService`

Responsabilidades:

- consultar disponibilidad general;
- consultar disponibilidad por producto;
- calcular stock físico;
- calcular stock reservado activo;
- calcular stock disponible;
- calcular disponibilidad por lote;
- validar disponibilidad antes de crear reservas.

---

## 10. Endpoints Fase 6

Todos protegidos por `auth:sanctum`.

### Reservas

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/api/inventario/reservas` | Lista reservas. |
| POST | `/api/inventario/reservas` | Crea reserva. |
| GET | `/api/inventario/reservas/{id}` | Ver detalle. |
| POST | `/api/inventario/reservas/{id}/cancelar` | Cancela reserva. |
| POST | `/api/inventario/reservas/{id}/liberar` | Libera parcial o totalmente. |
| POST | `/api/inventario/reservas/{id}/consumir` | Consume reserva y genera salida real. |

### Disponibilidad

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/api/inventario/disponibilidad` | Consulta disponibilidad general. |
| GET | `/api/inventario/productos/{id}/disponibilidad` | Consulta disponibilidad por producto. |

### Importante

Disponibilidad **solo se consulta**.

No existe:

```txt
POST /api/inventario/disponibilidad
```

---

## 11. Permisos RBAC

Permisos agregados:

```txt
inventario.reservas.ver
inventario.reservas.crear
inventario.reservas.cancelar
inventario.reservas.liberar
inventario.reservas.consumir
inventario.disponibilidad.ver
```

Aplicación:

| Acción | Permiso |
|---|---|
| Listar reservas | `inventario.reservas.ver` |
| Ver reserva | `inventario.reservas.ver` |
| Crear reserva | `inventario.reservas.crear` |
| Cancelar reserva | `inventario.reservas.cancelar` |
| Liberar reserva | `inventario.reservas.liberar` |
| Consumir reserva | `inventario.reservas.consumir` |
| Consultar disponibilidad | `inventario.disponibilidad.ver` |

### Auditor

El auditor puede:

- consultar productos;
- consultar bodegas;
- consultar movimientos;
- consultar Kardex;
- consultar valorización;
- consultar ajustes críticos;
- consultar lotes;
- consultar reservas;
- consultar disponibilidad.

El auditor no puede:

- crear reservas;
- cancelar reservas;
- liberar reservas;
- consumir reservas;
- registrar movimientos;
- crear lotes;
- editar lotes;
- registrar ajustes críticos.

---

## 12. Reglas de negocio Fase 6

### Crear reserva

- La reserva pertenece a una empresa.
- Una reserva puede tener uno o más detalles.
- Cada detalle reserva producto + bodega + lote opcional.
- Si el producto maneja lotes, `lote_id` es obligatorio.
- Si el producto no maneja lotes, no debe enviarse `lote_id`.
- No se permite reservar producto inactivo.
- No se permite reservar en bodega inactiva.
- No se permite reservar lote inactivo.
- No se permite lote de otra empresa.
- No se permite lote de otro producto.
- No se permite reservar más que stock disponible.
- Crear reserva no descuenta stock físico.
- Crear reserva no genera Kardex.
- Crear reserva no afecta PMP.

### Cancelar reserva

- Solo se puede cancelar una reserva activa o parcial.
- No se puede cancelar una reserva consumida, cancelada o expirada.
- Cancelar no modifica stock físico.
- Cancelar libera disponibilidad comprometida.

### Liberar reserva

- Solo se puede liberar una reserva activa o parcial.
- Se debe informar `detalle_id`.
- La cantidad debe ser mayor a cero.
- No se puede liberar más que la cantidad pendiente.
- Liberar no modifica stock físico.
- Liberar reduce stock reservado activo.

### Consumir reserva

- Solo se puede consumir una reserva activa o parcial.
- No se puede consumir una reserva cancelada, consumida o expirada.
- No se puede consumir más que la cantidad pendiente.
- Consumir genera salida real mediante `InventarioMovimientoService`.
- Si el detalle tiene lote, el consumo respeta `lote_id`.
- El consumo descuenta `inventario_stock`.
- El consumo descuenta `inventario_stock_lotes` si aplica.
- El consumo registra Kardex.
- El consumo mantiene trazabilidad en `inventario_reserva_consumos`.

---

## 13. Contrato de API y nomenclatura

### Backend/API/DB

Se mantiene `snake_case` en minúscula:

```txt
entrada
salida
traspaso
ajuste_positivo
ajuste_negativo
```

Esto es correcto para Laravel, MySQL y contratos API.

### Frontend

En frontend se puede usar `camelCase` para variables JS:

```js
const tipoMovimientoSeleccionado = 'entrada';
const bodegaDestinoId = 2;
const costoUnitario = 100;
```

Pero el payload enviado al backend debe respetar el contrato API:

```js
const payload = {
  tipo: tipoMovimientoSeleccionado,
  producto_id: productoId,
  bodega_destino_id: bodegaDestinoId,
  costo_unitario: costoUnitario,
};
```

En Fase 10 o Fase 7.5 se recomienda crear constantes frontend:

```js
export const TIPOS_MOVIMIENTO = {
  ENTRADA: 'entrada',
  SALIDA: 'salida',
  TRASPASO: 'traspaso',
  AJUSTE_POSITIVO: 'ajuste_positivo',
  AJUSTE_NEGATIVO: 'ajuste_negativo',
};
```

---

## 14. Tests automáticos

Tests agregados:

```txt
tests/Feature/Inventario/InventarioReservaApiTest.php
tests/Feature/Inventario/InventarioDisponibilidadApiTest.php
```

Casos cubiertos:

- crear reserva válida sin lote;
- crear reserva válida con lote;
- rechazar reserva sin lote cuando producto maneja lotes;
- rechazar lote de otro producto;
- rechazar lote de otra empresa;
- rechazar stock disponible insuficiente;
- confirmar que crear reserva no descuenta stock físico;
- disponibilidad considera reservas activas;
- disponibilidad por lote considera reservas activas por lote;
- cancelar reserva libera disponibilidad;
- liberar parcialmente reduce stock reservado;
- consumir reserva genera movimiento de salida;
- consumir reserva con lote descuenta stock_lote;
- consumo parcial deja estado `PARCIALMENTE_CONSUMIDA`;
- consumo total deja estado `CONSUMIDA`;
- no permite consumir reserva cancelada;
- no permite liberar más de lo pendiente;
- auditor puede consultar reservas/disponibilidad;
- auditor no puede operar reservas;
- multiempresa en listado y detalle;
- 401 sin token;
- no rompe invariante stock consolidado vs stock_lotes.

Comando:

```bash
php artisan test --filter=Inventario
```

Resultado validado:

```txt
108 tests passed
630 assertions
```

---

## 15. Postman — Preparación correcta

### 15.1. Seeders requeridos

Ejecutar:

```bash
php artisan db:seed --class=InventarioPostmanSeeder
php artisan db:seed --class=InventarioDemoPermisosSeeder
```

Usuarios demo:

```txt
contador@example.com / password
auditor@example.com / password
```

### 15.2. Environment de Postman

Crear environment:

```txt
ERP Contable Local
```

Variables recomendadas:

```txt
base_url = http://127.0.0.1:8000/api
token_contador =
token_auditor =
producto_id =
bodega_id =
lote_id =
reserva_id =
detalle_reserva_id =
reserva_liberar_id =
detalle_liberar_id =
reserva_consumir_id =
detalle_consumir_id =
```

### 15.3. Error común: No environment

Si Postman muestra `No environment`, variables como:

```txt
{{producto_id}}
{{bodega_id}}
{{lote_id}}
```

no se reemplazarán correctamente.

Solución:

- crear environment;
- seleccionarlo arriba a la derecha;
- verificar `Current value` de cada variable.

---

## 16. Postman — Flujo contador con lote

### 16.1. Login contador

```txt
POST {{base_url}}/auth/login
```

Body:

```json
{
  "email": "contador@example.com",
  "password": "password"
}
```

Guardar token:

```js
const json = pm.response.json();
pm.environment.set('token_contador', json.token);
```

---

### 16.2. Consultar productos del contador

```txt
GET {{base_url}}/inventario/productos
```

Usar:

```txt
Authorization: Bearer {{token_contador}}
```

Ejemplo real validado:

```txt
producto_id = 2
empresa_id = 2
maneja_lotes = true
requiere_fecha_vencimiento = true
```

---

### 16.3. Consultar lotes del producto

```txt
GET {{base_url}}/inventario/productos/2/lotes
```

Ejemplo real validado:

```txt
lote_id = 1
bodega_id = 2
stock_actual = 5
```

---

### 16.4. Registrar entrada con lote

Si el producto maneja lotes, el body debe incluir `lote_id`.

```txt
POST {{base_url}}/inventario/movimientos
```

Body:

```json
{
  "tipo": "entrada",
  "producto_id": 2,
  "bodega_destino_id": 2,
  "lote_id": 1,
  "cantidad": 10,
  "costo_unitario": 100,
  "referencia": "ENT-POSTMAN-RES-LOTE-001",
  "motivo": "compra",
  "observacion": "Entrada inicial para probar reservas con lote"
}
```

Tipos válidos:

```txt
entrada
salida
traspaso
ajuste_positivo
ajuste_negativo
```

No usar:

```txt
ENTRADA
SALIDA
TRASPASO
```

---

### 16.5. Crear reserva con lote

```txt
POST {{base_url}}/inventario/reservas
```

Body:

```json
{
  "referencia": "PED-POSTMAN-RES-001",
  "motivo": "reserva_comercial",
  "observacion": "Reserva demo Postman con lote",
  "detalles": [
    {
      "producto_id": 2,
      "bodega_id": 2,
      "lote_id": 1,
      "cantidad": 3
    }
  ]
}
```

Guardar variables:

```js
const json = pm.response.json();
pm.environment.set('reserva_id', json.data.id);
pm.environment.set('detalle_reserva_id', json.data.detalles[0].id);
```

Resultado esperado:

```txt
201 Created
estado: ACTIVA
```

---

### 16.6. Consultar disponibilidad con lote

```txt
GET {{base_url}}/inventario/productos/2/disponibilidad?bodega_id=2&incluir_lotes=1
```

Resultado esperado si había stock 5 y reserva 3:

```txt
stock_fisico: 5
stock_reservado: 3
stock_disponible: 2
```

---

### 16.7. Cancelar reserva

Usar ID real o variable Postman con doble llave:

Correcto:

```txt
POST {{base_url}}/inventario/reservas/{{reserva_id}}/cancelar
```

Incorrecto:

```txt
POST /api/inventario/reservas/{reserva_id}/cancelar
```

Body:

```json
{
  "observacion": "Cancelación demo desde Postman"
}
```

Resultado esperado:

```txt
200 OK
estado: CANCELADA
```

---

### 16.8. Crear reserva para liberar parcialmente

```txt
POST {{base_url}}/inventario/reservas
```

Body:

```json
{
  "referencia": "PED-POSTMAN-LIBERAR-001",
  "motivo": "reserva_comercial",
  "observacion": "Reserva para liberar parcialmente",
  "detalles": [
    {
      "producto_id": 2,
      "bodega_id": 2,
      "lote_id": 1,
      "cantidad": 4
    }
  ]
}
```

Guardar:

```js
const json = pm.response.json();
pm.environment.set('reserva_liberar_id', json.data.id);
pm.environment.set('detalle_liberar_id', json.data.detalles[0].id);
```

---

### 16.9. Liberar parcialmente

Endpoint correcto:

```txt
POST {{base_url}}/inventario/reservas/{{reserva_liberar_id}}/liberar
```

Body:

```json
{
  "observacion": "Liberación parcial por cambio de pedido",
  "detalles": [
    {
      "detalle_id": 2,
      "cantidad": 1
    }
  ]
}
```

Si se usan variables:

```json
{
  "observacion": "Liberación parcial por cambio de pedido",
  "detalles": [
    {
      "detalle_id": "{{detalle_liberar_id}}",
      "cantidad": 1
    }
  ]
}
```

Resultado esperado:

```txt
estado: PARCIALMENTE_LIBERADA
```

---

### 16.10. Consumir reserva

```txt
POST {{base_url}}/inventario/reservas/{{reserva_liberar_id}}/consumir
```

Body:

```json
{
  "referencia": "SAL-RES-POSTMAN-001",
  "motivo": "consumo_reserva",
  "observacion": "Salida generada desde reserva Postman"
}
```

Si la reserva era de 4 y se liberó 1, queda pendiente 3.  
Al consumir, baja stock físico en 3.

Resultado esperado:

```txt
estado: CONSUMIDA
```

Efecto:

```txt
Baja stock físico.
Genera movimiento de salida.
Registra Kardex.
Registra trazabilidad en inventario_reserva_consumos.
```

---

### 16.11. Consultar Kardex

```txt
GET {{base_url}}/inventario/productos/2/kardex
```

Debe aparecer:

```txt
SAL-RES-POSTMAN-001
```

---

## 17. Postman — Flujo auditor

### 17.1. Login auditor

```txt
POST {{base_url}}/auth/login
```

Body:

```json
{
  "email": "auditor@example.com",
  "password": "password"
}
```

Guardar token:

```js
const json = pm.response.json();
pm.environment.set('token_auditor', json.token);
```

---

### 17.2. Auditor puede consultar reservas

```txt
GET {{base_url}}/inventario/reservas
```

Esperado:

```txt
200 OK
success: true
```

---

### 17.3. Auditor puede consultar disponibilidad

```txt
GET {{base_url}}/inventario/disponibilidad
```

Esperado:

```txt
200 OK
success: true
```

---

### 17.4. Auditor no puede crear reserva

```txt
POST {{base_url}}/inventario/reservas
```

Body:

```json
{
  "referencia": "PED-AUDITOR-BLOQUEADO",
  "motivo": "reserva_comercial",
  "observacion": "El auditor no debe crear reservas",
  "detalles": [
    {
      "producto_id": 2,
      "bodega_id": 2,
      "lote_id": 1,
      "cantidad": 1
    }
  ]
}
```

Esperado:

```txt
422
success: false
```

---

### 17.5. Auditor no puede cancelar/liberar/consumir

Cancelar:

```txt
POST {{base_url}}/inventario/reservas/{{reserva_id}}/cancelar
```

Liberar:

```txt
POST {{base_url}}/inventario/reservas/{{reserva_id}}/liberar
```

Consumir:

```txt
POST {{base_url}}/inventario/reservas/{{reserva_id}}/consumir
```

Esperado:

```txt
422
success: false
```

---

## 18. Errores comunes en Postman

### 18.1. Usar `{reserva_id}` en vez de `{{reserva_id}}`

Incorrecto:

```txt
/api/inventario/reservas/{reserva_id}/cancelar
```

Correcto:

```txt
/api/inventario/reservas/{{reserva_id}}/cancelar
```

O usar el ID real:

```txt
/api/inventario/reservas/2/cancelar
```

---

### 18.2. Enviar a endpoint equivocado

Para liberar no se usa:

```txt
POST /api/inventario/reservas
```

Ese endpoint crea reservas y exigirá `producto_id`, `bodega_id` y cantidad.

Correcto:

```txt
POST /api/inventario/reservas/{id}/liberar
```

---

### 18.3. Producto con lotes sin `lote_id`

Si el producto tiene:

```txt
maneja_lotes = true
```

entonces movimientos y reservas deben incluir:

```txt
lote_id
```

---

### 18.4. Tipo de movimiento en mayúscula

Incorrecto:

```json
{
  "tipo": "ENTRADA"
}
```

Correcto:

```json
{
  "tipo": "entrada"
}
```

---

### 18.5. Producto de otra empresa

Error:

```txt
El producto no existe o no pertenece a la empresa.
```

Solución:

Consultar productos con el mismo token:

```txt
GET /api/inventario/productos
```

Y usar un ID perteneciente a la empresa del usuario autenticado.

---

### 18.6. Variables sin Current Value

Si `{{producto_id}}` o `{{bodega_id}}` no tienen `Current value`, Laravel recibirá datos inválidos.

Solución:

- seleccionar environment;
- revisar variables;
- guardar IDs desde Scripts/Post-response;
- o usar IDs reales temporalmente.

---

## 19. Checklist de validación Fase 6

- [x] Migraciones creadas.
- [x] Models creados.
- [x] Services creados.
- [x] Controller conectado.
- [x] Rutas agregadas.
- [x] Permisos RBAC agregados.
- [x] Seeders demo actualizados.
- [x] Tests Feature/API agregados.
- [x] Tests automáticos ejecutados.
- [x] Postman validado.
- [x] Auditor puede consultar.
- [x] Auditor no puede operar.
- [x] Multiempresa validada.
- [x] Producto con lote exige `lote_id`.
- [x] Reserva no descuenta stock físico.
- [x] Cancelar libera disponibilidad.
- [x] Liberar parcialmente reduce compromiso.
- [x] Consumir genera salida real.
- [x] Consumo aparece en Kardex.
- [x] Inventario sigue sin DTE/SII.

---

## 20. Comandos útiles

Migraciones:

```bash
php artisan migrate
```

Seeders demo/Postman:

```bash
php artisan db:seed --class=InventarioPostmanSeeder
php artisan db:seed --class=InventarioDemoPermisosSeeder
```

Rutas:

```bash
php artisan route:list --path=inventario
```

Tests:

```bash
php artisan test --filter=Inventario
```

Build frontend:

```bash
npm run build
```

---

## 21. Commit sugerido

```txt
feat(inventario): agregar reservas y disponibilidad comprometida fase 6
```

Descripción sugerida:

```txt
- agrega migraciones para reservas, detalles y consumos de reserva
- agrega models de reservas y trazabilidad de consumos
- agrega services de reservas y disponibilidad
- agrega endpoints de reservas y disponibilidad
- integra consumo de reservas con InventarioMovimientoService
- agrega permisos RBAC de Fase 6
- actualiza seeders demo/Postman de inventario
- agrega tests Feature/API de reservas y disponibilidad
- documenta flujo Postman y reglas de negocio de Fase 6
```

---

## 22. Decisiones técnicas y valor profesional para el ERP

### 1. Separar stock físico, reservado y disponible

**Qué se decidió:**  
La reserva no modifica `inventario_stock` ni `inventario_stock_lotes`.

**Por qué se decidió:**  
Reservar stock no representa movimiento físico, sino compromiso operativo.

**Cómo aporta valor profesional:**  
Permite diferenciar inventario real de stock comprometido, evitando sobreventa, errores de despacho y falsa reducción de inventario.

---

### 2. Calcular disponibilidad dinámicamente

**Qué se decidió:**  
No se agregó columna `stock_reservado` en `inventario_stock`.

**Por qué se decidió:**  
Guardar stock reservado directamente podría generar inconsistencias si una reserva se cancela, libera o consume parcialmente.

**Cómo aporta valor profesional:**  
La disponibilidad se calcula desde datos fuente auditables, reduciendo riesgo de descuadres.

---

### 3. Delegar consumo a `InventarioMovimientoService`

**Qué se decidió:**  
El consumo de reserva no implementa descuento propio, sino que delega salida real al service existente.

**Por qué se decidió:**  
`InventarioMovimientoService` ya controla stock, Kardex, PMP, lotes, validaciones y transacciones.

**Cómo aporta valor profesional:**  
Evita duplicar lógica crítica y mantiene una sola fuente de verdad para movimientos reales.

---

### 4. Crear tabla `inventario_reserva_consumos`

**Qué se decidió:**  
Registrar cada consumo de reserva en una tabla específica.

**Por qué se decidió:**  
Una reserva puede consumirse parcial o totalmente, y cada consumo debe poder auditarse.

**Cómo aporta valor profesional:**  
Permite seguimiento completo desde compromiso hasta salida real, mejorando auditoría y soporte.

---

### 5. Mantener multiempresa desde token

**Qué se decidió:**  
El frontend/Postman no envía `empresa_id`; el backend usa el usuario autenticado.

**Por qué se decidió:**  
Evita manipulación del payload y acceso cruzado entre empresas.

**Cómo aporta valor profesional:**  
Refuerza seguridad, aislamiento de datos y escalabilidad multiempresa.

---

### 6. Mantener `snake_case` en API/DB

**Qué se decidió:**  
Los valores y campos del backend mantienen `snake_case` en minúscula.

**Por qué se decidió:**  
Es una convención coherente con Laravel, MySQL y el contrato API actual.

**Cómo aporta valor profesional:**  
Reduce ambigüedad, facilita filtros SQL y mantiene consistencia técnica.

---

### 7. Mantener disponibilidad como consulta GET

**Qué se decidió:**  
Disponibilidad no se crea ni modifica por POST.

**Por qué se decidió:**  
La disponibilidad es una consecuencia calculada del stock físico y las reservas activas.

**Cómo aporta valor profesional:**  
Evita estados artificiales y mantiene coherencia del inventario.

---

### 8. Fase 7.5 como puente demo, no reemplazo de Fase 10

**Qué se decidió:**  
Agregar una fase opcional 7.5 para frontend parcial demo-operativo.

**Por qué se decidió:**  
Permite presentar y operar flujos principales antes del frontend completo.

**Cómo aporta valor profesional:**  
Mejora la capacidad de demostración temprana sin adelantar falsamente el producto vendible final.

---

## 23. Estado final de Fase 6

Fase 6 queda validada con:

```txt
108 tests passed
630 assertions
Postman validado manualmente
Seeders demo ejecutados correctamente
Multiempresa validada
RBAC validado
Inventario sigue sin DTE/SII
```

Siguiente fase oficial:

```txt
Fase 7 — Toma física e inventario cíclico
```

Subfase opcional recomendada después de Fase 7:

```txt
Fase 7.5 — Frontend parcial demo-operativo de Inventario
```
