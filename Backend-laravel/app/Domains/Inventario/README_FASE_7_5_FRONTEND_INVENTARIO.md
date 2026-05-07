# README — Fase 7.5 Frontend Demo-Operativo de Inventario

## ERP Contable — Módulo Inventario

**Rama de trabajo:** `SLagos-dev`  
**Stack:** Laravel 12, PHP 8.2+, MySQL/MariaDB, Laravel Sanctum, React, Tailwind CSS, SweetAlert2  
**Estado:** Fase 7.5 validada y lista para demo/commit

---

## 1. Objetivo de la Fase 7.5

La Fase 7.5 agrega un **frontend demo-operativo** para el módulo de Inventario del ERP Contable, consumiendo los endpoints backend ya desarrollados hasta la Fase 7.

Esta fase permite usar Inventario desde la interfaz web del ERP, sin depender únicamente de Postman o pruebas backend.

La Fase 7.5 **no reemplaza la Fase 10**, que corresponde al frontend completo y definitivo del módulo. Su objetivo es dejar una interfaz funcional, presentable y coherente con el diseño actual del ERP para demostrar:

- Productos.
- Bodegas.
- Movimientos.
- Kardex.
- Lotes.
- Reservas y disponibilidad.
- Tomas físicas.
- Valorización.
- Dashboard general de Inventario.

---

## 2. Roadmap oficial del módulo Inventario

El módulo Inventario sigue este roadmap:

```txt
1. Productos, bodegas y stock base
2. Movimientos y Kardex
3. PMP y valorización
4. Mermas y ajustes críticos
5. Lotes y vencimientos
6. Reservas y disponibilidad comprometida
7. Toma física e inventario cíclico
8. Reposición y alertas
9. Reportes avanzados/dashboard
10. Frontend completo
11. Hardening, auditoría final y producto vendible
```

La Fase 7.5 se ubica entre la Fase 7 y la Fase 8 como una etapa visual/demo para mostrar el avance real del módulo.

---

## 3. Alcance funcional

Se implementaron vistas frontend para consumir los endpoints reales de Inventario:

```txt
/inventario/dashboard
/inventario/productos
/inventario/bodegas
/inventario/movimientos
/inventario/kardex
/inventario/lotes
/inventario/reservas
/inventario/tomas-fisicas
/inventario/valorizacion
```

La integración respeta:

- Autenticación con Laravel Sanctum.
- Permisos runtime del usuario.
- Layout general del ERP.
- Sidebar actual.
- Wrapper global de API.
- Contrato API existente.
- Arquitectura modular del frontend.

---

## 4. Archivos creados

Se creó el módulo:

```txt
Frontend/src/Modulos/Inventario/
```

Estructura final:

```txt
Frontend/src/Modulos/Inventario/
├── Servicios/
│   └── inventarioApi.js
├── Componentes/
│   └── InventarioUI.jsx
└── Vistas/
    ├── InventarioDashboard.jsx
    ├── ProductosInventario.jsx
    ├── BodegasInventario.jsx
    ├── MovimientosInventario.jsx
    ├── KardexInventario.jsx
    ├── LotesInventario.jsx
    ├── ReservasInventario.jsx
    ├── TomasFisicasInventario.jsx
    └── ValorizacionInventario.jsx
```

---

## 5. Archivos modificados

Además del módulo nuevo, se modificaron archivos globales mínimos:

```txt
Frontend/src/App.jsx
Frontend/src/Componentes/Estructura/BarraLateral.jsx
Frontend/src/Modulos/Administrador/GestionRoles.jsx
Backend-laravel/app/Domains/Core/Controllers/AuthController.php
```

### 5.1 App.jsx

Se agregaron imports y rutas protegidas para Inventario.

Rutas agregadas:

```txt
/inventario
/inventario/dashboard
/inventario/productos
/inventario/bodegas
/inventario/movimientos
/inventario/kardex
/inventario/lotes
/inventario/reservas
/inventario/tomas-fisicas
/inventario/valorizacion
```

La ruta `/inventario` redirige a:

```txt
/inventario/dashboard
```

### 5.2 BarraLateral.jsx

Se agregó el grupo de menú:

```txt
Inventario
```

Con submenús:

```txt
Dashboard Inventario
Productos
Bodegas
Movimientos
Kardex
Lotes
Reservas
Tomas Físicas
Valorización
```

