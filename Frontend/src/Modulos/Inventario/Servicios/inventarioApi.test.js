import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        download: vi.fn(),
    },
}));

import { inventarioApi } from './inventarioApi';
import { api } from '../../../Configuracion/api';

beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockResolvedValue({ success: true, data: [] });
    api.post.mockResolvedValue({ success: true, data: {} });
    api.put.mockResolvedValue({ success: true, data: {} });
    api.delete.mockResolvedValue({ success: true });
});

describe('inventarioApi — dashboard', () => {
    it('obtener llama GET /inventario/dashboard', async () => {
        await inventarioApi.dashboard.obtener();
        expect(api.get).toHaveBeenCalledWith('/inventario/dashboard');
    });
});

describe('inventarioApi — productos', () => {
    it('listar sin params llama GET /inventario/productos', async () => {
        await inventarioApi.productos.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/productos');
    });

    it('listar con params construye query string', async () => {
        await inventarioApi.productos.listar({ bodega_id: 1, estado: 'activo' });
        expect(api.get).toHaveBeenCalledWith('/inventario/productos?bodega_id=1&estado=activo');
    });

    it('crear llama POST /inventario/productos con payload', async () => {
        const payload = { nombre: 'Producto A', sku: 'SKU-001' };
        await inventarioApi.productos.crear(payload);
        expect(api.post).toHaveBeenCalledWith('/inventario/productos', payload);
    });

    it('obtener llama GET /inventario/productos/:id', async () => {
        await inventarioApi.productos.obtener(5);
        expect(api.get).toHaveBeenCalledWith('/inventario/productos/5');
    });

    it('actualizar llama PUT /inventario/productos/:id', async () => {
        const payload = { nombre: 'Producto B' };
        await inventarioApi.productos.actualizar(3, payload);
        expect(api.put).toHaveBeenCalledWith('/inventario/productos/3', payload);
    });

    it('kardex llama GET /inventario/productos/:id/kardex', async () => {
        await inventarioApi.productos.kardex(7);
        expect(api.get).toHaveBeenCalledWith('/inventario/productos/7/kardex');
    });

    it('valorizacion llama GET con params', async () => {
        await inventarioApi.productos.valorizacion(2, { metodo: 'pmp' });
        expect(api.get).toHaveBeenCalledWith('/inventario/productos/2/valorizacion?metodo=pmp');
    });
});

describe('inventarioApi — bodegas', () => {
    it('listar llama GET /inventario/bodegas', async () => {
        await inventarioApi.bodegas.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/bodegas');
    });

    it('crear llama POST /inventario/bodegas', async () => {
        const payload = { nombre: 'Bodega Central' };
        await inventarioApi.bodegas.crear(payload);
        expect(api.post).toHaveBeenCalledWith('/inventario/bodegas', payload);
    });
});

describe('inventarioApi — ubicaciones', () => {
    it('listar llama GET /inventario/ubicaciones', async () => {
        await inventarioApi.ubicaciones.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/ubicaciones');
    });

    it('stock llama GET /inventario/ubicaciones/:id/stock', async () => {
        await inventarioApi.ubicaciones.stock(3);
        expect(api.get).toHaveBeenCalledWith('/inventario/ubicaciones/3/stock');
    });
});

describe('inventarioApi — picking', () => {
    it('listar llama GET /inventario/picking', async () => {
        await inventarioApi.picking.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/picking');
    });

    it('asignar llama POST /inventario/picking/:id/asignar', async () => {
        await inventarioApi.picking.asignar(10);
        expect(api.post).toHaveBeenCalledWith('/inventario/picking/10/asignar', {});
    });

    it('iniciar llama POST /inventario/picking/:id/iniciar', async () => {
        await inventarioApi.picking.iniciar(10);
        expect(api.post).toHaveBeenCalledWith('/inventario/picking/10/iniciar', {});
    });

    it('confirmar llama POST /inventario/picking/:id/confirmar con payload', async () => {
        const payload = { lineas: [] };
        await inventarioApi.picking.confirmar(2, payload);
        expect(api.post).toHaveBeenCalledWith('/inventario/picking/2/confirmar', payload);
    });

    it('cancelar llama POST /inventario/picking/:id/cancelar', async () => {
        await inventarioApi.picking.cancelar(4);
        expect(api.post).toHaveBeenCalledWith('/inventario/picking/4/cancelar', {});
    });
});

