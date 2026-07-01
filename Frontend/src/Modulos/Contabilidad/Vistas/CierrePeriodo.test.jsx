import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import CierrePeriodo from './CierrePeriodo';

// ─── Mock del contexto de permisos ────────────────────────────────────────────
vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: vi.fn(),
}));

import { usePermisos } from '../../../Contextos/Permisos';

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const PERIODOS_VACIOS = { success: true, data: [] };

const PERIODOS_CON_CERRADOS = {
    success: true,
    data: [
        {
            id: 1,
            anio: 2026,
            mes: 3,
            estado: 'CERRADO',
            motivo: 'Cierre trimestral',
            cerrado_at: '2026-04-01T12:00:00.000000Z',
            cerrado_por: { id: 1, name: 'Admin Test' },
            reabierto_por: null,
        },
        {
            id: 2,
            anio: 2026,
            mes: 5,
            estado: 'CERRADO',
            motivo: null,
            cerrado_at: '2026-06-01T10:00:00.000000Z',
            cerrado_por: { id: 1, name: 'Admin Test' },
            reabierto_por: null,
        },
    ],
};

const RESPUESTA_CIERRE_OK = {
    success: true,
    message: 'Periodo cerrado correctamente.',
    data: { id: 3, anio: 2026, mes: 1, estado: 'CERRADO' },
};

const RESPUESTA_REAPERTURA_OK = {
    success: true,
    message: 'Periodo reabierto correctamente.',
    data: { id: 1, anio: 2026, mes: 3, estado: 'ABIERTO' },
};