Cada submenú se muestra según permisos.

### 5.3 GestionRoles.jsx

Se agregó la categoría **Inventario** al listado visual de permisos.

### 5.4 AuthController.php

Se ajustó la entrega de permisos runtime.  
El rol Administrador ahora mezcla:

```txt
permisos base del administrador
+
permisos reales guardados en roles.permisos
```

Esto evita que nuevos permisos agregados a la base de datos queden fuera de `erp_user` al iniciar sesión.

---

## 6. Servicio API

Archivo:

```txt
Frontend/src/Modulos/Inventario/Servicios/inventarioApi.js
```

Este servicio centraliza todas las llamadas del módulo Inventario.

Usa el wrapper global:

```js
import { api } from '../../../Configuracion/api';
```

Ventajas:

- No se usa `axios` directo.
- Respeta el token de sesión.
- Respeta la base URL global.
- Respeta headers globales.
- Reduce duplicación de endpoints.
- Facilita mantenimiento futuro.

---

## 7. Componentes UI reutilizables

Archivo:

```txt
Frontend/src/Modulos/Inventario/Componentes/InventarioUI.jsx
```

Incluye componentes y helpers:

```txt
PageHeader
Panel
StatCard
EstadoBadge
LoadingState
EmptyState
AlertBox
PrimaryButton
SecondaryButton
DangerButton
Field
ErrorNotice
TableShell
Th
Td
formatNumber
formatCurrency
formatDate
getProductoNombre
getBodegaNombre
```

Estos componentes permiten mantener una apariencia uniforme con el ERP:

- Cards blancas.
- Bordes `slate`.
- Botones `emerald`.
- Tablas limpias.
- Badges de estado.
- Paneles reutilizables.
- Estética coherente con el dashboard actual.

---

## 8. Endpoints consumidos

### 8.1 Catálogos

```txt
GET /api/inventario/catalogos
```

### 8.2 Productos

```txt
GET  /api/inventario/productos
POST /api/inventario/productos
GET  /api/inventario/productos/{id}
PUT  /api/inventario/productos/{id}
GET  /api/inventario/productos/{id}/kardex
GET  /api/inventario/productos/{id}/valorizacion
GET  /api/inventario/productos/{id}/disponibilidad
GET  /api/inventario/productos/{id}/lotes
```

### 8.3 Bodegas

```txt
GET  /api/inventario/bodegas
POST /api/inventario/bodegas
```

### 8.4 Movimientos

```txt
GET  /api/inventario/movimientos
POST /api/inventario/movimientos
```

### 8.5 Kardex

```txt
GET /api/inventario/kardex
```

### 8.6 Lotes

```txt
GET  /api/inventario/lotes
POST /api/inventario/lotes
GET  /api/inventario/lotes/{id}
PUT  /api/inventario/lotes/{id}
GET  /api/inventario/lotes/{id}/stock
```

### 8.7 Reservas y disponibilidad

```txt
GET  /api/inventario/reservas
POST /api/inventario/reservas
GET  /api/inventario/reservas/{id}
POST /api/inventario/reservas/{id}/cancelar
POST /api/inventario/reservas/{id}/liberar
POST /api/inventario/reservas/{id}/consumir

GET  /api/inventario/disponibilidad
GET  /api/inventario/productos/{id}/disponibilidad
```

### 8.8 Tomas físicas

```txt
GET  /api/inventario/tomas-fisicas
POST /api/inventario/tomas-fisicas
GET  /api/inventario/tomas-fisicas/{id}
POST /api/inventario/tomas-fisicas/{id}/iniciar
POST /api/inventario/tomas-fisicas/{id}/conteos
POST /api/inventario/tomas-fisicas/{id}/cerrar
POST /api/inventario/tomas-fisicas/{id}/ajustar
POST /api/inventario/tomas-fisicas/{id}/cancelar
```

### 8.9 Valorización

```txt
GET /api/inventario/valorizacion
GET /api/inventario/productos/{id}/valorizacion
```

---

## 9. Permisos requeridos

### Productos

```txt
inventario.productos.ver
inventario.productos.crear
inventario.productos.editar
```

### Bodegas

```txt
inventario.bodegas.ver
inventario.bodegas.crear
```

### Movimientos y Kardex

```txt
inventario.movimientos.ver
inventario.movimientos.entrada
inventario.movimientos.salida
inventario.movimientos.traspaso
inventario.movimientos.ajuste
inventario.kardex.ver
```

