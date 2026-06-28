import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, cleanup, waitFor } from '@testing-library/react';

vi.mock('react-router-dom', () => ({
    useNavigate: () => vi.fn(),
}));

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
    },
}));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn() },
}));

vi.mock('../../../Componentes/EstadoCarga', () => ({
    default: ({ mensajeCargando, mensajeVacio, iconoVacio, cargando, vacio }) => {
        if (cargando) return <div>{mensajeCargando}</div>;
        if (vacio) return <div>{mensajeVacio}</div>;
        return null;
    },
}));

vi.mock('lucide-react', () => ({
    ArrowUpRight: () => null,
}));

import HistorialCotizaciones from './HistorialCotizaciones';
import { api } from '../../../Configuracion/api';

afterEach(cleanup);
beforeEach(() => {
    vi.clearAllMocks();
});

const cotizacionesMock = [
    {
        id: 1,
        folio: 1001,
        fecha: '2025-01-15',
        total: 500000,
        estado_nombre: 'PENDIENTE',
    },
    {
        id: 2,
        folio: 1002,
        fecha: '2025-02-10',
        total: 1200000,
        estado_nombre: 'ACEPTADA',
    },
    {
        id: 3,
        folio: 1003,
        fecha: '2025-03-05',
        total: 300000,
        estado_nombre: 'RECHAZADA',
    },
];

describe('HistorialCotizaciones', () => {
    it('muestra mensaje de guardar cuando no hay clienteId', () => {
        render(<HistorialCotizaciones />);
        expect(screen.getByText(/Debes registrar y guardar al cliente primero/i)).toBeTruthy();
    });

    it('no llama a la API cuando clienteId es undefined', () => {
        render(<HistorialCotizaciones />);
        expect(api.get).not.toHaveBeenCalled();
    });

    it('muestra spinner de carga mientras se obtienen datos', async () => {
        api.get.mockReturnValue(new Promise(() => {})); // pendiente
        render(<HistorialCotizaciones clienteId={1} />);
        expect(screen.getByText(/Cargando historial/i)).toBeTruthy();
    });

    it('llama api.get con el clienteId correcto', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<HistorialCotizaciones clienteId={5} />);
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/cotizaciones?cliente_id=5');
        });
    });

    it('muestra estado vacío cuando el cliente no tiene cotizaciones', async () => {
        api.get.mockResolvedValue({ success: true, data: [] });
        render(<HistorialCotizaciones clienteId={1} />);
        await waitFor(() => {
            expect(screen.getByText(/Este cliente aún no tiene cotizaciones registradas/i)).toBeTruthy();
        });
    });

    it('renderiza la tabla cuando hay cotizaciones', async () => {
        api.get.mockResolvedValue({ success: true, data: cotizacionesMock });
        render(<HistorialCotizaciones clienteId={1} />);
        await waitFor(() => {
            expect(screen.getByText('#01001')).toBeTruthy();
        });
    });

    it('muestra la cabecera de la tabla correctamente', async () => {
        api.get.mockResolvedValue({ success: true, data: cotizacionesMock });
        render(<HistorialCotizaciones clienteId={1} />);
        await waitFor(() => {
            expect(screen.getByText('Fecha')).toBeTruthy();
            expect(screen.getByText('Folio')).toBeTruthy();
            expect(screen.getByText('Total')).toBeTruthy();
            expect(screen.getByText('Estado')).toBeTruthy();
        });
    });

    it('muestra el folio con padding correcto', async () => {
        api.get.mockResolvedValue({ success: true, data: [cotizacionesMock[0]] });
        render(<HistorialCotizaciones clienteId={1} />);
        await waitFor(() => {
            expect(screen.getByText('#01001')).toBeTruthy();
        });
    });

    it('muestra el estado ACEPTADA', async () => {
        api.get.mockResolvedValue({ success: true, data: [cotizacionesMock[1]] });
        render(<HistorialCotizaciones clienteId={1} />);
        await waitFor(() => {
            expect(screen.getByText('ACEPTADA')).toBeTruthy();
        });
    });

    it('muestra el estado RECHAZADA', async () => {
        api.get.mockResolvedValue({ success: true, data: [cotizacionesMock[2]] });
        render(<HistorialCotizaciones clienteId={1} />);
        await waitFor(() => {
            expect(screen.getByText('RECHAZADA')).toBeTruthy();
        });
    });

    it('formatea la fecha de YYYY-MM-DD a DD-MM-YYYY', async () => {
        api.get.mockResolvedValue({ success: true, data: [cotizacionesMock[0]] });
        render(<HistorialCotizaciones clienteId={1} />);
        await waitFor(() => {
            expect(screen.getByText('15-01-2025')).toBeTruthy();
        });
    });

    it('maneja error silenciosamente sin romper el render', async () => {
        api.get.mockRejectedValue(new Error('Error de red'));
        render(<HistorialCotizaciones clienteId={1} />);
        await waitFor(() => {
            // Tras error, loading se pone en false y data queda vacía
            expect(screen.getByText(/Este cliente aún no tiene cotizaciones registradas/i)).toBeTruthy();
        });
    });

    it('usa fecha_emision como fallback si no hay fecha', async () => {
        const cotConFechaEmision = [{ id: 1, folio: 1, fecha_emision: '2025-06-15', total: 0, estado_nombre: 'PENDIENTE' }];
        api.get.mockResolvedValue({ success: true, data: cotConFechaEmision });
        render(<HistorialCotizaciones clienteId={1} />);
        await waitFor(() => {
            expect(screen.getByText('15-06-2025')).toBeTruthy();
        });
    });
});
