# ERP Contable — Inventario Fase 4

## Mermas y ajustes críticos

Esta fase profesionaliza el tratamiento de operaciones sensibles de inventario: mermas, deterioros, pérdidas, vencimientos y ajustes críticos positivos/negativos.

El objetivo es mantener trazabilidad, auditoría, control por permisos, consistencia de stock, Kardex y valorización PMP sin mezclar responsabilidades con otros módulos del ERP.

---

## 1. Alcance de la fase

La Fase 4 agrega:

- Catálogo de tipos de ajuste crítico.
- Registro auditable de mermas, deterioros, pérdidas y vencimientos.
- Registro de ajustes críticos positivos y negativos.
- Motivo obligatorio.
- Observación obligatoria.
- Reporte/listado de ajustes críticos.
- Detalle de ajuste crítico.
- Integración con movimientos de inventario existentes.
- Integración con Kardex.
- Integración con PMP/valorización.
- RBAC usando permisos existentes en `roles.permisos`.
- Tests unitarios y Feature/API.
- Flujo Postman/demo.

---

## 2. Regla crítica: Inventario no gestiona DTE

El módulo de Inventario NO emite, NO gestiona y NO prepara DTE.

No se debe usar en Inventario:

- `codigo_dte`
- `codigo_sii`
- `folio_dte`
- `xml_dte`
- `emitir_dte`
- lógica tributaria/SII

Si se requiere trazabilidad externa, usar solo campos genéricos:

- `referencia`
- `motivo`
- `observacion`
- `origen_modulo`
- `origen_id`

---

## 3. Arquitectura respetada

Ruta del dominio:

```txt
Backend-laravel/app/Domains/Inventario
```

Arquitectura aplicada:

- Laravel 12
- PHP 8.2+
- MySQL/MariaDB
- Laravel Sanctum
- PHPUnit/Laravel
- Services + Eloquent
- Sin capa Repository física
- Sin librerías nuevas
- Sin cambios al manejo global de errores
- Sin tocar otros dominios salvo el ajuste global de lectura de permisos en `AuthController`

Contrato API mantenido:

- `success: true/false`
- `422` para errores de dominio, validación y permisos
- `401` sin token por `auth:sanctum`

---

## 4. RBAC y permisos

### 4.1. Decisión arquitectónica

Inventario NO crea roles, NO crea usuarios y NO asigna permisos automáticamente.

La asignación de permisos pertenece al gestor global de roles.

Inventario solo valida permisos existentes mediante `InventarioPermisoService`.

### 4.2. Permisos usados por Fase 4

```txt
inventario.ajustes_criticos.ver
inventario.ajustes_criticos.crear
```

Uso:

```txt
GET  /api/inventario/ajustes-criticos/tipos  -> inventario.ajustes_criticos.ver
GET  /api/inventario/ajustes-criticos        -> inventario.ajustes_criticos.ver
GET  /api/inventario/ajustes-criticos/{id}   -> inventario.ajustes_criticos.ver
POST /api/inventario/ajustes-criticos        -> inventario.ajustes_criticos.crear
```

No se usa:

```txt
inventario.ajustes_criticos.tipos.ver
```

El listado de tipos queda cubierto por el permiso general de consulta.

### 4.3. Permisos recomendados para demo

Contador:

```txt
inventario.productos.ver
inventario.productos.crear
inventario.productos.editar
inventario.bodegas.ver
inventario.bodegas.crear
inventario.movimientos.ver
inventario.movimientos.entrada
inventario.movimientos.salida
inventario.movimientos.traspaso
inventario.movimientos.ajuste
inventario.kardex.ver
inventario.valorizacion.ver
inventario.ajustes_criticos.ver
inventario.ajustes_criticos.crear
```

Auditor:

```txt
inventario.productos.ver
inventario.bodegas.ver
inventario.movimientos.ver
inventario.kardex.ver
inventario.valorizacion.ver
inventario.ajustes_criticos.ver
```

---

## 5. Seeders

### 5.1. Seeder base

`InventarioCatalogosSeeder` queda dentro del `DatabaseSeeder`.

Responsabilidad:

- Crear/asegurar unidades de medida base.
- No crear usuarios.
- No crear roles.
- No asignar permisos.

Ejemplo de unidades:

```txt
UN
KG
LT
M
M2
M3
HR
CJ
```

### 5.2. Seeder opcional para usuarios Postman

`InventarioPostmanSeeder` queda fuera del `DatabaseSeeder`.

Uso manual:

```bash
php artisan db:seed --class=InventarioPostmanSeeder
```

Responsabilidad:

- Crear usuarios demo para Postman.
- Usar roles existentes creados por `RolSeeder`.
- No crear roles.
- No asignar permisos.

Usuarios demo:

```txt
contador@example.com
auditor@example.com
```

### 5.3. Seeder opcional para permisos demo

`InventarioDemoPermisosSeeder` queda fuera del `DatabaseSeeder`.

Uso manual:

```bash
php artisan db:seed --class=InventarioDemoPermisosSeeder
```

Responsabilidad:

- Asignar permisos de Inventario a roles existentes.
- No crear roles.
- No crear usuarios.
- Usarse solo para demo/Postman.

Flujo demo recomendado:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=InventarioPostmanSeeder
php artisan db:seed --class=InventarioDemoPermisosSeeder
```

---

## 6. Migraciones Fase 4

Archivo:

```txt
database/migrations/2026_05_05_090000_create_inventario_ajustes_criticos_tables.php
```

Tablas creadas:

```txt
inventario_tipos_ajuste_critico
inventario_ajustes_criticos
```

### 6.1. `inventario_tipos_ajuste_critico`

Catálogo global.

Campos principales:

```txt
id
codigo
nombre
descripcion
tipo_movimiento
requiere_stock
activo
created_at
updated_at
```

Tipos base:

```txt
MERMA_OPERACIONAL
DETERIORO
PERDIDA
VENCIMIENTO
AJUSTE_CRITICO_NEGATIVO
AJUSTE_CRITICO_POSITIVO
```

### 6.2. `inventario_ajustes_criticos`

Registro multiempresa y auditable.

Campos principales:

```txt
id
empresa_id
movimiento_inventario_id
tipo_ajuste_critico_id
producto_id
bodega_id
cantidad
costo_unitario
costo_total
motivo
observacion
referencia
origen_modulo
origen_id
registrado_por
created_at
updated_at
```

---

## 7. Models agregados

```txt
app/Domains/Inventario/Models/TipoAjusteCritico.php
app/Domains/Inventario/Models/AjusteCriticoInventario.php
```

### 7.1. `TipoAjusteCritico`

Responsabilidades:

- Representar el catálogo global.
- Identificar si el tipo genera ajuste positivo o negativo.
- Indicar si exige stock suficiente.
- Exponer constantes de códigos base.

### 7.2. `AjusteCriticoInventario`

Responsabilidades:

- Guardar trazabilidad del evento crítico.
- Relacionarse con empresa, movimiento, tipo, producto, bodega y usuario.
- Exponer scopes para reportes por empresa, producto, bodega, tipo y fecha.

---

## 8. Service agregado

Archivo:

```txt
app/Domains/Inventario/Services/InventarioAjusteCriticoService.php
```

Responsabilidades:

- Listar tipos de ajuste crítico.
- Listar ajustes críticos.
- Obtener detalle de ajuste crítico.
- Registrar ajuste crítico.
- Validar permisos.
- Validar multiempresa.
- Validar producto activo.
- Validar bodega activa.
- Validar tipo activo.
- Exigir motivo.
- Exigir observación.
- Validar cantidad mayor a cero.
- Delegar movimiento real a `InventarioMovimientoService`.
- Mantener stock, Kardex y PMP consistentes.
- Crear registro especializado en `inventario_ajustes_criticos`.

El movimiento real de stock NO se duplica. Se delega al service existente de movimientos para mantener la consistencia del sistema.

---

## 9. Controller y rutas

Controller:

```txt
app/Domains/Inventario/Controllers/InventarioController.php
```

Métodos agregados:

```php
public function tiposAjusteCritico(Request $request, InventarioAjusteCriticoService $ajusteCriticoService): JsonResponse
public function ajustesCriticos(Request $request, InventarioAjusteCriticoService $ajusteCriticoService): JsonResponse
public function registrarAjusteCritico(Request $request, InventarioAjusteCriticoService $ajusteCriticoService): JsonResponse
public function verAjusteCritico(Request $request, int $id, InventarioAjusteCriticoService $ajusteCriticoService): JsonResponse
```

Rutas:

```txt
GET    /api/inventario/ajustes-criticos/tipos
GET    /api/inventario/ajustes-criticos
POST   /api/inventario/ajustes-criticos
GET    /api/inventario/ajustes-criticos/{id}
```

Todas protegidas por:

```txt
auth:sanctum
```

---

## 10. Reglas de negocio

Para registrar un ajuste crítico:

- Usuario autenticado.
- Usuario con permiso `inventario.ajustes_criticos.crear`.
- Producto debe pertenecer a la empresa del usuario.
- Producto debe estar activo.
- Bodega debe pertenecer a la empresa del usuario.
- Bodega debe estar activa.
- Tipo de ajuste crítico debe existir.
- Tipo debe estar activo.
- Cantidad debe ser mayor a cero.
- Motivo obligatorio.
- Observación obligatoria.
- Si el tipo es negativo, debe existir stock suficiente.
- Si es `MERMA_OPERACIONAL`, el producto debe permitir merma.
- Todo debe ejecutarse transaccionalmente.
- El movimiento real queda en `inventario_movimientos`.
- El registro auditable queda en `inventario_ajustes_criticos`.

---

## 11. Ejemplos API

### 11.1. Listar tipos

```http
GET /api/inventario/ajustes-criticos/tipos
Authorization: Bearer {token}
```

Respuesta:

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "codigo": "MERMA_OPERACIONAL",
      "nombre": "Merma operacional",
      "tipo_movimiento": "ajuste_negativo",
      "requiere_stock": true,
      "activo": true
    }
  ]
}
```