### Valorización

```txt
inventario.valorizacion.ver
```

### Ajustes críticos

```txt
inventario.ajustes_criticos.ver
inventario.ajustes_criticos.crear
```

### Lotes

```txt
inventario.lotes.ver
inventario.lotes.crear
inventario.lotes.editar
```

### Reservas y disponibilidad

```txt
inventario.reservas.ver
inventario.reservas.crear
inventario.reservas.cancelar
inventario.reservas.liberar
inventario.reservas.consumir
inventario.disponibilidad.ver
```

### Tomas físicas

```txt
inventario.tomas_fisicas.ver
inventario.tomas_fisicas.crear
inventario.tomas_fisicas.contar
inventario.tomas_fisicas.cerrar
inventario.tomas_fisicas.ajustar
inventario.tomas_fisicas.cancelar
```

---

# 10. Uso del Dashboard de Inventario

Ruta:

```txt
/inventario/dashboard
```

El Dashboard de Inventario es la pantalla principal del módulo.

## 10.1 Cards superiores

Muestra cuatro indicadores principales:

### Productos

Cantidad de productos registrados en Inventario.

Uso:

- Validar si el catálogo está cargado.
- Confirmar que productos nuevos aparecen en el sistema.

### Bodegas

Cantidad de bodegas registradas.

Uso:

- Confirmar que existen ubicaciones de stock.
- Validar que se puedan operar movimientos y tomas físicas.

### Reservas activas

Cantidad de reservas activas o comprometidas.

Uso:

- Revisar stock comprometido.
- Ver si existen reservas pendientes.
- Confirmar que una reserva creada desde frontend se refleje en dashboard.

### Tomas abiertas

Cantidad de tomas físicas pendientes.

Cuenta tomas en estados:

```txt
BORRADOR
EN_CONTEO
CERRADA
```

No cuenta tomas:

```txt
AJUSTADA
CANCELADA
```

Uso:

- Ver si existen conteos pendientes.
- Detectar tomas listas para ajustar.
- Confirmar que una toma ajustada ya no queda abierta.

---

## 10.2 Stock valorizado

El dashboard muestra un bloque de valorización referencial.

Representa el valor total del stock según la respuesta de los endpoints de valorización/PMP.

Uso:

- Confirmar que las entradas con costo unitario valorizan stock.
- Ver impacto de ajustes positivos.
- Mostrar una métrica comercial rápida para demo.

Ejemplo validado:

```txt
Entrada inicial: 10 unidades x $2.500 = $25.000
Ajuste positivo: 2 unidades x $2.500 = $5.000
Stock final esperado del producto demo: $30.000
```

El total del dashboard puede ser mayor si existen otros productos/bodegas.

---

## 10.3 Últimos movimientos

Muestra movimientos recientes de Inventario.

Puede incluir:

```txt
entrada
salida
traspaso
ajuste_positivo
ajuste_negativo
```

Uso:

- Confirmar que una entrada aparece después de registrarla.
- Confirmar que un ajuste desde toma física genera movimiento real.
- Revisar referencia, producto, bodega y cantidad.

Se aplicó truncado visual para referencias largas, manteniendo el valor completo como tooltip.

---

## 10.4 Tomas físicas recientes

Muestra tomas físicas recientes.

Estados posibles:

```txt
BORRADOR
EN_CONTEO
CERRADA
AJUSTADA
CANCELADA
```

Uso:

- Confirmar que una toma creada aparece en dashboard.
- Ver rápidamente si quedó ajustada, cancelada o pendiente.
- Mostrar trazabilidad visual de inventario físico.

---

## 11. Uso recomendado de cada vista

## 11.1 Productos

Ruta:

```txt
/inventario/productos
```

Permite:

- Crear producto.
- Listar producto.
- Buscar producto.
- Definir si maneja lotes.
- Definir si requiere fecha de vencimiento.
- Revisar PMP/costo promedio.

Producto demo validado:

```txt
SKU: PROD-TF-001
Nombre: Producto Toma Física Demo
Descripción: Producto creado para validar flujo completo de toma física
Unidad: Unidad
Costo promedio: 0
Stock mínimo: 0
Maneja lotes: No
Requiere vencimiento: No
Activo: Sí
```

---

## 11.2 Bodegas

Ruta:

```txt
/inventario/bodegas
```

Permite:

- Crear bodega.
- Listar bodega.
- Buscar bodega.
- Ver estado activa/inactiva.

Bodega demo validada:

```txt
Código: BOD-TF-001
Nombre: Bodega Toma Física Demo
Ubicación: Santiago
Descripción: Bodega creada para validar toma física
Bodega activa: Sí
```

Compatibilidad visual:

La vista reconoce bodega activa mediante:

```txt
activa
activo
estado = ACTIVA
```

---

## 11.3 Movimientos

Ruta:

```txt
/inventario/movimientos
```

Permite registrar:

```txt
entrada
salida
traspaso
ajuste_positivo
ajuste_negativo
```

Entrada demo validada:

```txt
Tipo: entrada
Producto: Producto Toma Física Demo
Bodega destino: Bodega Toma Física Demo
Cantidad: 10
Costo unitario: 2500
Referencia: ENT-TF-FRONT-001
Motivo: compra_inicial
Observación: Entrada inicial para validar toma física desde frontend
```

Resultado esperado:

```txt
Stock físico aumenta.
Kardex registra entrada.
Valorización se actualiza.
Dashboard muestra movimiento.
```

---

## 11.4 Kardex

Ruta:

```txt
/inventario/kardex
```

Permite consultar trazabilidad por:

- Producto.
- Bodega.
- Lote.
- Tipo.
- Fechas.

Movimientos validados:

```txt
ENT-TF-FRONT-001
AJ-TF-...
```

Uso:

- Confirmar entradas.
- Confirmar ajustes generados desde toma física.
- Ver cantidad, referencia y motivo.
- Auditar movimientos reales de stock.

Pendiente menor:

En algunas respuestas el saldo aparece como `-`, porque el backend no siempre entrega un campo estándar de saldo.

---

## 11.5 Lotes

Ruta:

```txt
/inventario/lotes
```

Permite:

- Listar lotes.
- Crear lotes.
- Filtrar por producto.
- Revisar vencimiento.
- Ver estado visual de lote.

Estados visuales:

```txt
Sin vencimiento
Vigente
Vence pronto
Vencido
```

---

## 11.6 Reservas

Ruta:

```txt
/inventario/reservas
```

Permite:

- Crear reserva.
- Listar reservas.
- Liberar reserva.
- Consumir reserva.
- Cancelar reserva.

Reserva demo validada:

```txt
Producto: Producto Toma Física Demo
Bodega: Bodega Toma Física Demo
Lote: Sin lote
Cantidad: 1
Referencia: RES-FRONT-001
Motivo: reserva_comercial
Observación: Reserva demo final para validar disponibilidad
```

Regla crítica:

```txt
Crear reserva NO descuenta stock físico.
Crear reserva solo compromete disponibilidad.
Consumir reserva genera movimiento real.
```

Resultado esperado:

```txt
Reserva aparece en listado.
Reservas activas aumenta en dashboard.
Stock físico se mantiene.
```

---

## 11.7 Tomas Físicas

Ruta:

```txt
/inventario/tomas-fisicas
```

Es la vista principal de la Fase 7.5.

Permite:

- Crear toma física.
- Iniciar toma.
- Ver detalle.
- Registrar conteos.
- Cerrar toma.
- Ajustar toma.
- Cancelar toma.
- Ver diferencia.
- Ver movimiento generado.

Estados:

```txt
BORRADOR
EN_CONTEO
CERRADA
AJUSTADA
CANCELADA
```

Regla principal:

```txt
diferencia = stock_contado - stock_sistema
```

Comportamiento:

```txt
diferencia > 0 => ajuste_positivo
diferencia < 0 => ajuste_negativo
diferencia = 0 => no genera movimiento
```

Regla crítica:

```txt
La toma física compara contra stock físico.
No compara contra stock disponible.
Las reservas activas no alteran el stock_sistema capturado.
```

---

## 11.8 Valorización

Ruta:

```txt
/inventario/valorizacion
```

Muestra:

- Producto.
- Bodega.
- Stock.
- PMP/costo promedio.
- Valor total.

Uso:

- Validar PMP.
- Validar stock valorizado.
- Confirmar valor después de entradas y ajustes.
- Apoyar reportes contables futuros.

---

# 12. Flujo demo completo recomendado

Este es el flujo recomendado para presentar la Fase 7.5:

## 12.1 Crear producto

```txt
SKU: PROD-TF-001
Nombre: Producto Toma Física Demo
```

## 12.2 Crear bodega

```txt
Código: BOD-TF-001
Nombre: Bodega Toma Física Demo
```

## 12.3 Registrar entrada inicial

```txt
Tipo: entrada
Cantidad: 10
Costo unitario: 2500
Referencia: ENT-TF-FRONT-001
```

Resultado:

```txt
Stock sistema: 10
PMP: $2.500
Valor esperado: $25.000
```

## 12.4 Crear toma física

```txt
Tipo: BODEGA
Bodega: Bodega Toma Física Demo
Referencia: TF-FRONT-001
Motivo: inventario_ciclico
```

## 12.5 Iniciar toma

Estado esperado:

```txt
EN_CONTEO
```

## 12.6 Ver detalle

Debe mostrar:

```txt
Producto: Producto Toma Física Demo
Stock sistema: 10
Stock contado: vacío
Diferencia: 0
```

## 12.7 Registrar conteo

Ingresar:

```txt
Stock contado: 12
```

Resultado:

```txt
stock_sistema: 10
stock_contado: 12
diferencia: 2
```

## 12.8 Cerrar toma

Estado esperado:

```txt
CERRADA
```

## 12.9 Ajustar toma

Costo unitario para ajuste positivo:

```txt
2500
```

Resultado esperado:

```txt
Estado: AJUSTADA
Movimiento ajuste generado
Tipo movimiento: ajuste_positivo
Cantidad: 2
```

## 12.10 Validar Kardex

Debe mostrar:

```txt
entrada +10
ajuste_positivo +2
```

## 12.11 Validar Dashboard

Debe mostrar:

```txt
Últimos movimientos actualizados
Toma física reciente en estado AJUSTADA
Stock valorizado actualizado
```

---

# 13. Pruebas funcionales realizadas

Se validó:

```txt
Login con usuario administrador
Visualización del menú Inventario
Permisos runtime de Inventario
Dashboard de Inventario
Creación de producto
Creación de bodega
Registro de entrada
Consulta de Kardex
Consulta de valorización
Creación de toma física
Inicio de toma física
Registro de conteo
Cierre de toma física
Ajuste de toma física
Generación de movimiento real
Actualización de Kardex
Actualización de dashboard
Creación de reserva simple
Visualización de reservas activas
```

---

# 14. Pruebas negativas realizadas

Se validó:

```txt
Una toma CANCELADA no permite ajuste.
Una toma AJUSTADA no permite doble ajuste.
El botón Ajustar solo queda disponible en estado CERRADA.
Crear toma no modifica stock.
Iniciar toma no modifica stock.
Registrar conteo no modifica stock.
Cerrar toma no modifica stock.
Solo ajustar modifica stock real.
```

---

# 15. Pulidos visuales realizados

Se aplicaron mejoras finales:

```txt
Referencias largas truncadas en Dashboard.
Referencias largas truncadas en Kardex.
Inputs numéricos de toma física configurados para subir de 1 en 1.
Modal de ajuste configurado para costo unitario entero.
Badges visuales para estados.
Cards superiores del dashboard.
Tablas con scroll horizontal.
Compatibilidad visual para estado de bodega activa/inactiva.
```

---

# 16. Problemas conocidos / pendientes menores

Estos puntos no bloquean la Fase 7.5.

## 16.1 Saldo Kardex

En algunas respuestas el saldo aparece como:

```txt
-
```

Pendiente sugerido:

```txt
Estandarizar en backend un campo de saldo: saldo, stock_resultante o stock_despues.
```

## 16.2 Reservas/disponibilidad

La vista de reservas funciona y permite crear reserva simple, pero puede mejorarse visualmente con paneles de:

```txt
stock físico
stock reservado
stock disponible
```

## 16.3 Fase 7.5 no reemplaza la Fase 10

La Fase 7.5 es demo-operativa.  
El frontend completo y definitivo del módulo sigue reservado para la Fase 10.

---

# 17. Decisiones técnicas y valor profesional para el ERP

## 17.1 Módulo frontend aislado

Se creó Inventario dentro de:

```txt
Frontend/src/Modulos/Inventario
```

Valor profesional:

```txt
Reduce conflictos de merge.
Facilita mantenimiento.
Permite evolucionar Inventario sin romper otros módulos.
```

