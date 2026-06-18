# Diseño — Plan MultiTenant: cambio de empresa sin cerrar sesión
**Versión:** 1.0 · **Fecha:** 2026-06-17 · **Riesgo:** ALTO (toca cimiento de aislamiento)

---

## Estado actual (premisa que esta épica modifica)

| Elemento | Estado actual |
|---|---|
| `EmpresaScope` | Lee `auth()->user()->empresa_id` — una columna, un tenant |
| `usuarios.empresa_id` | FK a empresas, columna del aislamiento actual |
| `usuarios.rol_id` | Un único rol por usuario, sin distinción por empresa |
| `module_keys` | Array JSON en usuarios, provisionado desde tenri.cl |
| Sanctum tokens | Opacos — no llevan metadata de empresa en payload |

Modelos con `HasEmpresaScope`: 62. Todos filtran por `empresa_id`. Este es el cimiento.

---

## Decisiones de diseño

### 1. Modelo de pertenencia — tabla pivote `empresa_user`

```sql
empresa_user (
  id         BIGINT UNSIGNED AUTO_INCREMENT PK,
  user_id    BIGINT UNSIGNED NOT NULL,
  empresa_id BIGINT UNSIGNED NOT NULL,
  rol_id     BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL,
  UNIQUE (user_id, empresa_id),
  FK user_id    → usuarios(id) ON DELETE CASCADE,
  FK empresa_id → empresas(id) ON DELETE CASCADE,
  FK rol_id     → roles(id)
)
```

**Data migration**: cada `usuarios.empresa_id` existente genera una fila pivote con `rol_id` del usuario. Sin pérdida. Todo usuario queda con exactamente una fila en el pivote tras la migración.

**`usuarios.empresa_id` se conserva**: empresa original/provisionada. No se elimina. Sirve como fallback de compatibilidad.

### 2. Resolución de empresa activa — columna server-side

**Estrategia**: columna `empresa_activa_id BIGINT UNSIGNED NULL FK→empresas` en `usuarios`.

**Data migration**: `UPDATE usuarios SET empresa_activa_id = empresa_id` — todos los usuarios existentes quedan con su empresa actual como activa. Ninguno queda sin empresa activa.

**Por qué columna y no token/header**:
- Sanctum tokens son opacos — solo una hash en `personal_access_tokens`, sin metadata en payload. Agregar metadata implicaría revocar/reemitir en cada cambio.
- Header/body: el cliente puede manipularlos. Explícitamente prohibido.
- Columna server-side: el cliente no la controla. `POST /empresa/cambiar` la actualiza solo tras validar pertenencia en el pivote.

**EmpresaScope** — único cambio (una línea):
```php
// Antes:
$empresaId = auth()->user()->empresa_id;

// Después:
$empresaId = auth()->user()->empresa_activa_id ?? auth()->user()->empresa_id;
```

El fallback `?? empresa_id` garantiza:
- Compatibilidad durante el período de migración (empresa_activa_id aún null)
- Usuarios que nunca usan multitenant siguen funcionando igual
- Fail-safe: si empresa_activa_id es null y empresa_id también es null → `whereRaw('1 = 0')` (código existente)

### 3. RBAC por empresa

`usuarios.rol_id` es el rol activo. Al cambiar de empresa, se actualiza `rol_id` al rol de la fila pivote de la empresa destino.

**Transacción de cambio de empresa** (atómica):
```sql
BEGIN;
  UPDATE usuarios
  SET empresa_activa_id = :nueva_empresa_id,
      rol_id            = (SELECT rol_id FROM empresa_user
                           WHERE user_id = :uid AND empresa_id = :nueva_empresa_id)
  WHERE id = :uid;
COMMIT;
```

Resultado: `ModuloPermisos::permisosUsuario`, middleware `permiso:`, endpoint `me` y toda la capa de autorización leen `user->rol_id` sin cambios.

### 4. Gate del plan MultiTenant

Condición: `in_array('multitenant', $user->module_keys ?? [])`.

