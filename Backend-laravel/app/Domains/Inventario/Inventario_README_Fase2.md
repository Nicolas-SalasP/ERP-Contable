# Dominio Inventario

Módulo de Inventario del ERP Contable.

Este dominio gestiona productos, unidades de medida, bodegas, stock, movimientos de inventario y Kardex.

> Importante: Inventario **NO** emite, gestiona ni prepara DTE.  
> No se usan campos como `codigo_dte`, `codigo_sii`, `folio_dte` ni `xml_dte`.  
> Si se requiere trazabilidad externa, se utilizan campos genéricos como `referencia`, `motivo` y `observacion`.

---

## Stack

- Laravel 12
- PHP 8.2+
- MySQL/MariaDB
- Laravel Sanctum
- Arquitectura por dominios
- PHPUnit/Laravel para testing
- RBAC mediante roles/permisos

---

## Ubicación del dominio

```txt
Backend-laravel/app/Domains/Inventario
```

Estructura principal:

```txt
Controllers/
  InventarioController.php

Models/
  UnidadMedida.php
  Bodega.php
  Producto.php
  StockProducto.php
  MovimientoInventario.php

Services/
  InventarioService.php
  InventarioPermisoService.php
  InventarioMovimientoService.php
```

---

# Fase 1 - Productos, Bodegas, Unidades y Stock Inicial

## Funcionalidades

- Listar catálogos de inventario.
- Crear y listar productos.
- Obtener detalle de producto.
- Actualizar productos.
- Crear y listar bodegas.
- Validar SKU único por empresa.
- Validar código de bodega único por empresa.
- Validar unidad de medida existente.
- Validar bodega por defecto de la misma empresa.
- Validar valores monetarios y stock mínimo no negativos.
- Crear stock inicial en cero cuando un producto tiene bodega por defecto.
- Proteger endpoints con Laravel Sanctum.
- Validar permisos mediante RBAC.
- Mantener aislamiento multiempresa.

## Tablas Fase 1

```txt
inventario_unidades_medida
inventario_bodegas
inventario_productos
inventario_stock
```

## Endpoints Fase 1

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/api/inventario/catalogos` | Lista unidades, bodegas, tipos de producto y métodos de valorización |
| GET | `/api/inventario/productos` | Lista productos de la empresa |
| POST | `/api/inventario/productos` | Crea producto |
| GET | `/api/inventario/productos/{id}` | Obtiene detalle de producto |
| PUT | `/api/inventario/productos/{id}` | Actualiza producto |
| GET | `/api/inventario/bodegas` | Lista bodegas |
| POST | `/api/inventario/bodegas` | Crea bodega |

## Permisos Fase 1

```txt
inventario.productos.ver
inventario.productos.crear
inventario.productos.editar
inventario.bodegas.ver
inventario.bodegas.crear
```

---

# Fase 2 - Movimientos de Inventario y Kardex

## Objetivo

Implementar movimientos de inventario y Kardex consultable, manteniendo consistencia de stock, control multiempresa y permisos RBAC.

La tabla `inventario_movimientos` actúa como fuente de historial para el Kardex.

---

## Funcionalidades Fase 2

- Registrar entradas de stock.
- Registrar salidas de stock.
- Registrar traspasos entre bodegas.
- Registrar ajustes positivos.
- Registrar ajustes negativos.
- Registrar mermas usando `tipo = ajuste_negativo` y `motivo = merma`.
- Actualizar `inventario_stock` transaccionalmente.
- Validar stock insuficiente.
- Validar cantidades positivas.
- Validar producto activo.
- Validar bodega activa.
- Validar producto y bodega pertenecientes a la misma empresa.
- Consultar movimientos.
- Consultar Kardex general.
- Consultar Kardex por producto.
- Mantener endpoints protegidos por Sanctum.
- Mantener permisos mediante RBAC.

---

## Tabla Fase 2

```txt
inventario_movimientos
```

Campos principales:

```txt
id
empresa_id
producto_id
tipo
bodega_origen_id
bodega_destino_id
cantidad
stock_origen_antes
stock_origen_despues
stock_destino_antes
stock_destino_despues
costo_unitario
costo_total
referencia
motivo
observacion
created_by
fecha_movimiento
created_at
updated_at
```

---

## Tipos de movimiento

```txt
entrada
salida
traspaso
ajuste_positivo
ajuste_negativo
```

### Entrada

Aumenta stock en la bodega destino.

Requiere:

```txt
producto_id
bodega_destino_id
cantidad > 0
```

### Salida

Disminuye stock desde la bodega origen.

Requiere:

```txt
producto_id
bodega_origen_id
cantidad > 0
stock suficiente
```

### Traspaso

Mueve stock entre dos bodegas.

Requiere:

```txt
producto_id
bodega_origen_id
bodega_destino_id
cantidad > 0
stock suficiente en origen
origen distinto de destino
```

### Ajuste positivo

Aumenta stock por corrección.

Requiere:

```txt
producto_id
bodega_destino_id
cantidad > 0
```

### Ajuste negativo

Disminuye stock por corrección, pérdida o merma.

Requiere:

```txt
producto_id
bodega_origen_id
cantidad > 0
stock suficiente
```

### Merma

Una merma se registra como ajuste negativo:

```json
{
  "tipo": "ajuste_negativo",
  "motivo": "merma"
}
```

---

## Motivos soportados

```txt
compra
venta_interna
traspaso_bodega
correccion_stock
merma
perdida
devolucion
ingreso_manual
egreso_manual
```

---

## Endpoints Fase 2

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/api/inventario/movimientos` | Lista movimientos de inventario |
| POST | `/api/inventario/movimientos` | Registra entrada, salida, traspaso o ajuste |
| GET | `/api/inventario/kardex` | Consulta Kardex general |
| GET | `/api/inventario/productos/{id}/kardex` | Consulta Kardex de un producto |

