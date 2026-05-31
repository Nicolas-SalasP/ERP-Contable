import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../../test-utils';
import TabIndicesIpc from './TabIndicesIpc';

const swalMock = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
}));
vi.mock('sweetalert2', () => ({ default: swalMock }));

const indicesBase = Array.from({ length: 12 }, (_, i) => ({
    mes: i + 1,
    nombre_mes: ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][i],
    anio: 2026,
    cargado: i < 3,
    variacion_mensual: i < 3 ? 0.42 : null,
    variacion_acumulada: i < 3 ? 0.42 * (i + 1) : null,
    factor_multiplicador: i < 3 ? 1.004200 : null,
    fuente: i < 3 ? 'manual' : null,
    observacion: null,
    updated_at: i < 3 ? '2026-03-10' : null,
}));

const setupMocks = (overrides = {}) =>
    setupFetchRouter({
        'GET /correccion-monetaria/indices': () => mockJsonResponse(200, { success: true, data: indicesBase }),
        'POST /correccion-monetaria/indices': () => mockJsonResponse(200, { success: true, data: indicesBase[0] }),
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

describe('TabIndicesIpc - render', () => {
    it('muestra el titulo Indices IPC Mensuales', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => expect(screen.getByText('Índices IPC Mensuales')).toBeDefined());
    });

    it('muestra el año inicial en el selector', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => expect(screen.getByText('2026')).toBeDefined());
    });

    it('muestra los 12 meses en la tabla', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => {
            expect(screen.getByText('Enero')).toBeDefined();
            expect(screen.getByText('Diciembre')).toBeDefined();
        });
    });

    it('meses cargados muestran variacion con signo +', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => {
            const positivos = screen.getAllByText(/^\+0\.4200%$/);
            expect(positivos.length).toBeGreaterThan(0);
        });
    });

    it('meses sin dato muestran Sin dato', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => {
            const sinDatos = screen.getAllByText('Sin dato');
            expect(sinDatos.length).toBeGreaterThan(0);
        });
    });

    it('meses cargados muestran boton Editar', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => {
            const editar = screen.getAllByText('Editar');
            expect(editar.length).toBeGreaterThan(0);
        });
    });

    it('meses sin dato muestran boton Ingresar', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => {
            const ingresar = screen.getAllByText('Ingresar');
            expect(ingresar.length).toBeGreaterThan(0);
        });
    });

    it('muestra banner de IPC acumulado cuando hay datos', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => {
            expect(screen.getByText(/IPC Acumulado/i)).toBeDefined();
        });
    });

    it('muestra conteo de meses cargados', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => {
            expect(screen.getByText(/3\/12 meses cargados/i)).toBeDefined();
        });
    });
});

describe('TabIndicesIpc - edicion inline', () => {
    it('click en Ingresar muestra input de variacion', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => screen.getAllByText('Ingresar'));

        const botones = screen.getAllByText('Ingresar');
        fireEvent.click(botones[0]);

        await waitFor(() => {
            expect(screen.getByPlaceholderText('0.4200')).toBeDefined();
        });
    });

    it('click en Cancelar oculta el input', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => screen.getAllByText('Ingresar'));

        fireEvent.click(screen.getAllByText('Ingresar')[0]);
        await waitFor(() => screen.getByPlaceholderText('0.4200'));

        fireEvent.click(screen.getByText('Cancelar'));
        await waitFor(() => {
            expect(screen.queryByPlaceholderText('0.4200')).toBeNull();
        });
    });

    it('click en Guardar llama POST al endpoint', async () => {
        const fetchMock = setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => screen.getAllByText('Ingresar'));

        fireEvent.click(screen.getAllByText('Ingresar')[0]);
        await waitFor(() => screen.getByPlaceholderText('0.4200'));

        fireEvent.change(screen.getByPlaceholderText('0.4200'), { target: { value: '0.35' } });
        fireEvent.click(screen.getByText('Guardar'));

        await waitFor(() => {
            const postCalls = fetchMock.mock.calls.filter(([url, opts]) =>
                url.includes('/correccion-monetaria/indices') && (opts?.method || 'GET') === 'POST'
            );
            expect(postCalls.length).toBeGreaterThan(0);
        });
    });

    it('Guardar con valor vacio llama a Swal con error', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => screen.getAllByText('Ingresar'));

        fireEvent.click(screen.getAllByText('Ingresar')[0]);
        await waitFor(() => screen.getByText('Guardar'));

        fireEvent.click(screen.getByText('Guardar'));

        await waitFor(() => {
            expect(swalMock.fire).toHaveBeenCalled();
        });
    });
});

describe('TabIndicesIpc - navegacion de año', () => {
    it('boton chevron izquierdo decrementa el año', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => screen.getByText('2026'));

        const chevrons = screen.getAllByRole('button');
        const izq = chevrons.find(b => b.querySelector('.fa-chevron-left'));
        if (izq) fireEvent.click(izq);

        await waitFor(() => {
            expect(screen.getByText('2025')).toBeDefined();
        });
    });

    it('cambio de año hace nueva peticion al backend', async () => {
        const fetchMock = setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => screen.getByText('2026'));

        const prevCallCount = fetchMock.mock.calls.length;

        const chevrons = screen.getAllByRole('button');
        const izq = chevrons.find(b => b.querySelector('.fa-chevron-left'));
        if (izq) fireEvent.click(izq);

        await waitFor(() => {
            expect(fetchMock.mock.calls.length).toBeGreaterThan(prevCallCount);
        });
    });

    it('muestra nota informativa del INE', async () => {
        setupMocks();
        renderWithRouter(<TabIndicesIpc anioInicial={2026} />);
        await waitFor(() => {
            expect(screen.getByText(/INE publica el IPC/i)).toBeDefined();
        });
    });
});