describe('inventarioApi — packing', () => {
    it('iniciar llama POST /inventario/packing/:id/iniciar', async () => {
        await inventarioApi.packing.iniciar(1);
        expect(api.post).toHaveBeenCalledWith('/inventario/packing/1/iniciar', {});
    });

    it('confirmar llama POST /inventario/packing/:id/confirmar', async () => {
        await inventarioApi.packing.confirmar(1, { bultos: 2 });
        expect(api.post).toHaveBeenCalledWith('/inventario/packing/1/confirmar', { bultos: 2 });
    });

    it('cancelar llama POST /inventario/packing/:id/cancelar', async () => {
        await inventarioApi.packing.cancelar(1);
        expect(api.post).toHaveBeenCalledWith('/inventario/packing/1/cancelar', {});
    });
});

describe('inventarioApi — despachos', () => {
    it('listar llama GET /inventario/despachos', async () => {
        await inventarioApi.despachos.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/despachos');
    });

    it('iniciar llama POST /inventario/despachos/:id/iniciar', async () => {
        await inventarioApi.despachos.iniciar(5);
        expect(api.post).toHaveBeenCalledWith('/inventario/despachos/5/iniciar', {});
    });

    it('reversable llama GET /inventario/despachos/:id/reversable', async () => {
        await inventarioApi.despachos.reversable(9);
        expect(api.get).toHaveBeenCalledWith('/inventario/despachos/9/reversable');
    });
});

describe('inventarioApi — devoluciones', () => {
    it('listar llama GET /inventario/devoluciones', async () => {
        await inventarioApi.devoluciones.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/devoluciones');
    });

    it('confirmar llama POST /inventario/devoluciones/:id/confirmar', async () => {
        await inventarioApi.devoluciones.confirmar(3);
        expect(api.post).toHaveBeenCalledWith('/inventario/devoluciones/3/confirmar', {});
    });
});

describe('inventarioApi — lotes', () => {
    it('listar llama GET /inventario/lotes', async () => {
        await inventarioApi.lotes.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/lotes');
    });

    it('stock llama GET /inventario/lotes/:id/stock', async () => {
        await inventarioApi.lotes.stock(3);
        expect(api.get).toHaveBeenCalledWith('/inventario/lotes/3/stock');
    });

    it('actualizar llama PUT /inventario/lotes/:id', async () => {
        await inventarioApi.lotes.actualizar(3, { vencimiento: '2025-12-31' });
        expect(api.put).toHaveBeenCalledWith('/inventario/lotes/3', { vencimiento: '2025-12-31' });
    });
});

describe('inventarioApi — reservas', () => {
    it('crear llama POST /inventario/reservas', async () => {
        const payload = { producto_id: 1, cantidad: 5 };
        await inventarioApi.reservas.crear(payload);
        expect(api.post).toHaveBeenCalledWith('/inventario/reservas', payload);
    });

    it('cancelar llama POST /inventario/reservas/:id/cancelar', async () => {
        await inventarioApi.reservas.cancelar(2, { motivo: 'test' });
        expect(api.post).toHaveBeenCalledWith('/inventario/reservas/2/cancelar', { motivo: 'test' });
    });

    it('liberar llama POST /inventario/reservas/:id/liberar', async () => {
        await inventarioApi.reservas.liberar(2);
        expect(api.post).toHaveBeenCalledWith('/inventario/reservas/2/liberar', {});
    });

    it('consumir llama POST /inventario/reservas/:id/consumir', async () => {
        await inventarioApi.reservas.consumir(2);
        expect(api.post).toHaveBeenCalledWith('/inventario/reservas/2/consumir', {});
    });
});

describe('inventarioApi — movimientos', () => {
    it('registrar llama POST /inventario/movimientos', async () => {
        const payload = { tipo: 'entrada', cantidad: 10 };
        await inventarioApi.movimientos.registrar(payload);
        expect(api.post).toHaveBeenCalledWith('/inventario/movimientos', payload);
    });

    it('listar llama GET /inventario/movimientos', async () => {
        await inventarioApi.movimientos.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/movimientos');
    });
});

describe('inventarioApi — reglasReposicion', () => {
    it('eliminar llama DELETE /inventario/reglas-reposicion/:id', async () => {
        await inventarioApi.reglasReposicion.eliminar(5);
        expect(api.delete).toHaveBeenCalledWith('/inventario/reglas-reposicion/5');
    });

    it('actualizar llama PUT /inventario/reglas-reposicion/:id', async () => {
        await inventarioApi.reglasReposicion.actualizar(5, { punto_reorden: 10 });
        expect(api.put).toHaveBeenCalledWith('/inventario/reglas-reposicion/5', { punto_reorden: 10 });
    });
});

