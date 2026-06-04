# Contrato HTTP de la API — Tenri ERP

Este documento fija la semántica de los códigos de estado HTTP que devuelve la API.
Es el contrato que consumen el frontend del ERP, `tenri.cl` y el panel admin.

## Códigos de estado

| Código | Significado | Cuándo se usa |
|--------|-------------|---------------|
| **200 / 201 / 202 / 204** | Éxito | `201` al crear recurso, `202` para procesos asíncronos (DTE), `204` sin cuerpo (revocar/eliminar). |
| **401** | No autenticado | Falta token Sanctum o es inválido. |
| **403** | Sin autorización | Empresa suspendida, usuario bloqueado, **permiso insuficiente** (`permiso:` middleware), suscripción `expired`. |
| **404** | No encontrado o sin acceso | El recurso no existe **o pertenece a otra empresa** (multitenant: para el usuario autenticado, un recurso ajeno simplemente no existe). |
| **419** | CSRF / sesión expirada | Sólo rutas web con sesión. |
| **422** | **Error de validación de entrada** | **Siempre** que falle una regla de `FormRequest`/`$request->validate()`. Nunca se devuelve 400 por validación. |
| **400** | **Regla de negocio / estado** | El recurso existe y la entrada es válida, pero la operación **no aplica** en el estado actual. |
| **429** | Rate limit | Throttle (`throttle:sii-empresa`, etc.). |
| **500** | Error inesperado | Fallo no controlado del servidor. |

## 422 vs 400 — la distinción clave

La diferencia es **el origen del error**, no su gravedad:

- **422 = la entrada está mal.** El payload no pasa validación (campo requerido ausente, tipo
  incorrecto, formato inválido, archivo no permitido). Lo arregla el cliente cambiando lo que envía.
  En código: la `ValidationException` se captura **antes** del `catch (Exception)` genérico.

  ```php
  try {
      $datos = $request->validate([...]);
      $this->service->hacer($datos);
  } catch (ValidationException $e) {
      return response()->json(['success' => false, 'errors' => $e->errors()], 422);
  } catch (Exception $e) {
      return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
  }
  ```

- **400 = la entrada está bien, pero la operación no.** El recurso existe, el payload es válido,
  pero una regla de negocio lo impide ("la factura ya fue anulada", "el mes contable está cerrado",
  "no se puede editar un activo dado de baja"). No lo arregla revalidar el payload: hay que cambiar
  el **estado** del recurso. Estos 400 son **intencionales** y forman parte del contrato — un
  consumidor puede distinguir "corrige tu formulario" (422) de "esta acción no procede ahora" (400).

> **Auditoría H3 (2026-06):** se catalogaron las ~50 respuestas 400 del backend. Sólo 5 eran
> degradaciones reales (validación que caía al `catch` genérico) y se normalizaron a 422 + 1 caso
> de mapeo inexistente que pasó a 404. El resto son errores de negocio legítimos y se documentan
> abajo: **no son bugs, son contrato.**

## Catálogo de errores de negocio (400 intencionales)

Estos endpoints devuelven 400 por **regla de estado**, no por validación. No cambiar sin revisar
el contrato con los consumidores.

| Dominio | Endpoint / método | Regla de negocio |
|---------|-------------------|------------------|
| Activos | `ActivoFijoService::editar` | No se puede editar un activo dado de baja. |
| Activos | `ActivoFijoService::eliminarProyecto` | Sólo se eliminan proyectos en construcción. |
| Activos | `ActivoFijoService::desvincular` | No se desvinculan facturas de un proyecto cerrado. |
| Comercial | `FacturaController::anular` | Factura ya anulada / con pagos aplicados en Tesorería. |
| Comercial | `FacturaController::reclasificarAsiento` | La factura no tiene asiento contable centralizado. |
| Comercial | `FacturaController::pagar` | El pago no procede por estado de la factura. |
| Comercial | `ClienteController` (activar/reactivar) | Transición de estado de cliente inválida. |
| Contabilidad | `ImpuestosController::simularF29` / `preCalculoRenta` | Período/datos no permiten el cálculo. |
| Corrección Monetaria | `CorreccionMonetariaController` (varios) | Mes cerrado, proceso no ejecutable, etc. |
| Tesorería | `ConciliacionController::anticiposPendientes` / `sugerencias` | Estado de conciliación no aplica. |
| Tesorería | `BancoController::cuentasEmpresa` | Sin cuentas / estado inválido. |
| Core | `EmpresaController` (update/config) | Regla de negocio de la empresa. |

> **Pendiente (diferido a H15, Fase 25):** `UsuarioController` devuelve 400 con mensajes genéricos
> ("Error al cargar usuarios/roles") que en realidad son errores **inesperados** (semántica 500).
> Se corrige al mover su RBAC manual a middleware declarativo, para no tocar el archivo dos veces.
