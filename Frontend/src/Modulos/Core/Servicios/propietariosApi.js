import { api } from '../../../Configuracion/api';

export const propietariosApi = {
    listar: () => api.get('/empresa/propietarios').then(r => r.data),
    crear: (datos) => api.post('/empresa/propietarios', datos).then(r => r.data),
    actualizar: (id, datos) => api.put(`/empresa/propietarios/${id}`, datos).then(r => r.data),
    eliminar: (id) => api.delete(`/empresa/propietarios/${id}`).then(r => r.data),
};