describe('inventarioApi — tomasFisicas', () => {
    it('iniciar llama POST /inventario/tomas-fisicas/:id/iniciar', async () => {
        await inventarioApi.tomasFisicas.iniciar(1);
        expect(api.post).toHaveBeenCalledWith('/inventario/tomas-fisicas/1/iniciar', {});
    });

    it('registrarConteos llama POST /inventario/tomas-fisicas/:id/conteos', async () => {
        const payload = { conteos: [] };
        await inventarioApi.tomasFisicas.registrarConteos(1, payload);
        expect(api.post).toHaveBeenCalledWith('/inventario/tomas-fisicas/1/conteos', payload);
    });

    it('cerrar llama POST /inventario/tomas-fisicas/:id/cerrar', async () => {
        await inventarioApi.tomasFisicas.cerrar(1);
        expect(api.post).toHaveBeenCalledWith('/inventario/tomas-fisicas/1/cerrar', {});
    });

    it('ajustar llama POST /inventario/tomas-fisicas/:id/ajustar', async () => {
        await inventarioApi.tomasFisicas.ajustar(1, { ajuste: true });
        expect(api.post).toHaveBeenCalledWith('/inventario/tomas-fisicas/1/ajustar', { ajuste: true });
    });

    it('cancelar llama POST /inventario/tomas-fisicas/:id/cancelar', async () => {
        await inventarioApi.tomasFisicas.cancelar(1);
        expect(api.post).toHaveBeenCalledWith('/inventario/tomas-fisicas/1/cancelar', {});
    });
});

describe('inventarioApi — normalize', () => {
    it('retorna defaults cuando la respuesta está vacía', async () => {
        api.get.mockResolvedValue({});
        const result = await inventarioApi.dashboard.obtener();
        expect(result.success).toBe(true);
        expect(result.data).toEqual([]);
        expect(result.pagination).toBeNull();
        expect(result.resumen).toBeNull();
        expect(result.metadata).toBeNull();
        expect(result.message).toBeNull();
        expect(result.errors).toBeNull();
    });

    it('retorna datos del response cuando existen', async () => {
        api.get.mockResolvedValue({ success: true, data: [{ id: 1 }], pagination: { page: 1 } });
        const result = await inventarioApi.productos.listar();
        expect(result.data).toEqual([{ id: 1 }]);
        expect(result.pagination).toEqual({ page: 1 });
    });
});

describe('inventarioApi — catalogos y alertas', () => {
    it('catalogos llama GET /inventario/catalogos', async () => {
        await inventarioApi.catalogos();
        expect(api.get).toHaveBeenCalledWith('/inventario/catalogos');
    });

    it('alertas.listar llama GET /inventario/alertas', async () => {
        await inventarioApi.alertas.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/alertas');
    });

    it('reposicion.sugerencias llama GET /inventario/reposicion/sugerencias', async () => {
        await inventarioApi.reposicion.sugerencias();
        expect(api.get).toHaveBeenCalledWith('/inventario/reposicion/sugerencias');
    });
});

describe('inventarioApi — reportes', () => {
    it('reportes.stock llama GET /inventario/reportes/stock', async () => {
        await inventarioApi.reportes.stock();
        expect(api.get).toHaveBeenCalledWith('/inventario/reportes/stock');
    });

    it('reportes.movimientos llama GET /inventario/reportes/movimientos', async () => {
        await inventarioApi.reportes.movimientos();
        expect(api.get).toHaveBeenCalledWith('/inventario/reportes/movimientos');
    });

    it('reportes.exportarCsvUrl genera URL correcta sin params', () => {
        const url = inventarioApi.reportes.exportarCsvUrl('stock');
        expect(url).toBe('/inventario/reportes/stock/exportar-csv');
    });

    it('reportes.exportarCsvUrl genera URL con query string', () => {
        const url = inventarioApi.reportes.exportarCsvUrl('stock', { bodega_id: 1 });
        expect(url).toBe('/inventario/reportes/stock/exportar-csv?bodega_id=1');
    });
});

describe('inventarioApi — eventosIntegracion', () => {
    it('listar llama GET /inventario/eventos-integracion', async () => {
        await inventarioApi.eventosIntegracion.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/eventos-integracion');
    });

    it('procesar llama POST /inventario/eventos-integracion/:id/procesar', async () => {
        await inventarioApi.eventosIntegracion.procesar(3);
        expect(api.post).toHaveBeenCalledWith('/inventario/eventos-integracion/3/procesar', {});
    });
});