### 11.2. Registrar deterioro

```http
POST /api/inventario/ajustes-criticos
Authorization: Bearer {token}
```

Body:

```json
{
  "tipo_ajuste_critico_id": 2,
  "producto_id": 1,
  "bodega_id": 1,
  "cantidad": 2,
  "motivo": "Producto deteriorado en bodega",
  "observacion": "Detectado durante control interno de inventario",
  "referencia": "DET-POSTMAN-001"
}
```

Respuesta:

```json
{
  "success": true,
  "message": "Ajuste crítico registrado correctamente.",
  "data": {
    "id": 1,
    "empresa_id": 1,
    "producto_id": 1,
    "bodega_id": 1,
    "cantidad": "2.0000",
    "motivo": "Producto deteriorado en bodega"
  }
}
```

### 11.3. Registrar ajuste crítico positivo

```json
{
  "tipo_ajuste_critico_id": 6,
  "producto_id": 1,
  "bodega_id": 1,
  "cantidad": 3,
  "costo_unitario": 120,
  "motivo": "Corrección positiva autorizada",
  "observacion": "Diferencia positiva detectada durante conteo físico",
  "referencia": "AJ-POS-POSTMAN-001"
}
```

### 11.4. Error sin motivo

```json
{
  "success": false,
  "message": "El motivo es obligatorio para registrar un ajuste crítico.",
  "errors": {
    "motivo": [
      "El motivo es obligatorio para registrar un ajuste crítico."
    ]
  }
}
```

Código HTTP:

```txt
422
```

### 11.5. Error sin token

Código HTTP:

```txt
401
```

---

## 12. Postman demo

### 12.1. Preparar demo

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=InventarioPostmanSeeder
php artisan db:seed --class=InventarioDemoPermisosSeeder
```

### 12.2. Logins

Admin:

```json
{
  "email": "admin@tenri.cl",
  "password": "password123"
}
```

Contador:

```json
{
  "email": "contador@example.com",
  "password": "password"
}
```

Auditor:

```json
{
  "email": "auditor@example.com",
  "password": "password"
}
```

### 12.3. Flujo recomendado

1. Login admin.
2. Login contador.
3. Login auditor.
4. Listar tipos de ajuste crítico.
5. Crear producto.
6. Crear bodega.
7. Registrar entrada inicial.
8. Registrar deterioro.
9. Registrar pérdida.
10. Registrar vencimiento.
11. Registrar ajuste crítico positivo.
12. Listar ajustes críticos.
13. Ver detalle.
14. Probar auditor consultando.
15. Probar auditor intentando registrar.
16. Probar error sin motivo.
17. Probar error sin observación.
18. Probar error por stock insuficiente.
19. Probar endpoint sin token.

---

## 13. Tests

### 13.1. Helper para tests

Archivo:

```txt
tests/Concerns/PreparaInventarioTest.php
```

Responsabilidad:

- Ejecutar usuarios demo solo dentro de tests.
- Usar roles existentes.
- No crear roles desde tests de Inventario.
- Simular permisos actualizando `roles.permisos` según el escenario.
- Mantener el `DatabaseSeeder` limpio.

### 13.2. Tests unitarios

Archivo:

```txt
tests/Unit/InventarioAjusteCriticoServiceTest.php
```

Casos cubiertos:

- Lista tipos con permiso.
- Bloquea sin permiso.
- Registra merma válida.
- Registra ajuste positivo.
- Exige motivo.
- Exige observación.
- Rechaza cantidad negativa.
- Rechaza stock insuficiente.
- Rechaza producto inactivo.
- Rechaza bodega inactiva.
- Rechaza tipo inactivo.
- Respeta `permite_merma`.
- Bloquea auditor sin permiso crear.
- Listado respeta empresa y filtros.

### 13.3. Tests Feature/API

Archivo:

```txt
tests/Feature/InventarioAjusteCriticoApiTest.php
```

Casos cubiertos:

- 401 sin token.
- Contador lista tipos.
- Usuario sin permiso ver no lista.
- Contador registra deterioro.
- Contador registra ajuste positivo.
- Auditor lista pero no registra.
- Error sin motivo.
- Error sin observación.
- Error por stock insuficiente.
- Listado respeta filtros y empresa.
- Detalle por empresa.
- Bloqueo de detalle de otra empresa.

### 13.4. Comandos

```bash
php artisan test --filter=InventarioAjusteCriticoServiceTest
php artisan test --filter=InventarioAjusteCriticoApiTest
php artisan test --filter=Inventario
```

---

## 14. Validaciones manuales

### Base limpia

```bash
php artisan migrate:fresh --seed
```

```php
DB::table('roles')->select('nombre', 'permisos')->get();
```

Esperado:

```txt
Administrador -> null
Contador      -> null
Ventas        -> null
Auditor       -> null
```

### Usuarios demo opcionales

```bash
php artisan db:seed --class=InventarioPostmanSeeder
```

```php
DB::table('usuarios')
    ->whereIn('email', ['contador@example.com', 'auditor@example.com'])
    ->select('email', 'rol_id')
    ->get();
