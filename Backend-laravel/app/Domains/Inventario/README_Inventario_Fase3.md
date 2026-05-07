# Fase 3 — Precio Medio Ponderado / Valorización de Stock

## Objetivo

La Fase 3 formaliza el cálculo de **Precio Medio Ponderado (PMP)** y la **valorización del stock** dentro del dominio Inventario del ERP Contable.

Esta fase mantiene la arquitectura existente del proyecto:

- Laravel 12.
- PHP 8.2+.
- MySQL/MariaDB.
- Laravel Sanctum.
- PHPUnit/Laravel.
- Arquitectura por dominios en `app/Domains`.
- Services + Eloquent.
- Sin capa Repository física en Inventario.
- Contrato API actual con `success: true/false`.
- Errores de negocio, validación y permisos con HTTP 422.
- Acceso sin token protegido por `auth:sanctum` con HTTP 401.

> Importante: Inventario **NO emite, gestiona ni prepara DTE**.  
> No se usan campos como `codigo_dte`, `codigo_sii`, `folio_dte`, `xml_dte` ni lógica tributaria/SII dentro del módulo de Inventario.

---

## Archivos agregados en Fase 3

```txt
app/Domains/Inventario/Services/InventarioValorizacionService.php
database/migrations/2026_05_04_120000_add_inventario_fase3_valorizacion_permission_to_roles.php
database/seeders/InventarioPermisosSeeder.php
database/seeders/InventarioPostmanSeeder.php
tests/Unit/InventarioValorizacionServiceTest.php
tests/Feature/InventarioValorizacionApiTest.php
```

---

## Archivos modificados en Fase 3

```txt
app/Domains/Inventario/Services/InventarioMovimientoService.php
app/Domains/Inventario/Controllers/InventarioController.php
routes/api.php
database/seeders/DatabaseSeeder.php
app/Domains/Inventario/README.md
```

---

## Service de valorización

Archivo:

```txt
app/Domains/Inventario/Services/InventarioValorizacionService.php
```

Responsabilidades principales:

- Calcular entrada con PMP.
- Calcular salida valorizada con PMP.
- Calcular traspaso valorizado entre bodegas.
- Actualizar `inventario_stock.stock_actual`.
- Actualizar `inventario_stock.costo_promedio`.
- Actualizar `inventario_stock.valor_total`.
- Actualizar `inventario_productos.costo_promedio` consolidado.
- Listar stock valorizado.
- Generar resumen de valorización.

Métodos principales:

```php
calcularEntradaPmp()
calcularSalidaPmp()
calcularTraspasoPmp()
obtenerCostoUnitarioEntrada()
obtenerCostoUnitarioSalida()
actualizarCostoPromedioProducto()
obtenerOCrearStock()
listarValorizacion()
resumenValorizacion()
```

---

## Reglas PMP

### Entrada

Una entrada aumenta stock y recalcula PMP por bodega.

Fórmula:

```txt
nuevo_stock = stock_actual + cantidad_entrada
nuevo_valor_total = valor_total_actual + (cantidad_entrada * costo_unitario)
nuevo_costo_promedio = nuevo_valor_total / nuevo_stock
```

Ejemplo:

```txt
Stock actual: 10
PMP actual: 1000
Valor actual: 10000

Entrada: 5 unidades a 1200
Valor entrada: 6000

Nuevo stock: 15
Nuevo valor total: 16000
Nuevo PMP: 1066.6667
```

---

### Salida

Una salida descuenta stock usando el PMP actual de la bodega.

```txt
costo_unitario_salida = costo_promedio_actual
costo_total_salida = cantidad_salida * costo_unitario_salida
nuevo_stock = stock_actual - cantidad_salida
nuevo_valor_total = valor_total_actual - costo_total_salida
```

La salida no recalcula PMP hacia arriba o abajo. Mantiene el costo promedio de la bodega mientras exista stock.

Si el stock queda en cero:

```txt
stock_actual = 0
costo_promedio = 0
valor_total = 0
```

---

### Traspaso

Un traspaso se valoriza usando el PMP de la bodega origen.

Reglas:

```txt
- La bodega origen descuenta stock usando su PMP.
- La bodega destino recibe stock usando el costo unitario PMP del origen.
- Si la bodega destino ya tenía stock, recalcula su PMP propio.
```

---

### Ajuste positivo

Funciona como una entrada.

Puede recibir `costo_unitario`. Si no se informa costo unitario, usa fallback:

```txt
1. costo_promedio del stock en la bodega.
2. costo_promedio consolidado del producto.
3. 0.
```

---

### Ajuste negativo / merma

Funciona como una salida.

