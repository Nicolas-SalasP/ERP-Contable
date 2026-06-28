import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
    },
}));

import TabHistorial from './TabHistorial';
import { api } from '../../../Configuracion/api';

afterEach(cleanup);
beforeEach(() => {
    vi.clearAllMocks();
});

const ejecucionesMock = [
    {
        id: 1,
        estado: 'ejecutada',
        tipo: 'mensual',
        periodo: 'Enero 2025',
        fecha: '2025-01-31',
        usuario: 'admin@empresa.cl',
        asiento_comprobante: 'CM-2025-001',
        variacion_pct: '0.2850',
        total_cm_neto: 125000,
        total_activos: 80000,
        total_existencias: 30000,
        total_depreciacion: -5000,
        total_patrimonio: 15000,
        total_pasivos: 5000,
    },
    {
        id: 2,
        estado: 'simulada',
        tipo: 'anual',
        periodo: 'Año 2024',
        fecha: '2024-12-31',
        usuario: 'admin@empresa.cl',
        asiento_comprobante: null,
        variacion_pct: '3.1200',
        total_cm_neto: 950000,
        total_activos: 700000,
        total_existencias: 150000,
        total_depreciacion: -50000,
        total_patrimonio: 120000,
        total_pasivos: 30000,
    },
];

describe('TabHistorial', () => {
    it('muestra spinner mientras carga', () => {
        api.get.mockReturnValue(new Promise(() => {}));
        render(<TabHistorial />);
        expect(screen.getByText(/Cargando historial/i)).toBeTruthy();
    });

    it('muestra estado vacío cuando no hay ejecuciones', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText(/No hay ejecuciones registradas/i)).toBeTruthy();
        });
    });

    it('muestra el título "Historial de Ejecuciones"', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText('Historial de Ejecuciones')).toBeTruthy();
        });
    });

    it('muestra contador de ejecuciones registradas', async () => {
        api.get.mockResolvedValue({ success: true, data: ejecucionesMock });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText(/2 ejecuciones registradas/i)).toBeTruthy();
        });
    });

    it('renderiza los periodos de las ejecuciones', async () => {
        api.get.mockResolvedValue({ success: true, data: ejecucionesMock });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText('Enero 2025')).toBeTruthy();
            expect(screen.getByText('Año 2024')).toBeTruthy();
        });
    });

    it('muestra el badge de estado "ejecutada"', async () => {
        api.get.mockResolvedValue({ success: true, data: [ejecucionesMock[0]] });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText('ejecutada')).toBeTruthy();
        });
    });

    it('muestra el badge de estado "simulada"', async () => {
        api.get.mockResolvedValue({ success: true, data: [ejecucionesMock[1]] });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText('simulada')).toBeTruthy();
        });
    });

    it('muestra el comprobante de asiento cuando existe', async () => {
        api.get.mockResolvedValue({ success: true, data: [ejecucionesMock[0]] });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText('CM-2025-001')).toBeTruthy();
        });
    });

    it('llama api.get sin año cuando no hay anioInicial', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/correccion-monetaria/historial');
        });
    });

    it('llama api.get con año cuando se pasa anioInicial', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<TabHistorial anioInicial={2025} />);
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/correccion-monetaria/historial?anio=2025');
        });
    });

    it('expande los detalles al hacer click en una ejecución', async () => {
        api.get.mockResolvedValue({ success: true, data: [ejecucionesMock[0]] });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText('Enero 2025')).toBeTruthy();
        });

        fireEvent.click(screen.getByText('Enero 2025').closest('button'));
        await waitFor(() => {
            expect(screen.getByText('Activos')).toBeTruthy();
            expect(screen.getByText('Existencias')).toBeTruthy();
            expect(screen.getByText('Patrimonio')).toBeTruthy();
        });
    });

    it('colapsa los detalles al hacer click dos veces', async () => {
        api.get.mockResolvedValue({ success: true, data: [ejecucionesMock[0]] });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText('Enero 2025')).toBeTruthy();
        });

        const btn = screen.getByText('Enero 2025').closest('button');
        fireEvent.click(btn);
        await waitFor(() => {
            expect(screen.getByText('Activos')).toBeTruthy();
        });
        fireEvent.click(btn);
        await waitFor(() => {
            expect(screen.queryByText('Activos')).toBeNull();
        });
    });

    it('filtro por año llama api.get con el año seleccionado', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/correccion-monetaria/historial');
        });

        const select = screen.getByRole('combobox');
        const anioActual = new Date().getFullYear();
        fireEvent.change(select, { target: { value: String(anioActual) } });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(`/correccion-monetaria/historial?anio=${anioActual}`);
        });
    });

    it('seleccionar "Todos" en el filtro de año vuelve a llamar sin año', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<TabHistorial anioInicial={2025} />);
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/correccion-monetaria/historial?anio=2025');
        });

        const select = screen.getByRole('combobox');
        fireEvent.change(select, { target: { value: '' } });

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/correccion-monetaria/historial');
        });
    });

    it('maneja error de API silenciosamente', async () => {
        api.get.mockRejectedValue(new Error('Error de red'));
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText(/No hay ejecuciones registradas/i)).toBeTruthy();
        });
    });
});

    it('muestra badge de tipo "anual"', async () => {
        const ejAnual = [{ ...ejecucionesMock[1], tipo: 'anual', estado: 'anulada' }];
        api.get.mockResolvedValue({ success: true, data: ejAnual });
        render(<TabHistorial />);
        await waitFor(() => {
            expect(screen.getByText('anulada')).toBeTruthy();
        });
    });
