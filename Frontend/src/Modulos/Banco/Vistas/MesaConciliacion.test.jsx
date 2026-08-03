import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import MesaConciliacion from './MesaConciliacion';
import { api } from '../../../Configuracion/api';

const swalMock = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
    showLoading: vi.fn(),
    close: vi.fn(),
}));
vi.mock('sweetalert2', () => ({ default: swalMock }));

const cuentaBanco = { id: 1, banco: 'Banco Test', numero_cuenta: '12345', cuenta_contable: '110100' };
const movimiento = { id: 10, fecha: '2026-05-01', descripcion: 'PAGO PROVEEDOR ACME', cargo: 119000, abono: 0 };

// Rutas base comunes. El orden importa: setupFetchRouter matchea por url.includes,
// asi que las rutas mas especificas (cuentas-imputables) van antes que /banco/cuentas.
const rutasBase = (pendientesHandler, overrides = {}) => ({
    'GET /banco/cuentas-imputables': () => mockJsonResponse(200, { success: true, data: [] }),
    'GET /banco/anticipos-pendientes': () => mockJsonResponse(200, { success: true, data: [] }),
    'GET /banco/cuentas': () => mockJsonResponse(200, { success: true, data: [cuentaBanco] }),
    'GET /empresas/centros-costo': () => mockJsonResponse(200, { success: true, data: [] }),
    'GET /banco/movimientos/pendientes': pendientesHandler,
    'GET /banco/movimientos/descartados': () => mockJsonResponse(200, { success: true, data: [] }),
    ...overrides,
});