describe('inventarioApi — toQuery filtra valores vacíos', () => {
    it('ignora params null y undefined', async () => {
        await inventarioApi.productos.listar({ activo: null, estado: undefined, bodega_id: 1 });
        expect(api.get).toHaveBeenCalledWith('/inventario/productos?bodega_id=1');
    });

    it('ignora params string vacíos', async () => {
        await inventarioApi.productos.listar({ nombre: '', codigo: 'ABC' });
        expect(api.get).toHaveBeenCalledWith('/inventario/productos?codigo=ABC');
    });
});

describe('inventarioApi — stockUbicaciones y putaway', () => {
    it('stockUbicaciones.listar llama GET /inventario/stock-ubicaciones', async () => {
        await inventarioApi.stockUbicaciones.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/stock-ubicaciones');
    });

    it('stockUbicaciones.mover llama POST /inventario/stock-ubicaciones/mover', async () => {
        const payload = { desde: 1, hacia: 2, cantidad: 5 };
        await inventarioApi.stockUbicaciones.mover(payload);
        expect(api.post).toHaveBeenCalledWith('/inventario/stock-ubicaciones/mover', payload);
    });

    it('putaway.confirmar llama POST /inventario/putaway/confirmar', async () => {
        const payload = { ubicacion_id: 3, cantidad: 10 };
        await inventarioApi.putaway.confirmar(payload);
        expect(api.post).toHaveBeenCalledWith('/inventario/putaway/confirmar', payload);
    });
});

describe('inventarioApi — kardex y valorizacion', () => {
    it('kardex.listar llama GET /inventario/kardex', async () => {
        await inventarioApi.kardex.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/kardex');
    });

    it('valorizacion.listar llama GET /inventario/valorizacion', async () => {
        await inventarioApi.valorizacion.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/valorizacion');
    });
});

describe('inventarioApi — disponibilidad y auditoria', () => {
    it('disponibilidad.listar llama GET /inventario/disponibilidad', async () => {
        await inventarioApi.disponibilidad.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/disponibilidad');
    });

    it('disponibilidad.producto llama GET /inventario/productos/:id/disponibilidad', async () => {
        await inventarioApi.disponibilidad.producto(4);
        expect(api.get).toHaveBeenCalledWith('/inventario/productos/4/disponibilidad');
    });

    it('auditoria.listar llama GET /inventario/auditoria', async () => {
        await inventarioApi.auditoria.listar();
        expect(api.get).toHaveBeenCalledWith('/inventario/auditoria');
    });

    it('auditoria.obtener llama GET /inventario/auditoria/:id', async () => {
        await inventarioApi.auditoria.obtener(7);
        expect(api.get).toHaveBeenCalledWith('/inventario/auditoria/7');
    });

    it('auditoria.resumen llama GET /inventario/auditoria/resumen', async () => {
        await inventarioApi.auditoria.resumen();
        expect(api.get).toHaveBeenCalledWith('/inventario/auditoria/resumen');
    });
});

describe('inventarioApi — devoluciones endpoints restantes', () => {
    it('devoluciones.reporte llama GET /inventario/reportes/devoluciones', async () => {
        await inventarioApi.devoluciones.reporte();
        expect(api.get).toHaveBeenCalledWith('/inventario/reportes/devoluciones');
    });

    it('devoluciones.cancelar llama POST /inventario/devoluciones/:id/cancelar', async () => {
        await inventarioApi.devoluciones.cancelar(5);
        expect(api.post).toHaveBeenCalledWith('/inventario/devoluciones/5/cancelar', {});
    });
});

describe('inventarioApi — eventosIntegracion endpoints restantes', () => {
    it('eventosIntegracion.obtener llama GET /inventario/eventos-integracion/:id', async () => {
        await inventarioApi.eventosIntegracion.obtener(2);
        expect(api.get).toHaveBeenCalledWith('/inventario/eventos-integracion/2');
    });

    it('eventosIntegracion.resumen llama GET /inventario/eventos-integracion/resumen', async () => {
        await inventarioApi.eventosIntegracion.resumen();
        expect(api.get).toHaveBeenCalledWith('/inventario/eventos-integracion/resumen');
    });

    it('eventosIntegracion.ignorar llama POST /inventario/eventos-integracion/:id/ignorar', async () => {
        await inventarioApi.eventosIntegracion.ignorar(3, { razon: 'test' });
        expect(api.post).toHaveBeenCalledWith('/inventario/eventos-integracion/3/ignorar', { razon: 'test' });
    });
});