```

### Permisos demo opcionales

```bash
php artisan db:seed --class=InventarioDemoPermisosSeeder
```

```php
DB::table('roles')->select('nombre', 'permisos')->get();
```

---

## 15. Archivos involucrados

### Nuevos

```txt
app/Domains/Inventario/Models/TipoAjusteCritico.php
app/Domains/Inventario/Models/AjusteCriticoInventario.php
app/Domains/Inventario/Services/InventarioAjusteCriticoService.php
database/migrations/2026_05_05_090000_create_inventario_ajustes_criticos_tables.php
database/seeders/InventarioDemoPermisosSeeder.php
tests/Concerns/PreparaInventarioTest.php
tests/Unit/InventarioAjusteCriticoServiceTest.php
tests/Feature/InventarioAjusteCriticoApiTest.php
```

### Modificados

```txt
app/Domains/Core/Controllers/AuthController.php
app/Domains/Inventario/Controllers/InventarioController.php
routes/api.php
database/seeders/DatabaseSeeder.php
database/seeders/InventarioCatalogosSeeder.php
Frontend/src/Modulos/Administrador/GestionRoles.jsx
```

### Opcionales para demo

```txt
database/seeders/InventarioPostmanSeeder.php
database/seeders/InventarioDemoPermisosSeeder.php
```

---

## 16. Checklist final Fase 4

```txt
[x] Sin DTE.
[x] Sin SII.
[x] Sin codigo_dte/codigo_sii/folio_dte/xml_dte.
[x] Migraciones no asignan permisos a roles.
[x] Seeders base no asignan permisos a roles.
[x] Seeders demo son opcionales.
[x] AuthController lee roles.permisos.
[x] Inventario valida permisos, no los asigna.
[x] Gestión de roles queda fuera de Inventario.
[x] Catálogo de tipos críticos creado.
[x] Registro auditable de ajustes críticos creado.
[x] Motivo obligatorio.
[x] Observación obligatoria.
[x] Stock suficiente en ajustes negativos.
[x] Auditor no registra.
[x] Contador registra si tiene permiso.
[x] Kardex se mantiene mediante InventarioMovimientoService.
[x] PMP se mantiene mediante flujo de movimientos.
[x] Tests unitarios agregados.
[x] Tests Feature/API agregados.
[x] Flujo Postman documentado.
```

---

## 17. Commits sugeridos

Commit para saneamiento RBAC/seeders/tests:

```bash
git add .
git commit -m "refactor(inventario): alinear roles permisos seeders y pruebas"
```

Commit para Fase 4:

```bash
git add .
git commit -m "feat(inventario): agregar mermas y ajustes criticos"
```

---

## 18. Estado esperado

Al finalizar esta fase, Inventario tendrá un flujo profesional para operaciones sensibles de stock:

- Las mermas y pérdidas quedan auditadas.
- Los ajustes críticos generan Kardex.
- La valorización permanece consistente.
- Los roles se administran fuera de Inventario.
- La demo se puede preparar con seeders opcionales.
- El módulo sigue aislado de DTE/SII.
