# Contrato Inter-Servicio — Módulo de Soporte (Tickets)
**Versión:** 1.0 · **Fecha:** 2026-06-17

## Arquitectura

`api.tenri.cl` (Tenri-Web-Page backend) es el **system of record** de tickets.
`admin.tenri.cl` (Tenri-Admin frontend) es un cliente via Sanctum — ya implementado.
`erp.tenri.cl` (ERP Contable) es un cliente via HMAC inter-servicio.

## Estado del ecosistema al 2026-06-17

| Componente | Estado |
|---|---|
| api.tenri.cl — Ticket domain (Ticket, TicketMessage, TicketService, StateMachine) | COMPLETO |
| api.tenri.cl — Endpoints usuario web (/tickets) y admin (/admin/tickets) | COMPLETO |
| admin.tenri.cl — UI bandeja soporte (TicketList, TicketThread, AssignModal, polling 8s) | COMPLETO |
| api.tenri.cl — Endpoints HMAC para ERP (/internal/erp/tickets) | PENDIENTE |
| ERP — SoporteClienteService + SoporteController + rutas + frontend Soporte | PENDIENTE |
| admin.tenri.cl — Badge origen ERP + empresa_nombre en bandeja | PENDIENTE |

## Decisiones

| Decisión | Valor |
|---|---|
| System of record | api.tenri.cl |
| Auth ERP → api.tenri.cl | HMAC-SHA256 existente (ERP_INTEGRATION_KEY / VerifyErpApiKey) |
| Polling vs websockets | Polling — ERP hace GET al abrir detalle (misma estrategia que Admin: cada 8s) |
| Email notificación v1 | Activo automático — api.tenri.cl ya tiene template ticket_reply |
| user_id en tickets ERP | NULL — ERP users no tienen cuenta web; se guardan origen_email y origen_nombre |
| Versionado | /internal/erp/tickets — separado de /tickets (sin /v1/ — patrón existente del repo) |

## Migración api.tenri.cl

```sql
-- Tabla tickets: agregar campos ERP, hacer user_id nullable
ALTER TABLE tickets
  ADD COLUMN origen        ENUM('WEB','ERP') NOT NULL DEFAULT 'WEB' AFTER ticket_code,
  ADD COLUMN empresa_id    BIGINT UNSIGNED NULL AFTER origen,
  ADD COLUMN empresa_nombre VARCHAR(255) NULL AFTER empresa_id,
  ADD COLUMN origen_email   VARCHAR(255) NULL AFTER empresa_nombre,
  ADD COLUMN origen_nombre  VARCHAR(255) NULL AFTER origen_email,
  MODIFY COLUMN user_id BIGINT UNSIGNED NULL,
  ADD INDEX idx_origen (origen),
  ADD INDEX idx_empresa_id (empresa_id);

-- Tabla ticket_messages: hacer user_id nullable (replies ERP no tienen user web)
ALTER TABLE ticket_messages
  MODIFY COLUMN user_id BIGINT UNSIGNED NULL;
```

## Endpoints nuevos en api.tenri.cl

Middleware: `erp.api.key` (VerifyErpApiKey — HMAC-SHA256, ya existe).

```
POST   /internal/erp/tickets              Crear ticket desde ERP
GET    /internal/erp/tickets              Listar tickets de la empresa (filtros: estado, categoria)
GET    /internal/erp/tickets/{id}         Detalle + hilo (guard: empresa_id coincide)
POST   /internal/erp/tickets/{id}/reply   Agregar mensaje como cliente ERP
```

### Payload POST /internal/erp/tickets

```json
{
  "empresa_id":    42,
  "empresa_nombre": "Mi Empresa SpA",
  "origen_email":  "usuario@empresa.cl",
  "origen_nombre": "Juan Pérez",
  "subject":       "Problema con asiento de cierre",
  "category":      "ERP",
  "priority":      "media",
  "message":       "Descripción detallada del problema..."
}
```

### Response — ticket (crear / detalle)

```json
{
  "id": 15,
  "ticket_code": "TK-A3F9KL",
  "origen": "ERP",
  "empresa_id": 42,
  "empresa_nombre": "Mi Empresa SpA",
  "origen_email": "usuario@empresa.cl",
  "origen_nombre": "Juan Pérez",
  "subject": "Problema con asiento de cierre",
  "category": "ERP",
  "priority": "media",
  "status": "nuevo",
  "status_label": "Nuevo",
  "assignee": null,
  "messages": [
    {
      "id": 1,
      "autor_tipo": "CLIENTE",
      "autor_nombre": "Juan Pérez",
      "autor_email": "usuario@empresa.cl",
      "message": "Descripción detallada...",
      "created_at": "2026-06-17T18:00:00Z"
    }
  ],
  "created_at": "2026-06-17T18:00:00Z",
  "updated_at": "2026-06-17T18:00:00Z"
}
```

### Payload POST /internal/erp/tickets/{id}/reply

```json
{
  "empresa_id":    42,
  "origen_email":  "usuario@empresa.cl",
  "origen_nombre": "Juan Pérez",
  "message":       "Adjunto el comprobante del período..."
}
```

## Endpoints ERP (proxy hacia api.tenri.cl, auth Sanctum)

```
GET    /api/soporte/tickets              Lista mis tickets (empresa_id del user autenticado)
POST   /api/soporte/tickets              Crear ticket
GET    /api/soporte/tickets/{id}         Detalle + hilo
POST   /api/soporte/tickets/{id}/reply   Responder
```

## Plan de implementación

**Fase 1** — api.tenri.cl:
- Migración (campos ERP + user_id nullable)
- ErpTicketController (4 endpoints)
- Extensiones TicketService (métodos ERP)
- TicketResource actualizado (campos ERP opcionales)
- Rutas bajo erp.api.key
- Tests (con mock HMAC)

**Fase 2A** — ERP (paralelo con 2B):
- SoporteClienteService (HTTP client HMAC → api.tenri.cl)
- SoporteController + rutas /api/soporte/*
- Frontend: src/Modulos/Soporte/ (lista, crear, detalle, responder)
- BarraLateral entrada "Soporte"

**Fase 2B** — admin.tenri.cl (paralelo con 2A):
- Badge origen (ERP/WEB) en TicketList y TicketThread
- Campo empresa_nombre visible cuando origen=ERP
- Filtro por origen en toolbar

**Fase 3** — smoke test end-to-end
