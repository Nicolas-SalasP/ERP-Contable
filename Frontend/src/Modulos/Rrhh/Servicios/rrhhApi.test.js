import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(() => Promise.resolve({ success: true, data: [] })),
        post: vi.fn(() => Promise.resolve({ success: true })),
        put: vi.fn(() => Promise.resolve({ success: true })),
        delete: vi.fn(() => Promise.resolve({ success: true })),
        download: vi.fn(() => Promise.resolve({ success: true })),
    },
}));

import rrhhApi from './rrhhApi';
import { api } from '../../../Configuracion/api';

beforeEach(() => {
    vi.clearAllMocks();
});

describe('rrhhApi', () => {
    it('empleados.listar pega a /rrhh/empleados con params', () => {
        rrhhApi.empleados.listar({ buscar: 'ana' });
        expect(api.get).toHaveBeenCalledWith('/rrhh/empleados', { params: { buscar: 'ana' } });
    });

    it('empleados.crear y actualizar usan POST/PUT', () => {
        rrhhApi.empleados.crear({ rut: '1-9' });
        expect(api.post).toHaveBeenCalledWith('/rrhh/empleados', { rut: '1-9' });
        rrhhApi.empleados.actualizar(7, { estado: 'INACTIVO' });
        expect(api.put).toHaveBeenCalledWith('/rrhh/empleados/7', { estado: 'INACTIVO' });
    });

    it('contratos.listarPorEmpleado y terminar arman bien la ruta', () => {
        rrhhApi.contratos.listarPorEmpleado(3);
        expect(api.get).toHaveBeenCalledWith('/rrhh/empleados/3/contratos', {});
        rrhhApi.contratos.terminar(9, { causal_termino: 'RENUNCIA' });
        expect(api.post).toHaveBeenCalledWith('/rrhh/contratos/9/terminar', { causal_termino: 'RENUNCIA' });
    });

    it('liquidaciones expone calcular, emitir y anular', () => {
        rrhhApi.liquidaciones.calcular({ empleado_id: 1, anio: 2026, mes: 6 });
        expect(api.post).toHaveBeenCalledWith('/rrhh/liquidaciones/calcular', { empleado_id: 1, anio: 2026, mes: 6 });
        rrhhApi.liquidaciones.emitir(5);
        expect(api.post).toHaveBeenCalledWith('/rrhh/liquidaciones/5/emitir');
        rrhhApi.liquidaciones.anular(5);
        expect(api.post).toHaveBeenCalledWith('/rrhh/liquidaciones/5/anular');
    });

    it('finiquitos.calcular y firmar', () => {
        rrhhApi.finiquitos.calcular({ contrato_id: 2, causal: 'NECESIDADES_EMPRESA' });
        expect(api.post).toHaveBeenCalledWith('/rrhh/finiquitos/calcular', { contrato_id: 2, causal: 'NECESIDADES_EMPRESA' });
        rrhhApi.finiquitos.firmar(4);
        expect(api.post).toHaveBeenCalledWith('/rrhh/finiquitos/4/firmar');
    });

    it('mapeoContable.eliminar usa DELETE con el tipo', () => {
        rrhhApi.mapeoContable.eliminar('GASTO_REMUNERACIONES');
        expect(api.delete).toHaveBeenCalledWith('/rrhh/mapeo-contable/GASTO_REMUNERACIONES');
    });

    it('centralizacion.ejecutar arma /rrhh/centralizacion/{anio}/{mes}', () => {
        rrhhApi.centralizacion.ejecutar(2026, 3);
        expect(api.post).toHaveBeenCalledWith('/rrhh/centralizacion/2026/3');
    });

    it('previred.descargar pide el archivo con nombre de mes con cero a la izquierda', () => {
        rrhhApi.previred.descargar(2026, 6);
        expect(api.download).toHaveBeenCalledWith('/rrhh/previred/2026/6/archivo', 'previred_2026_06.csv');
    });

    it('previred.previsualizar pega al endpoint preview', () => {
        rrhhApi.previred.previsualizar(2026, 6);
        expect(api.get).toHaveBeenCalledWith('/rrhh/previred/2026/6/preview');
    });
});
