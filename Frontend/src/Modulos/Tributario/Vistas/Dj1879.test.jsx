import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => ({
        tienePermiso: (p) => ['tributario.ver', 'tributario.procesar'].includes(p),
    }),
}));

vi.mock('../Servicios/tributarioApi', () => ({
    dj1879: {
        listar: vi.fn(),
        generar: vi.fn(),
        validar: vi.fn(),
        descargar: vi.fn(),
        confirmarPresentacion: vi.fn(),
    },
}));

vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({ default: () => null }));
vi.mock('../../../Componentes/Skeleton', () => ({ TablaSkeleton: () => null }));
vi.mock('../../../Componentes/EstadoVacio', () => ({ EstadoVacio: () => null }));

import { dj1879 } from '../Servicios/tributarioApi';
import Dj1879 from './Dj1879';

afterEach(cleanup);

describe('Dj1879 (vista)', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('renderiza el título DJ 1879', async () => {
        dj1879.listar.mockResolvedValue([]);
        render(<Dj1879 />);
        await waitFor(() => {
            expect(screen.getAllByText(/DJ 1879/i).length).toBeGreaterThan(0);
        });
    });

    it('muestra estado vacío cuando no hay envíos', async () => {
        dj1879.listar.mockResolvedValue([]);
        render(<Dj1879 />);
        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1879/i)).toBeTruthy();
        });
    });

    it('muestra historial cuando listar retorna envíos', async () => {
        dj1879.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 10, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1879 />);
        await waitFor(() => {
            expect(screen.getAllByText('2024').length).toBeGreaterThan(0);
        });
    });

    it('BadgeEstado renderiza estado GENERADO', async () => {
        dj1879.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 10, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1879 />);
        await waitFor(() => {
            expect(screen.getAllByText('GENERADO').length).toBeGreaterThan(0);
        });
    });

    it('handleGenerar llama a dj1879.generar con el año seleccionado', async () => {
        dj1879.listar.mockResolvedValue([]);
        dj1879.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 8, estado: 'GENERADO' });

        render(<Dj1879 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1879/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1879/i });
        await act(async () => { fireEvent.click(btn); });

        expect(dj1879.generar).toHaveBeenCalledWith(new Date().getFullYear() - 1);
    });

    it('muestra mensaje de éxito al generar', async () => {
        dj1879.listar.mockResolvedValue([]);
        dj1879.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 8, estado: 'GENERADO' });

        render(<Dj1879 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1879/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1879/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            expect(screen.getByText(/DJ 1879 año/i)).toBeTruthy();
        });
    });

    it('muestra mensaje de error cuando generar falla', async () => {
        dj1879.listar.mockResolvedValue([]);
        dj1879.generar.mockRejectedValue({ response: { data: { mensaje: 'Sin honorarios registrados' } } });

        render(<Dj1879 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1879/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1879/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            expect(screen.getByText(/Sin honorarios registrados/i)).toBeTruthy();
        });
    });

    it('formatFecha devuelve — para fecha nula', async () => {
        dj1879.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 10, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1879 />);
        await waitFor(() => {
            const dashes = screen.getAllByText('—');
            expect(dashes.length).toBeGreaterThan(0);
        });
    });

    it('el selector de año incluye el año anterior al actual', async () => {
        dj1879.listar.mockResolvedValue([]);
        render(<Dj1879 />);
        await waitFor(() => {
            expect(screen.getByDisplayValue(String(new Date().getFullYear() - 1))).toBeTruthy();
        });
    });
});
