import { api } from '../../Configuracion/api';

/** Capa de servicio del motor de alertas de cumplimiento y gestion. */
const alertasApi = {
    listar: (params = {}, signal) => api.get('/alertas', { params, signal }),
    resolver: (id, estado) => api.patch(`/alertas/${id}`, { estado }),
};

export default alertasApi;
