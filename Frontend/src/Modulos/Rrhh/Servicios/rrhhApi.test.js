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

    it('empleados.obtener y eliminar arman la ruta con id', () => {
        rrhhApi.empleados.obtener(11);
        expect(api.get).toHaveBeenCalledWith('/rrhh/empleados/11');
        rrhhApi.empleados.eliminar(11);
        expect(api.delete).toHaveBeenCalledWith('/rrhh/empleados/11');
    });

    it('contratos.obtener, crear y agregarHaber', () => {
        rrhhApi.contratos.obtener(8);
        expect(api.get).toHaveBeenCalledWith('/rrhh/contratos/8');
        rrhhApi.contratos.crear(3, { cargo: 'Contador' });
        expect(api.post).toHaveBeenCalledWith('/rrhh/empleados/3/contratos', { cargo: 'Contador' });
        rrhhApi.contratos.agregarHaber(9, { nombre: 'Bono' });
        expect(api.post).toHaveBeenCalledWith('/rrhh/contratos/9/haberes', { nombre: 'Bono' });
    });

    it('liquidaciones.listar y obtener', () => {
        rrhhApi.liquidaciones.listar({ anio: 2026 });
        expect(api.get).toHaveBeenCalledWith('/rrhh/liquidaciones', { params: { anio: 2026 } });
        rrhhApi.liquidaciones.obtener(5);
        expect(api.get).toHaveBeenCalledWith('/rrhh/liquidaciones/5');
    });

    it('finiquitos.listar y obtener', () => {
        rrhhApi.finiquitos.listar();
        expect(api.get).toHaveBeenCalledWith('/rrhh/finiquitos', {});
        rrhhApi.finiquitos.obtener(4);
        expect(api.get).toHaveBeenCalledWith('/rrhh/finiquitos/4');
    });

    it('vacaciones.saldo y listar', () => {
        rrhhApi.vacaciones.saldo(1);
        expect(api.get).toHaveBeenCalledWith('/rrhh/vacaciones/saldo/1', {});
        rrhhApi.vacaciones.listar({ estado: 'PENDIENTE' });
        expect(api.get).toHaveBeenCalledWith('/rrhh/vacaciones/solicitudes', { params: { estado: 'PENDIENTE' } });
    });

    it('vacaciones.solicitar, aprobar, rechazar y anular', () => {
        rrhhApi.vacaciones.solicitar({ empleado_id: 1, dias: 5 });
        expect(api.post).toHaveBeenCalledWith('/rrhh/vacaciones/solicitudes', { empleado_id: 1, dias: 5 });
        rrhhApi.vacaciones.aprobar(2);
        expect(api.post).toHaveBeenCalledWith('/rrhh/vacaciones/solicitudes/2/aprobar');
        rrhhApi.vacaciones.rechazar(2, 'sin cobertura');
        expect(api.post).toHaveBeenCalledWith('/rrhh/vacaciones/solicitudes/2/rechazar', { motivo: 'sin cobertura' });
        rrhhApi.vacaciones.anular(2, 'cambio de fecha');
        expect(api.post).toHaveBeenCalledWith('/rrhh/vacaciones/solicitudes/2/anular', { motivo: 'cambio de fecha' });
    });

    it('parametros.listar y crear', () => {
        rrhhApi.parametros.listar();
        expect(api.get).toHaveBeenCalledWith('/rrhh/parametros');
        rrhhApi.parametros.crear({ nombre: 'UF' });
        expect(api.post).toHaveBeenCalledWith('/rrhh/parametros', { nombre: 'UF' });
    });

    it('indicadores.listar y crear', () => {
        rrhhApi.indicadores.listar();
        expect(api.get).toHaveBeenCalledWith('/rrhh/indicadores');
        rrhhApi.indicadores.crear({ nombre: 'UTM' });
        expect(api.post).toHaveBeenCalledWith('/rrhh/indicadores', { nombre: 'UTM' });
    });

    it('tablaImpuesto.listar con y sin año', () => {
        rrhhApi.tablaImpuesto.listar(2026);
        expect(api.get).toHaveBeenCalledWith('/rrhh/tabla-impuesto', { params: { anio: 2026 } });
        rrhhApi.tablaImpuesto.listar();
        expect(api.get).toHaveBeenCalledWith('/rrhh/tabla-impuesto', {});
    });

    it('mapeoContable.listar y guardar', () => {
        rrhhApi.mapeoContable.listar();
        expect(api.get).toHaveBeenCalledWith('/rrhh/mapeo-contable');
        rrhhApi.mapeoContable.guardar({ tipo: 'GASTO_REMUNERACIONES', cuenta_id: 10 });
        expect(api.post).toHaveBeenCalledWith('/rrhh/mapeo-contable', { tipo: 'GASTO_REMUNERACIONES', cuenta_id: 10 });
    });

    it('lre.listar, generar, validar y confirmarDt', () => {
        rrhhApi.lre.listar({ anio: 2026 });
        expect(api.get).toHaveBeenCalledWith('/rrhh/lre', { params: { anio: 2026 } });
        rrhhApi.lre.generar(2026, 5);
        expect(api.post).toHaveBeenCalledWith('/rrhh/lre/generar', { anio: 2026, mes: 5 });
        rrhhApi.lre.validar(7);
        expect(api.post).toHaveBeenCalledWith('/rrhh/lre/7/validar');
        rrhhApi.lre.confirmarDt(7, 'CONF-123');
        expect(api.post).toHaveBeenCalledWith('/rrhh/lre/7/confirmar-dt', { numero_confirmacion: 'CONF-123' });
    });

    it('lre.descargar pide el archivo con nombre de mes con cero a la izquierda', () => {
        rrhhApi.lre.descargar(7, 2026, 5);
        expect(api.download).toHaveBeenCalledWith('/rrhh/lre/7/descargar', 'LRE_2026_05.txt');
    });

    it('libroRemuneraciones.simular pega al endpoint mensual', () => {
        rrhhApi.libroRemuneraciones.simular(2026, 6);
        expect(api.get).toHaveBeenCalledWith('/rrhh/libro-remuneraciones/2026/6');
    });

    it('libroRemuneraciones.descargar usa excel por defecto y respeta pdf', () => {
        rrhhApi.libroRemuneraciones.descargar(2026, 6);
        expect(api.download).toHaveBeenCalledWith(
            '/rrhh/libro-remuneraciones/2026/6/descargar?formato=excel',
            'libro_remuneraciones_2026_06.xls',
        );
        rrhhApi.libroRemuneraciones.descargar(2026, 6, 'pdf');
        expect(api.download).toHaveBeenCalledWith(
            '/rrhh/libro-remuneraciones/2026/6/descargar?formato=pdf',
            'libro_remuneraciones_2026_06.pdf',
        );
    });
});