El módulo `'multitenant'` en `module_keys` lo provisionan desde tenri.cl (mismo canal que todos los módulos de plan). El ERP no gestiona qué empresas tiene el usuario — eso es responsabilidad de Tenri-Admin.

**Comportamiento por gate**:

| Condición | `GET /empresa/mis-empresas` | `POST /empresa/cambiar` |
|---|---|---|
| Sin plan multitenant | Retorna lista de 1 empresa (sin error) | 403 |
| Con plan multitenant | Retorna todas las empresas del pivote | Permitido si pertenece |
| Empresa no pertenece | N/A | 403 |

Alta de empresa adicional: fuera del scope del ERP. La gestión del pivote la hace Tenri-Admin vía el canal HMAC existente (o un endpoint nuevo en `/internal/web/`).

### 5. Endpoints nuevos

```
GET  /api/empresa/mis-empresas   → lista empresas del pivote (con rol por empresa)
POST /api/empresa/cambiar        → body: { empresa_id: int } → valida + actualiza server-side
```

El `empresa_id` en el body de `cambiar` es el destino solicitado por el cliente. El servidor valida que esté en el pivote antes de actualizar `empresa_activa_id`. El scope NUNCA lee ese body — solo lee `user->empresa_activa_id` después de la actualización.

### 6. Frontend — selector de empresa

- Visible solo si el usuario tiene >1 empresa (comprueba `mis-empresas.length > 1`)
- Al cambiar: llama `POST /empresa/cambiar`, espera 200, llama `GET /me` (actualiza rol/permisos), invalida cache de React Query, recarga la vista actual
- Muestra empresa activa siempre visible en la barra superior
- No cachea permisos entre cambios de empresa

---

## Invariantes de seguridad no negociables

| Invariante | Mecanismo |
|---|---|
| EmpresaScope nunca lee empresa del cliente | Lee `auth()->user()->empresa_activa_id` — objeto del servidor, no del request |
| Cambio a empresa ajena → 403 | Verifica en pivote ANTES del UPDATE |
| Sin empresa → sin datos (fail-closed) | `whereRaw('1 = 0')` — sin cambios |
| Rol correcto tras cambio | `rol_id` actualizado en misma transacción que `empresa_activa_id` |
| Un request no mezcla datos de dos empresas | EmpresaScope se aplica en boot — único punto, único valor |
| Concurrencia | UPDATE atómico en DB — no hay race condition a nivel de scope |

---

## Plan de fases

| Fase | Responsable | Descripción | Gate |
|---|---|---|---|
| 0 | Orquestador | Diseño (este documento) | Aprobado |
| 1 | 1 agente | Migración pivote + empresa_activa_id + User model + tests | Tests verdes |
| 2 | 1 agente | EmpresaScope + tests de aislamiento (5 tests) | Aislamiento verificado |
| 3A | 1 agente | Endpoints cambiar/mis-empresas + gate plan | Tests verdes |
| 3B | 1 agente | Frontend selector + rol efectivo | UI funcional |
| 4 | Orquestador | Auditoría manual end-to-end | Criterios checklist |

---

## Tests obligatorios (referencia)

### Fase 1
- `test_usuario_existente_conserva_su_empresa_tras_migracion`
- `test_usuario_puede_pertenecer_a_varias_empresas`
- `test_empresa_activa_por_defecto_es_la_original`

### Fase 2 — CORAZÓN DE LA ÉPICA
- `test_usuario_multiempresa_solo_ve_datos_de_empresa_activa`
- `test_cambio_de_empresa_cambia_los_datos_visibles`
- `test_usuario_no_puede_activar_empresa_que_no_le_pertenece`
- `test_usuario_una_empresa_comportamiento_intacto`
- `test_scope_no_lee_empresa_de_input_del_cliente`

### Fase 3
- `test_cambio_empresa_exitoso`
- `test_cambio_empresa_ajena_403`
- `test_cambio_empresa_sin_plan_403`
- `test_listar_mis_empresas`