## 17.2 Servicio API centralizado

Se creó:

```txt
inventarioApi.js
```

Valor profesional:

```txt
Evita duplicación.
Centraliza endpoints.
Facilita cambios futuros.
Mantiene coherencia con el wrapper global del ERP.
```

## 17.3 Integración mínima con el ERP

Solo se tocaron archivos necesarios:

```txt
App.jsx
BarraLateral.jsx
GestionRoles.jsx
AuthController.php
```

Valor profesional:

```txt
Menor riesgo de regresión.
Integración limpia.
Merge más simple con ramas de otros desarrolladores.
```

## 17.4 Permisos granulares

Cada vista y acción visual se apoya en permisos específicos.

Valor profesional:

```txt
Mejora seguridad.
Permite separar perfiles.
Permite auditoría.
Evita mostrar acciones no autorizadas.
```

## 17.5 Toma física auditable

Se respetó el flujo profesional:

```txt
Crear no modifica stock.
Iniciar no modifica stock.
Contar no modifica stock.
Cerrar no modifica stock.
Solo ajustar modifica stock real.
```

Valor profesional:

```txt
Evita ajustes accidentales.
Mantiene trazabilidad.
Permite revisión antes de afectar stock.
Genera movimientos reales en Kardex.
```

## 17.6 Dashboard como herramienta demo

El dashboard permite visualizar rápidamente:

```txt
productos
bodegas
reservas
tomas abiertas
stock valorizado
últimos movimientos
tomas recientes
```

Valor profesional:

```txt
Facilita demos.
Facilita validación operativa.
Da visibilidad del estado del módulo.
Reduce dependencia de Postman.
```

---

# 18. Checklist final

```txt
[OK] Módulo frontend Inventario creado
[OK] Servicio inventarioApi.js creado
[OK] Componentes UI reutilizables creados
[OK] Dashboard Inventario creado
[OK] Vista Productos creada
[OK] Vista Bodegas creada
[OK] Vista Movimientos creada
[OK] Vista Kardex creada
[OK] Vista Lotes creada
[OK] Vista Reservas creada
[OK] Vista Tomas Físicas creada
[OK] Vista Valorización creada
[OK] Rutas React agregadas
[OK] Sidebar Inventario agregado
[OK] Permisos Inventario agregados a Gestión de Roles
[OK] AuthController ajustado para permisos runtime
[OK] Flujo toma física validado
[OK] Kardex validado
[OK] Dashboard validado
[OK] Reserva simple validada
[OK] Pulidos visuales aplicados
```

---

# 19. Comandos útiles

## Backend

```bash
cd Backend-laravel
php artisan serve
```

## Frontend

```bash
cd Frontend
npm run dev
```

## Limpiar cache Laravel

```bash
php artisan optimize:clear
```

---

# 20. Credenciales demo usadas

```txt
admin@tenri.cl
password123
```

---

# 21. Nota para merge con la rama del compañero

Si se integra esta fase en una rama más avanzada del ERP, se recomienda merge selectivo.

No reemplazar directamente:

```txt
routes/api.php
AuthController.php
App.jsx
BarraLateral.jsx
GestionRoles.jsx
```

Recomendación:

```txt
1. Copiar completo Frontend/src/Modulos/Inventario.
2. Fusionar manualmente imports y rutas en App.jsx.
3. Fusionar manualmente grupo Inventario en BarraLateral.jsx.
4. Fusionar manualmente permisos Inventario en GestionRoles.jsx.
5. Revisar AuthController.php para asegurar permisos runtime.
6. Validar login.
7. Validar sidebar.
8. Probar flujo completo de toma física.
```

---

# 22. Commit sugerido

Commit corto:

```txt
feat(inventario): agregar frontend demo operativo fase 7.5
```

Commit alternativo:

```txt
feat(inventario): integrar dashboard y vistas demo de inventario
```

Commit extendido:

```txt
feat(inventario): integrar frontend demo con dashboard, kardex, reservas y toma fisica
```

---

# 23. Estado final

La Fase 7.5 queda finalizada como frontend demo-operativo.

Flujo principal validado:

```txt
Producto → Bodega → Entrada → Kardex → Toma física → Conteo → Cierre → Ajuste → Kardex → Dashboard
```

Resultado:

```txt
Fase 7.5 lista para demo, README y commit.
```