describe('MesaConciliacion', () => {
    beforeEach(() => {
        cleanTestEnv();
        api.config({ showErrorToast: false });
        swalMock.fire.mockClear();
    });
    afterEach(() => {
        cleanup();
        vi.clearAllMocks();
    });

    it('carga y muestra la lista de movimientos pendientes', async () => {
        setupFetchRouter(rutasBase(() => mockJsonResponse(200, { success: true, data: [movimiento] })));

        render(<MesaConciliacion />);

        expect(await screen.findByText('PAGO PROVEEDOR ACME')).toBeDefined();
        expect(screen.getByText('Movimientos Pendientes')).toBeDefined();
    });

    it('sin movimientos pendientes muestra el estado "Banco 100% Cuadrado"', async () => {
        setupFetchRouter(rutasBase(() => mockJsonResponse(200, { success: true, data: [] })));

        render(<MesaConciliacion />);

        expect(await screen.findByText(/Banco 100% Cuadrado/i)).toBeDefined();
    });

    it('conciliar un movimiento via sugerencia lo quita de pendientes', async () => {
        let llamadasPendientes = 0;
        const pendientesHandler = () => {
            llamadasPendientes += 1;
            // Primera carga: el movimiento esta pendiente. Tras conciliar: ya no.
            return mockJsonResponse(200, { success: true, data: llamadasPendientes === 1 ? [movimiento] : [] });
        };

        const conciliarSpy = vi.fn(() => mockJsonResponse(200, { success: true, asiento: { numero_comprobante: 'COMP-77' } }));

        setupFetchRouter(rutasBase(pendientesHandler, {
            'GET /banco/movimientos/10/sugerencias': () => mockJsonResponse(200, {
                success: true,
                data: [{ id: 5, numero_factura: 'F-100', proveedor: { razon_social: 'ACME SpA' }, monto_bruto: 119000 }],
            }),
            'POST /banco/movimientos/conciliar-facturas': conciliarSpy,
        }));

        render(<MesaConciliacion />);

        fireEvent.click(await screen.findByRole('button', { name: /Conciliar/i }));

        // El modal abre y la sugerencia (match exacto) habilita "Aprobar Pago Exacto".
        const aprobar = await screen.findByRole('button', { name: /Aprobar Pago Exacto/i });
        fireEvent.click(aprobar);

        await waitFor(() => expect(conciliarSpy).toHaveBeenCalled());
        expect(await screen.findByText(/Banco 100% Cuadrado/i)).toBeDefined();
    });

    it('descartar un movimiento (tras confirmar) lo quita de pendientes', async () => {
        let llamadasPendientes = 0;
        const pendientesHandler = () => {
            llamadasPendientes += 1;
            return mockJsonResponse(200, { success: true, data: llamadasPendientes === 1 ? [movimiento] : [] });
        };

        const descartarSpy = vi.fn(() => mockJsonResponse(200, { success: true, mensaje: 'Movimiento descartado.' }));

        setupFetchRouter(rutasBase(pendientesHandler, {
            'POST /banco/movimientos/10/descartar': descartarSpy,
        }));

        render(<MesaConciliacion />);

        fireEvent.click(await screen.findByTitle(/Descartar/i));

        await waitFor(() => expect(descartarSpy).toHaveBeenCalled());
        expect(await screen.findByText(/Banco 100% Cuadrado/i)).toBeDefined();
    });

    it('descartar cancelado en el confirm no llama al backend', async () => {
        swalMock.fire.mockResolvedValueOnce({ isConfirmed: false });

        const descartarSpy = vi.fn(() => mockJsonResponse(200, { success: true }));

        setupFetchRouter(rutasBase(() => mockJsonResponse(200, { success: true, data: [movimiento] }), {
            'POST /banco/movimientos/10/descartar': descartarSpy,
        }));

        render(<MesaConciliacion />);

        fireEvent.click(await screen.findByTitle(/Descartar/i));

        await waitFor(() => expect(swalMock.fire).toHaveBeenCalled());
        expect(descartarSpy).not.toHaveBeenCalled();
    });

    it('conciliar un movimiento contra un anticipo de proveedor pendiente', async () => {
        let llamadasPendientes = 0;
        const pendientesHandler = () => {
            llamadasPendientes += 1;
            return mockJsonResponse(200, { success: true, data: llamadasPendientes === 1 ? [movimiento] : [] });
        };

        const conciliarAnticipoSpy = vi.fn(() => mockJsonResponse(200, { success: true, mensaje: 'Anticipo conciliado correctamente.' }));

        setupFetchRouter(rutasBase(pendientesHandler, {
            'GET /banco/anticipos-pendientes': () => mockJsonResponse(200, {
                success: true,
                data: [{ id: 7, monto: 119000, referencia: 'OC-8821', tipo: 'proveedor', entidad_nombre: 'Proveedor Anticipo Test' }],
            }),
            'POST /banco/movimientos/conciliar-anticipo': conciliarAnticipoSpy,
        }));

        render(<MesaConciliacion />);

        fireEvent.click(await screen.findByRole('button', { name: /Conciliar/i }));
        fireEvent.click(await screen.findByRole('button', { name: /^Anticipo$/i }));

        const placeholder = await screen.findByText(/Busca la solicitud de anticipo/i);
        const input = placeholder.closest('div').parentElement.querySelector('input');
        fireEvent.keyDown(input, { key: 'ArrowDown', code: 'ArrowDown' });
        fireEvent.click(await screen.findByText(/Proveedor Anticipo Test/i));

        const aprobar = await screen.findByRole('button', { name: /Vincular Anticipo/i });
        fireEvent.click(aprobar);

        await waitFor(() => expect(conciliarAnticipoSpy).toHaveBeenCalled());
        const [body] = conciliarAnticipoSpy.mock.calls[0];
        expect(body).toMatchObject({ movimiento_id: 10, anticipo_id: 7, tipo: 'proveedor' });

        expect(await screen.findByText(/Banco 100% Cuadrado/i)).toBeDefined();
    });

    it('pestaña Descartados muestra los movimientos descartados y permite restaurar', async () => {
        const movDescartado = { id: 20, fecha: '2026-05-02', descripcion: 'LINEA DUPLICADA CARTOLA', cargo: 5000, abono: 0 };
        const restaurarSpy = vi.fn(() => mockJsonResponse(200, { success: true, mensaje: 'Movimiento restaurado a pendientes.' }));

        setupFetchRouter(rutasBase(() => mockJsonResponse(200, { success: true, data: [] }), {
            'GET /banco/movimientos/descartados': () => mockJsonResponse(200, { success: true, data: [movDescartado] }),
            'POST /banco/movimientos/20/restaurar': restaurarSpy,
        }));

        render(<MesaConciliacion />);

        await screen.findByText(/Banco 100% Cuadrado/i);

        fireEvent.click(screen.getByRole('button', { name: /Descartados/i }));

        expect(await screen.findByText('LINEA DUPLICADA CARTOLA')).toBeDefined();

        fireEvent.click(screen.getByRole('button', { name: /Restaurar/i }));

        await waitFor(() => expect(restaurarSpy).toHaveBeenCalled());
    });
});
