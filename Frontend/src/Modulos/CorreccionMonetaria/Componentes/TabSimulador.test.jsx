import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import TabSimulador from './TabSimulador';

const swalMock = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: false }),
}));
vi.mock('sweetalert2', () => ({ default: swalMock }));

const configMock = {
    aplica_cm:    true,
    modalidad:    'anual',
    mes_cierre:   12,
    nombre_mes_cierre: 'Diciembre',
};

const estadoEjecutableMock = {
    mes: 12, anio: 2026, nombre_mes: 'Diciembre',
    ya_ejecutada: false, tiene_ipc: true,
    puede_ejecutar: true, puede_simular: true,
    aplica_cm: true, modalidad: 'anual', mes_cierre: 12,
    bloqueado_por_modalidad: false,
};

const resultadoSimMock = {
    periodo: { mes: 12, anio: 2026, nombre_mes: 'Diciembre' },
    tipo: 'anual', variacion_pct: 0.42, factor: 1.004200,
    modalidad: 'anual', proveedor_ipc: 'Manual (base de datos)',
    lineas: [
        { cuenta_codigo: '112005', nombre_cuenta: 'Edificios', rol_cm: 'ACTIVO_NO_MONETARIO',
          label_rol: 'Activo No Monetario', saldo_ajustable: 10000000, variacion_usada: 0.42, ajuste: 42000 },
    ],
    asiento_preview: [
        { cuenta_contable: '112005', debe: 42000, haber: 0, glosa_detalle: 'CM Activo: Edificios' },
        { cuenta_contable: '811001', debe: 0, haber: 42000, glosa_detalle: 'CM Resultado Activos' },
    ],
    totales: { activos: 42000, existencias: 0, depreciacion: 0, patrimonio: 0, pasivos: 0, neto: 42000 },
    es_simulacion: true,
};

const setupMocks = (overrides = {}) =>
    setupFetchRouter({
        'GET /correccion-monetaria/simular': () => mockJsonResponse(200, { success: true, data: resultadoSimMock }),
        'GET /correccion-monetaria/estado':  () => mockJsonResponse(200, { success: true, data: estadoEjecutableMock }),
        'POST /correccion-monetaria/ejecutar': () => mockJsonResponse(200, {
            success: true,
            data: { asiento_comprobante: '2610000001', total_cm_neto: 42000 },
        }),
        ...overrides,
    });

beforeEach(() => {
    cleanTestEnv();
    swalMock.fire.mockClear();
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

describe('TabSimulador - render inicial', () => {
    it('muestra selector de mes y ano', () => {
        renderWithRouter(<TabSimulador config={configMock} />);
        expect(screen.getByText('Mes')).toBeDefined();
        expect(screen.getByText('Año')).toBeDefined();
    });

    it('muestra boton Simular', () => {
        renderWithRouter(<TabSimulador config={configMock} />);
        expect(screen.getByRole('button', { name: /Simular/i })).toBeDefined();
    });

    it('muestra mensaje de espera antes de simular', () => {
        renderWithRouter(<TabSimulador config={configMock} />);
        expect(screen.getByText(/Selecciona un período/i)).toBeDefined();
    });

    it('no muestra tabla de asiento antes de simular', () => {
        renderWithRouter(<TabSimulador config={configMock} />);
        expect(screen.queryByText('Vista Previa del Asiento')).toBeNull();
    });
});

describe('TabSimulador - despues de simular', () => {
    it('click en Simular llama al endpoint correcto', async () => {
        const fetchMock = setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);

        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => {
            const simCalls = fetchMock.mock.calls.filter(([url]) =>
                url.includes('/correccion-monetaria/simular')
            );
            expect(simCalls.length).toBeGreaterThan(0);
        });
    });

    it('muestra tabla de asiento preview despues de simular', async () => {
        setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => {
            expect(screen.getByText('Vista Previa del Asiento')).toBeDefined();
        });
    });

    it('muestra el total CM neto', async () => {
        setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => {
            expect(screen.getByText('Total CM Neto')).toBeDefined();
        });
    });

    it('muestra la variacion IPC en el header del asiento', async () => {
        setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => {
            expect(screen.getAllByText(/0\.4200/)[0]).toBeDefined();
        });
    });

    it('muestra tabla de detalle por cuenta', async () => {
        setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => {
            expect(screen.getByText('Detalle por Cuenta')).toBeDefined();
            expect(screen.getByText('Edificios')).toBeDefined();
        });
    });

    it('muestra boton Ejecutar y Contabilizar cuando puede ejecutar', async () => {
        setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => {
            expect(screen.getByText('Ejecutar y Contabilizar')).toBeDefined();
        });
    });

    it('muestra tarjetas de totales por categoria', async () => {
        setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => {
            expect(screen.getByText('Activos')).toBeDefined();
            expect(screen.getByText('Existencias')).toBeDefined();
            expect(screen.getByText('Depreciación')).toBeDefined();
            expect(screen.getByText('Patrimonio')).toBeDefined();
            expect(screen.getByText('Pasivos')).toBeDefined();
        });
    });

    it('periodo ya ejecutado muestra badge y oculta boton ejecutar', async () => {
        setupMocks({
            'GET /correccion-monetaria/estado': () => mockJsonResponse(200, {
                success: true,
                data: { ...estadoEjecutableMock, ya_ejecutada: true, puede_ejecutar: false },
            }),
        });
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => {
            expect(screen.getByText(/Ya ejecutada en este período/i)).toBeDefined();
            expect(screen.queryByText('Ejecutar y Contabilizar')).toBeNull();
        });
    });

    it('bloqueado por modalidad muestra mensaje de anual', async () => {
        setupMocks({
            'GET /correccion-monetaria/estado': () => mockJsonResponse(200, {
                success: true,
                data: { ...estadoEjecutableMock, bloqueado_por_modalidad: true, puede_ejecutar: false },
            }),
        });
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => {
            expect(screen.getByText(/Modalidad anual/i)).toBeDefined();
        });
    });
});