// ─── Setup / teardown ─────────────────────────────────────────────────────────
beforeEach(() => {
    vi.mocked(usePermisos).mockImplementation(() => ({ tienePermiso: () => true }));
    cleanTestEnv();
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function montarConPeriodos(periodos = PERIODOS_VACIOS) {
    setupFetchRouter({
        'GET /contabilidad/periodos': () => mockJsonResponse(200, periodos),
    });
    return renderWithRouter(<CierrePeriodo />);
}

// ─── Tests de render ──────────────────────────────────────────────────────────
describe('CierrePeriodo — render', () => {
    it('muestra el título del módulo', async () => {
        montarConPeriodos();
        await waitFor(() =>
            expect(screen.getByText(/Cierre de Períodos Contables/i)).toBeTruthy()
        );
    });

    it('muestra los 12 meses del año', async () => {
        montarConPeriodos();
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        await waitFor(() => {
            meses.forEach(mes => {
                expect(screen.getAllByText(mes).length).toBeGreaterThan(0);
            });
        });
    });

    it('muestra el año actual en el selector', async () => {
        montarConPeriodos();
        const anio = new Date().getFullYear().toString();
        await waitFor(() => expect(screen.getByText(anio)).toBeTruthy());
    });

    it('muestra badge Abierto cuando no hay períodos cerrados', async () => {
        montarConPeriodos(PERIODOS_VACIOS);
        await waitFor(() => {
            const badges = screen.getAllByText(/Abierto/i);
            expect(badges.length).toBeGreaterThan(0);
        });
    });

    it('muestra badge Cerrado en los meses cerrados', async () => {
        montarConPeriodos(PERIODOS_CON_CERRADOS);
        await waitFor(() => {
            const badges = screen.getAllByText(/Cerrado/i);
            expect(badges.length).toBeGreaterThan(0);
        });
    });

    it('muestra el historial cuando existen períodos cerrados', async () => {
        montarConPeriodos(PERIODOS_CON_CERRADOS);
        await waitFor(() =>
            expect(screen.getByText(/Historial de cierres/i)).toBeTruthy()
        );
    });

    it('muestra quién cerró el período en el historial', async () => {
        montarConPeriodos(PERIODOS_CON_CERRADOS);
        await waitFor(() => {
            const nombres = screen.getAllByText(/Admin Test/i);
            expect(nombres.length).toBeGreaterThan(0);
        });
    });

    it('no muestra historial cuando no hay períodos cerrados', async () => {
        montarConPeriodos(PERIODOS_VACIOS);
        await waitFor(() =>
            expect(screen.queryByText(/Historial de cierres/i)).toBeNull()
        );
    });

    it('muestra la leyenda de efectos del cierre', async () => {
        montarConPeriodos();
        await waitFor(() =>
            expect(screen.getByText(/Efectos del cierre/i)).toBeTruthy()
        );
    });
});

// ─── Tests de navegación por año ──────────────────────────────────────────────
describe('CierrePeriodo — navegación año', () => {
    it('permite avanzar al año siguiente', async () => {
        setupFetchRouter({
            'GET /contabilidad/periodos': () => mockJsonResponse(200, PERIODOS_VACIOS),
        });
        renderWithRouter(<CierrePeriodo />);

        const anioActual = new Date().getFullYear();
        await waitFor(() => expect(screen.getByText(String(anioActual))).toBeTruthy());

        const btnSiguiente = screen.getAllByRole('button').find(b =>
            b.querySelector('.fa-chevron-right')
        );
        fireEvent.click(btnSiguiente);

        await waitFor(() =>
            expect(screen.getByText(String(anioActual + 1))).toBeTruthy()
        );
    });

    it('permite retroceder al año anterior', async () => {
        setupFetchRouter({
            'GET /contabilidad/periodos': () => mockJsonResponse(200, PERIODOS_VACIOS),
        });
        renderWithRouter(<CierrePeriodo />);

        const anioActual = new Date().getFullYear();
        await waitFor(() => expect(screen.getByText(String(anioActual))).toBeTruthy());

        const btnAnterior = screen.getAllByRole('button').find(b =>
            b.querySelector('.fa-chevron-left')
        );
        fireEvent.click(btnAnterior);

        await waitFor(() =>
            expect(screen.getByText(String(anioActual - 1))).toBeTruthy()
        );
    });
});

// ─── Tests de modal: cerrar período ───────────────────────────────────────────
describe('CierrePeriodo — modal cerrar', () => {
    it('abre el modal al hacer click en Cerrar período', async () => {
        montarConPeriodos(PERIODOS_VACIOS);

        await waitFor(() => {
            const botones = screen.getAllByText(/Cerrar período/i);
            expect(botones.length).toBeGreaterThan(0);
        });

        const primerBoton = screen.getAllByText(/Cerrar período/i)[0];
        fireEvent.click(primerBoton);

        await waitFor(() =>
            expect(screen.getByText(/Advertencia/i)).toBeTruthy()
        );
    });

    it('cierra el modal al cancelar', async () => {
        montarConPeriodos(PERIODOS_VACIOS);

        await waitFor(() => screen.getAllByText(/Cerrar período/i)[0]);
        fireEvent.click(screen.getAllByText(/Cerrar período/i)[0]);
        await waitFor(() => screen.getByText(/Cancelar/));

        fireEvent.click(screen.getByText(/Cancelar/));

        await waitFor(() =>
            expect(screen.queryByText(/Advertencia/i)).toBeNull()
        );
    });

    it('llama POST /periodos/cerrar al confirmar', async () => {
        let bodyEnviado = null;

        setupFetchRouter({
            'GET /contabilidad/periodos': () => mockJsonResponse(200, PERIODOS_VACIOS),
            'POST /contabilidad/periodos/cerrar': (body) => {
                bodyEnviado = body;
                return mockJsonResponse(200, RESPUESTA_CIERRE_OK);
            },
        });

        renderWithRouter(<CierrePeriodo />);

        await waitFor(() => screen.getAllByText(/Cerrar período/i)[0]);
        fireEvent.click(screen.getAllByText(/Cerrar período/i)[0]);
        await waitFor(() => screen.getAllByText(/Cerrar período/i).find(b => b.closest('button')));

        // Confirmar desde el modal
        const botonesModal = screen.getAllByText(/Cerrar período/i);
        const botonConfirmar = botonesModal[botonesModal.length - 1];
        fireEvent.click(botonConfirmar);

        await waitFor(() => expect(bodyEnviado).not.toBeNull());
        expect(bodyEnviado).toMatchObject({ mes: expect.any(Number) });
    });
});

// ─── Tests de modal: reabrir período ──────────────────────────────────────────
describe('CierrePeriodo — modal reabrir', () => {
    it('abre el modal de reapertura para un mes cerrado', async () => {
        montarConPeriodos(PERIODOS_CON_CERRADOS);

        await waitFor(() => {
            const botones = screen.getAllByText(/Reabrir período/i);
            expect(botones.length).toBeGreaterThan(0);
        });

        fireEvent.click(screen.getAllByText(/Reabrir período/i)[0]);

        await waitFor(() =>
            expect(screen.getByText(/Reabrir período/i, { selector: 'h3' })).toBeTruthy()
        );
    });

    it('muestra error si intenta reabrir sin motivo', async () => {
        montarConPeriodos(PERIODOS_CON_CERRADOS);

        await waitFor(() => screen.getAllByText(/Reabrir período/i)[0]);
        fireEvent.click(screen.getAllByText(/Reabrir período/i)[0]);
        await waitFor(() => screen.getByText(/Reabrir período/i, { selector: 'h3' }));

        const botonesReabrir = screen.getAllByText(/Reabrir período/i);
        fireEvent.click(botonesReabrir[botonesReabrir.length - 1]);

        await waitFor(() =>
            expect(screen.getByText(/El motivo es obligatorio/i)).toBeTruthy()
        );
    });

    it('llama POST /periodos/reabrir con motivo al confirmar', async () => {
        let bodyEnviado = null;

        setupFetchRouter({
            'GET /contabilidad/periodos': () => mockJsonResponse(200, PERIODOS_CON_CERRADOS),
            'POST /contabilidad/periodos/reabrir': (body) => {
                bodyEnviado = body;
                return mockJsonResponse(200, RESPUESTA_REAPERTURA_OK);
            },
        });

        renderWithRouter(<CierrePeriodo />);

        await waitFor(() => screen.getAllByText(/Reabrir período/i)[0]);
        fireEvent.click(screen.getAllByText(/Reabrir período/i)[0]);
        await waitFor(() => screen.getByPlaceholderText(/motivo de reapertura/i));

        fireEvent.change(screen.getByPlaceholderText(/motivo de reapertura/i), {
            target: { value: 'Corrección autorizada' },
        });

        const botonesReabrir = screen.getAllByText(/Reabrir período/i);
        fireEvent.click(botonesReabrir[botonesReabrir.length - 1]);

        await waitFor(() => expect(bodyEnviado).not.toBeNull());
        expect(bodyEnviado.motivo).toBe('Corrección autorizada');
    });
});

// ─── Tests de permisos ────────────────────────────────────────────────────────
describe('CierrePeriodo — permisos', () => {
    it('oculta botones de acción cuando no tiene permiso contabilidad.cerrar_periodo', async () => {
        vi.mocked(usePermisos).mockImplementation(() => ({ tienePermiso: () => false }));

        setupFetchRouter({
            'GET /contabilidad/periodos': () => mockJsonResponse(200, PERIODOS_CON_CERRADOS),
        });

        renderWithRouter(<CierrePeriodo />);

        await waitFor(() => screen.getByText(/Cierre de Períodos/i));

        expect(screen.queryByText(/Cerrar período/i)).toBeNull();
        expect(screen.queryByText(/Reabrir período/i)).toBeNull();
    });
});