---

## Permisos Fase 2

```txt
inventario.movimientos.ver
inventario.movimientos.entrada
inventario.movimientos.salida
inventario.movimientos.traspaso
inventario.movimientos.ajuste
inventario.kardex.ver
```

### Distribución sugerida por rol

#### Administrador

Puede ejecutar todas las operaciones.

```txt
inventario.movimientos.ver
inventario.movimientos.entrada
inventario.movimientos.salida
inventario.movimientos.traspaso
inventario.movimientos.ajuste
inventario.kardex.ver
```

#### Contador

Puede ejecutar operaciones de inventario completas.

```txt
inventario.movimientos.ver
inventario.movimientos.entrada
inventario.movimientos.salida
inventario.movimientos.traspaso
inventario.movimientos.ajuste
inventario.kardex.ver
```

#### Auditor

Solo puede consultar movimientos y Kardex.

```txt
inventario.movimientos.ver
inventario.kardex.ver
```

---

# Ejemplos de uso API

Todos los endpoints requieren token Sanctum:

```txt
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

---

## Login

```http
POST /api/auth/login
```

Body:

```json
{
  "email": "usuario@example.com",
  "password": "password"
}
```

Respuesta esperada:

```json
{
  "token": "TOKEN_SANCTUM",
  "user": {
    "id": 1,
    "empresa_id": 1
  }
}
```

---

## Crear bodega

```http
POST /api/inventario/bodegas
```

Body:

```json
{
  "codigo": "BOD-POSTMAN-01",
  "nombre": "Bodega Postman 01",
  "direccion": "Santiago",
  "estado": "ACTIVA"
}
```

---

## Crear producto

```http
POST /api/inventario/productos
```

Body:

```json
{
  "sku": "POSTMAN-PROD-001",
  "nombre": "Producto prueba movimiento",
  "descripcion": "Producto creado para probar movimientos de inventario",
  "tipo_producto": "BIEN",
  "unidad_medida_id": 1,
  "metodo_valorizacion": "PMP",
  "costo_promedio": 1000,
  "precio_venta_neto": 1500,
  "afecto_iva": true,
  "stock_minimo": 0,
  "bodega_defecto_id": 1,
  "permite_merma": true,
  "activo": true
}
```

---

## Registrar entrada

```http
POST /api/inventario/movimientos
```

Body:

```json
{
  "tipo": "entrada",
  "producto_id": 1,
  "bodega_destino_id": 1,
  "cantidad": 10,
  "costo_unitario": 1000,
  "referencia": "POSTMAN-ENTRADA-001",
  "motivo": "ingreso_manual",
  "observacion": "Entrada creada desde Postman Fase 2"
}
```

Respuesta esperada:

```json
{
  "success": true,
  "data": {
    "tipo": "entrada",
    "cantidad": "10.0000",
    "stock_destino_antes": "0.0000",
    "stock_destino_despues": "10.0000"
  },
  "message": "Movimiento de inventario registrado correctamente."
}
```

---

## Registrar salida

```http
POST /api/inventario/movimientos
```

Body:

```json
{
  "tipo": "salida",
  "producto_id": 1,
  "bodega_origen_id": 1,
  "cantidad": 2,
  "referencia": "POSTMAN-SALIDA-001",
  "motivo": "egreso_manual",
  "observacion": "Salida creada desde Postman Fase 2"
}
```

Respuesta esperada:

```json
{
  "success": true,
  "data": {
    "tipo": "salida",
    "cantidad": "2.0000",
    "stock_origen_antes": "10.0000",
    "stock_origen_despues": "8.0000"
  },
  "message": "Movimiento de inventario registrado correctamente."
}
```

---

## Registrar traspaso

```http
POST /api/inventario/movimientos
```

Body:

```json
{
  "tipo": "traspaso",
  "producto_id": 1,
  "bodega_origen_id": 1,
  "bodega_destino_id": 2,
  "cantidad": 1,
  "referencia": "POSTMAN-TRASPASO-001",
  "motivo": "traspaso_bodega",
  "observacion": "Traspaso entre bodegas desde Postman"
}
```

Respuesta esperada:

```json
{
  "success": true,
  "data": {
    "tipo": "traspaso",
    "cantidad": "1.0000",
    "stock_origen_antes": "8.0000",
    "stock_origen_despues": "7.0000",
    "stock_destino_antes": "0.0000",
    "stock_destino_despues": "1.0000"
  },
  "message": "Movimiento de inventario registrado correctamente."
}
```

---

## Registrar ajuste positivo

```http
POST /api/inventario/movimientos
```

Body:

```json
{
  "tipo": "ajuste_positivo",
  "producto_id": 1,
  "bodega_destino_id": 1,
  "cantidad": 3,
  "costo_unitario": 1200,
  "referencia": "POSTMAN-AJUSTE-POS-001",
  "motivo": "correccion_stock",
  "observacion": "Corrección positiva de stock desde Postman"
}
```

---

## Registrar ajuste negativo / merma

```http
POST /api/inventario/movimientos
```

Body:

```json
{
  "tipo": "ajuste_negativo",
  "producto_id": 1,
  "bodega_origen_id": 1,
  "cantidad": 1,
  "referencia": "POSTMAN-MERMA-001",
  "motivo": "merma",
  "observacion": "Merma registrada desde Postman"
}
```

---

# Consultas

## Listar movimientos

```http
GET /api/inventario/movimientos
```

Filtros opcionales:

```txt
producto_id
bodega_id
tipo
desde
hasta
per_page
```

Ejemplos:

```http
GET /api/inventario/movimientos?producto_id=1
GET /api/inventario/movimientos?tipo=entrada
GET /api/inventario/movimientos?bodega_id=1
```

Respuesta esperada:

```json
{
  "success": true,
  "data": [],
  "pagination": {
    "total": 5,
    "totalPages": 1,
    "page": 1
  }
}
```

---

## Kardex general

```http
GET /api/inventario/kardex
```

Filtros opcionales:

```txt
producto_id
bodega_id
tipo
desde
hasta
per_page
```

Ejemplo:

```http
GET /api/inventario/kardex?producto_id=1
```

---

## Kardex por producto

```http
GET /api/inventario/productos/{id}/kardex
```

Ejemplo:

```http
GET /api/inventario/productos/1/kardex
```

---

# Errores esperados

## Cantidad cero

Body:

```json
{
  "tipo": "entrada",
  "producto_id": 1,
  "bodega_destino_id": 1,
  "cantidad": 0
}
```

Respuesta esperada:

```json
{
  "success": false,
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "cantidad": [
      "The cantidad field must be greater than 0."
    ]
  }
}
```

Status:

```txt
422
```

---

## Cantidad negativa

Body:

```json
{
  "tipo": "entrada",
  "producto_id": 1,
  "bodega_destino_id": 1,
  "cantidad": -5
}
```

Status esperado:

```txt
422
```

---

## Stock insuficiente

Body:

```json
{
  "tipo": "salida",
  "producto_id": 1,
  "bodega_origen_id": 1,
  "cantidad": 999999
}
```

Respuesta esperada:

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

Status:

```txt
422
```

---

## Traspaso a la misma bodega

Body:

```json
{
  "tipo": "traspaso",
  "producto_id": 1,
  "bodega_origen_id": 1,
  "bodega_destino_id": 1,
  "cantidad": 1
}
```

Status esperado:

```txt
422
```

---

## Producto inexistente o de otra empresa

Body:

```json
{
  "tipo": "entrada",
  "producto_id": 999999,
  "bodega_destino_id": 1,
  "cantidad": 1
}
```

Status esperado:

```txt
422
```

---

## Bodega inexistente o de otra empresa

Body:

```json
{
  "tipo": "entrada",
  "producto_id": 1,
  "bodega_destino_id": 999999,
  "cantidad": 1
}
```

Status esperado:

```txt
422
```

---

## Sin token

```http
GET /api/inventario/movimientos
```

Status esperado:

```txt
401
```

---

## Auditor intentando crear movimiento

Con un usuario Auditor:

```http
POST /api/inventario/movimientos
```

Body:

```json
{
  "tipo": "entrada",
  "producto_id": 1,
  "bodega_destino_id": 1,
  "cantidad": 1,
  "referencia": "AUDITOR-NO-DEBE-CREAR"
}
```

Respuesta esperada:

```json
{
  "success": false,
  "message": "No tienes permisos para ejecutar esta operación de inventario."
}
```

Status esperado según contrato actual del dominio:

```txt
422
```

---

# Testing

## Tests de Fase 1

```txt
InventarioPermisoServiceTest
InventarioApiTest
```

Cubren:

```txt
permisos
productos
bodegas
valores negativos
SKU duplicado
bodega duplicada
unidad inexistente
bodega externa
multiempresa
acceso sin token
```

## Tests de Fase 2

```txt
InventarioMovimientoApiTest
MovimientoInventarioModelTest
```

Cubren:

```txt
entrada aumenta stock
salida descuenta stock
stock insuficiente
traspaso entre bodegas
traspaso a misma bodega
ajuste positivo
ajuste negativo
merma
cantidad cero
cantidad negativa
producto inactivo
bodega inactiva
producto de otra empresa
bodega de otra empresa
auditor no crea movimientos
auditor consulta movimientos y kardex
kardex multiempresa
sin token
helpers del modelo
```

Ejecutar tests:

```bash
php artisan optimize:clear
php artisan test --filter=InventarioMovimientoApiTest
php artisan test --filter=MovimientoInventarioModelTest
php artisan test --filter=Inventario
```

Nota local:

```txt
Si aparece warning por falta de .env local, crear el archivo desde .env.example.
Los tests usan la configuración de phpunit.xml para ejecutar base de datos de testing.
```

---

# Pruebas Postman realizadas Fase 2

## Positivas

```txt
[OK] Login con token Sanctum
[OK] Crear bodegas
[OK] Crear producto
[OK] Registrar entrada
[OK] Registrar salida
[OK] Registrar traspaso
[OK] Registrar ajuste positivo
[OK] Registrar ajuste negativo / merma
[OK] Listar movimientos
[OK] Ver Kardex por producto
[OK] Auditor puede consultar movimientos y Kardex
```

## Negativas

```txt
[OK] Cantidad cero falla con 422
[OK] Cantidad negativa falla con 422
[OK] Stock insuficiente falla con 422
[OK] Traspaso a la misma bodega falla con 422
[OK] Producto inexistente / externo falla con 422
[OK] Bodega inexistente / externa falla con 422
[OK] Sin token falla con 401
[OK] Auditor no puede registrar movimientos
```

---

# Contrato actual de respuesta

Este dominio mantiene el contrato actual del ERP:

## Éxito

```json
{
  "success": true,
  "data": {},
  "message": "Operación realizada correctamente."
}
```

## Error controlado

```json
{
  "success": false,
  "message": "Mensaje de error."
}
```

## Error de validación

```json
{
  "success": false,
  "message": "Los datos enviados no son válidos.",
  "errors": {}
}
```

---

# Decisiones técnicas

## No DTE

Inventario no crea ni gestiona documentos tributarios electrónicos.

Campos permitidos para trazabilidad:

```txt
referencia
motivo
observacion
```

Campos no permitidos en este dominio:

```txt
codigo_dte
codigo_sii
folio_dte
xml_dte
```

## Kardex desde movimientos

No se crea tabla `inventario_kardex`.

El Kardex se consulta desde:

```txt
inventario_movimientos
```

porque cada movimiento guarda:

```txt
stock antes
stock después
producto
bodega
tipo
cantidad
fecha
usuario
referencia
motivo
```

## Consistencia transaccional

El registro de movimientos utiliza:

```txt
DB::transaction()
lockForUpdate()
```

para proteger la consistencia del stock ante operaciones concurrentes.

## Valorización

La Fase 2 deja base para valorización mediante:

```txt
costo_unitario
costo_total
costo_promedio
valor_total
```

La lógica actual trabaja con costo promedio simple para mantener trazabilidad básica del valor del stock.

---

# Checklist Fase 2

```txt
[OK] Migración inventario_movimientos
[OK] Modelo MovimientoInventario
[OK] Service InventarioMovimientoService
[OK] Controller actualizado
[OK] Rutas agregadas
[OK] Permisos RBAC agregados
[OK] Tests Feature/API agregados
[OK] Tests Unit agregados
[OK] Pruebas Postman positivas
[OK] Pruebas Postman negativas
[OK] Documentación README
```