describe('TabSimulador - ejecucion', () => {
    it('click en Ejecutar muestra Swal de confirmacion', async () => {
        swalMock.fire.mockResolvedValue({ isConfirmed: false });
        setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => screen.getByText('Ejecutar y Contabilizar'));
        fireEvent.click(screen.getByText('Ejecutar y Contabilizar'));

        await waitFor(() => {
            expect(swalMock.fire).toHaveBeenCalled();
        });
    });

    it('confirmar Swal llama POST al endpoint ejecutar', async () => {
        swalMock.fire.mockResolvedValue({ isConfirmed: true });
        const fetchMock = setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => screen.getByText('Ejecutar y Contabilizar'));
        fireEvent.click(screen.getByText('Ejecutar y Contabilizar'));

        await waitFor(() => {
            const postCalls = fetchMock.mock.calls.filter(([url, opts]) =>
                url.includes('/ejecutar') && (opts?.method || 'GET') === 'POST'
            );
            expect(postCalls.length).toBeGreaterThan(0);
        });
    });

    it('cancelar Swal no llama POST al endpoint', async () => {
        swalMock.fire.mockResolvedValue({ isConfirmed: false });
        const fetchMock = setupMocks();
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => screen.getByText('Ejecutar y Contabilizar'));
        fireEvent.click(screen.getByText('Ejecutar y Contabilizar'));

        await waitFor(() => expect(swalMock.fire).toHaveBeenCalled());

        const postCalls = fetchMock.mock.calls.filter(([url, opts]) =>
            url.includes('/ejecutar') && (opts?.method || 'GET') === 'POST'
        );
        expect(postCalls.length).toBe(0);
    });

    it('error del backend muestra Swal de error', async () => {
        swalMock.fire.mockResolvedValueOnce({ isConfirmed: true });
        setupMocks({
            'POST /correccion-monetaria/ejecutar': () => mockJsonResponse(400, {
                success: false, message: 'Ya fue ejecutada',
            }),
        });
        renderWithRouter(<TabSimulador config={configMock} />);
        fireEvent.click(screen.getByRole('button', { name: /Simular/i }));

        await waitFor(() => screen.getByText('Ejecutar y Contabilizar'));
        fireEvent.click(screen.getByText('Ejecutar y Contabilizar'));

        await waitFor(() => {
            expect(swalMock.fire).toHaveBeenCalledTimes(3);
        });
    });
});
