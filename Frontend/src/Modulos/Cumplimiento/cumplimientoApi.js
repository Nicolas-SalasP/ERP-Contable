import { api } from '../../Configuracion/api';

/**
 * Capa de servicio del módulo Cumplimiento / DPO.
 * Endpoints Fase 3 (auditoría PII) y Fase 6 (incidentes de seguridad).
 * Requieren permiso usuarios.gestionar.
 */
const cumplimientoApi = {
    // ── Auditoría PII (Ley 21.719 — Fase 3) ─────────────────────────────────
    auditoria: {
        listar: (params = {}) => api.get('/auditoria', { params }),
    },

    // ── Incidentes de Seguridad (Ley 21.663 / 21.719 — Fase 6) ──────────────
    incidentes: {
        listar: (params = {}) => api.get('/incidentes', { params }),
        crear: (payload) => api.post('/incidentes', payload),
        actualizar: (id, payload) => api.put(`/incidentes/${id}`, payload),
    },
};

export default cumplimientoApi;