Reglas:

```txt
- Valida stock suficiente.
- Usa PMP actual de la bodega.
- Descuenta stock.
- Descuenta valor_total.
```

La merma se registra como:

```json
{
  "tipo": "ajuste_negativo",
  "motivo": "merma"
}
```

---

## Costo promedio consolidado del producto

El campo:

```txt
inventario_productos.costo_promedio
```

representa el PMP consolidado del producto en todas sus bodegas de la misma empresa.

Fórmula:

```txt
producto.costo_promedio = SUM(valor_total) / SUM(stock_actual)
```

Si el stock total consolidado es cero:

```txt
producto.costo_promedio = 0
```

---

## Endpoints Fase 3

### Listar valorización general

```http
GET /api/inventario/valorizacion
```

Query params soportados:

```txt
producto_id
bodega_id
search
page
per_page
```

Respuesta esperada:

```json
{
  "success": true,
  "data": [],
  "pagination": {
    "total": 1,
    "totalPages": 1,
    "page": 1
  },
  "resumen": {
    "stock_total": "15.0000",
    "valor_total": "16000.0000",
    "costo_promedio_global": "1066.6667"
  }
}
```

---

### Listar valorización por producto

```http
GET /api/inventario/productos/{id}/valorizacion
```

Respuesta esperada:

```json
{
  "success": true,
  "data": [],
  "pagination": {
    "total": 1,
    "totalPages": 1,
    "page": 1
  },
  "resumen": {
    "stock_total": "15.0000",
    "valor_total": "16000.0000",
    "costo_promedio_global": "1066.6667"
  }
}
```

---

## Permisos Fase 3

Permiso nuevo:

```txt
inventario.valorizacion.ver
```

Roles recomendados:

```txt
Administrador:
- Permisos completos de Inventario.

Contador:
- Puede consultar valorización.
- Puede crear productos.
- Puede crear bodegas.
- Puede registrar movimientos.
- Puede consultar Kardex.

Auditor:
- Puede consultar productos.
- Puede consultar bodegas.
- Puede consultar movimientos.
- Puede consultar Kardex.
- Puede consultar valorización.
- No puede registrar movimientos.
```

---

## Seeders de Inventario

### InventarioPermisosSeeder

Archivo:

```txt
database/seeders/InventarioPermisosSeeder.php
```

Responsabilidad:

- Asegurar unidad de medida base `UN`.
- Agregar permisos de Inventario a:
  - Administrador.
  - Contador.
  - Auditor.

Incluye permisos de:

```txt
Fase 1:
- inventario.productos.ver
- inventario.productos.crear
- inventario.productos.editar
- inventario.bodegas.ver
- inventario.bodegas.crear

Fase 2:
- inventario.movimientos.ver
- inventario.movimientos.entrada
- inventario.movimientos.salida
- inventario.movimientos.traspaso
- inventario.movimientos.ajuste
- inventario.kardex.ver

Fase 3:
- inventario.valorizacion.ver
```

---

### InventarioPostmanSeeder

Archivo:

```txt
database/seeders/InventarioPostmanSeeder.php
```

Responsabilidad:

Crear usuarios locales para pruebas Postman:

```txt
contador@example.com / password
auditor@example.com / password
```

Este seeder solo debe ejecutarse en ambientes:

```txt
local
testing
```

No debe poblar usuarios demo en producción.

---

## Ejecutar seeders

```bash
php artisan db:seed --class=InventarioPermisosSeeder
php artisan db:seed --class=InventarioPostmanSeeder
```

O ejecutar todos los seeders:

```bash
php artisan db:seed
```

---

## Verificación rápida de roles

```bash
php artisan tinker
```

```php
DB::table('roles')
    ->whereIn('nombre', ['Administrador', 'Contador', 'Auditor'])
    ->get(['nombre', 'permisos']);
```

Verificación rápida de usuarios Postman:

```php
DB::table('users')
    ->whereIn('email', ['contador@example.com', 'auditor@example.com'])
    ->get(['id', 'email', 'rol_id']);
```

---

## Pruebas automatizadas Fase 3

### Unit

```bash
php artisan test --filter=InventarioValorizacionServiceTest
```

Cobertura:

```txt
- entrada recalcula PMP por bodega.
- salida mantiene PMP y descuenta valor_total.
- salida con stock insuficiente lanza excepción.
- traspaso transfiere valor usando PMP de origen.
- actualiza costo promedio consolidado del producto.
- listar y resumir valorización respeta empresa y filtros.
```

---

### Feature/API

```bash
php artisan test --filter=InventarioValorizacionApiTest
```

Cobertura:

```txt
- contador puede listar valorización general.
- contador puede consultar valorización de un producto.
- valorización filtra por bodega y search.
- valorización respeta multiempresa.
- auditor puede consultar valorización.
- usuario sin permiso no puede consultar valorización.
- usuario sin token no puede consultar valorización.
- valorización refleja PMP generado por movimientos.
```

---

## Suite recomendada de Inventario

```bash
php artisan test --filter=InventarioValorizacionServiceTest
php artisan test --filter=InventarioValorizacionApiTest
php artisan test --filter=InventarioMovimientoApiTest
php artisan test --filter=MovimientoInventarioModelTest
php artisan test --filter=InventarioApiTest
```

Resultado esperado:

```txt
InventarioValorizacionServiceTest: PASS
InventarioValorizacionApiTest: PASS
InventarioMovimientoApiTest: PASS
MovimientoInventarioModelTest: PASS
InventarioApiTest: PASS
```

---

## Pruebas Postman recomendadas

### Admin

Login:

```http
POST /api/auth/login
```

```json
{
  "email": "admin@tenri.cl",
  "password": "password123"
}
```

Flujo mínimo:

```txt
1. GET /api/inventario/catalogos
2. POST /api/inventario/bodegas
3. POST /api/inventario/productos
4. POST /api/inventario/movimientos entrada 10 x 1000
5. POST /api/inventario/movimientos entrada 5 x 1200
6. GET /api/inventario/valorizacion
7. POST /api/inventario/movimientos salida 3 unidades
8. GET /api/inventario/productos/{id}/valorizacion
9. POST /api/inventario/movimientos traspaso 2 unidades
10. POST /api/inventario/movimientos salida con stock insuficiente
```

Validación PMP esperada después de dos entradas:

```txt
Entrada 1: 10 unidades x 1000 = 10000
Entrada 2: 5 unidades x 1200 = 6000

Stock total: 15
Valor total: 16000
PMP: 1066.6667
```

---

### Contador

Login:

```http
POST /api/auth/login
```

```json
{
  "email": "contador@example.com",
  "password": "password"
}
```

Pruebas:

```txt
GET /api/inventario/valorizacion
Debe responder 200.

POST /api/inventario/movimientos
Debe permitir registrar entrada.
Debe responder 201.
```

---

### Auditor

Login:

```http
POST /api/auth/login
```

```json
{
  "email": "auditor@example.com",
  "password": "password"
}
```

Pruebas:

```txt
GET /api/inventario/valorizacion
Debe responder 200.

GET /api/inventario/movimientos
Debe responder 200.

POST /api/inventario/movimientos
Debe responder 422.
Auditor no debe registrar movimientos.
```

---

## Contrato de errores

### Sin token

```http
GET /api/inventario/valorizacion
```

Respuesta esperada:

```txt
401 Unauthorized
```

---

### Sin permiso

```json
{
  "success": false,
  "message": "..."
}
```

HTTP esperado:

```txt
422
```

---

### Stock insuficiente

```json
{
  "success": false,
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "cantidad": [
      "Stock insuficiente para realizar la salida."
    ]
  }
}
```

HTTP esperado:

```txt
422
```

---

## Decisiones técnicas

```txt
- Se mantiene InventarioMovimientoService como dueño de transacciones y reglas de movimiento.
- Se agrega InventarioValorizacionService para cálculo PMP y valorización.
- Se mantiene DB::transaction().
- Se mantiene lockForUpdate().
- Se mantiene multiempresa.
- Se mantiene contrato success true/false.
- No se agregan Form Requests.
- No se modifica el Handler global.
- No se crea Repository físico.
- No se agregan librerías nuevas.
- No se agrega lógica DTE.
- No se agrega lógica SII.
```

---

## Checklist Fase 3

```txt
[x] Entrada recalcula PMP.
[x] Salida valoriza con PMP.
[x] Traspaso usa PMP de origen.
[x] Ajuste positivo funciona como entrada.
[x] Ajuste negativo funciona como salida.
[x] Merma funciona como ajuste negativo.
[x] producto.costo_promedio se actualiza consolidado.
[x] GET /inventario/valorizacion disponible.
[x] GET /inventario/productos/{id}/valorizacion disponible.
[x] Auditor puede consultar valorización.
[x] Auditor no puede registrar movimientos.
[x] Contador puede consultar y operar.
[x] Sin token responde 401.
[x] Sin permiso responde 422.
[x] Tests unitarios pasan.
[x] Tests Feature/API pasan.
[x] Postman validado con Admin, Contador y Auditor.
[x] Sin DTE.
[x] Sin SII.
```
