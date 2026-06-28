import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => ({
        tienePermiso: (p) => ['tributario.ver', 'tributario.procesar'].includes(p),
    }),
}));

vi.mock('../Servicios/tributarioApi', () => ({
    dj1837: {
        listar: vi.fn(),
        generar: vi.fn(),
        validar: vi.fn(),
        descargar: vi.fn(),
        confirmarPresentacion: vi.fn(),
    },
}));

vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({
    default: () => null,
}));

import { dj1837 } from '../Servicios/tributarioApi';
import Dj1837 from './Dj1837';

afterEach(cleanup);

describe('Dj1837 (vista)', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('renderiza el título de la declaración', async () => {
        dj1837.listar.mockResolvedValue([]);
        render(<Dj1837 />);
        await waitFor(() => {
            expect(screen.getAllByText(/DJ 1837/i).length).toBeGreaterThan(0);
        });
    });

    it('muestra estado vacío cuando no hay envíos', async () => {
        dj1837.listar.mockResolvedValue([]);
        render(<Dj1837 />);
        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1837/i)).toBeTruthy();
        });
    });

    it('muestra historial cuando listar retorna envíos', async () => {
        dj1837.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 8, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1837 />);
        await waitFor(() => {
            expect(screen.getAllByText('2024').length).toBeGreaterThan(0);
        });
    });

    it('BadgeEstado renderiza estado GENERADO', async () => {
        dj1837.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 8, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1837 />);
        await waitFor(() => {
            expect(screen.getAllByText('GENERADO').length).toBeGreaterThan(0);
        });
    });

    it('handleGenerar llama a dj1837.generar con el año seleccionado', async () => {
        dj1837.listar.mockResolvedValue([]);
        dj1837.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 8, estado: 'GENERADO' });

        render(<Dj1837 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1837/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1837/i });
        await act(async () => { fireEvent.click(btn); });

        expect(dj1837.generar).toHaveBeenCalledWith(new Date().getFullYear() - 1);
    });

    it('muestra mensaje de éxito al generar', async () => {
        dj1837.listar.mockResolvedValue([]);
        dj1837.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 8, estado: 'GENERADO' });

        render(<Dj1837 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1837/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1837/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            // Success message: "DJ 1837 año XXXX generada con N prestador(es)."
            expect(screen.getByText(/DJ 1837 año/i)).toBeTruthy();
        });
    });

    it('muestra mensaje de error cuando generar falla', async () => {
        dj1837.listar.mockResolvedValue([]);
        dj1837.generar.mockRejectedValue({ response: { data: { mensaje: 'Sin honorarios sin retención' } } });

        render(<Dj1837 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1837/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1837/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            expect(screen.getByText(/Sin honorarios sin retención/i)).toBeTruthy();
        });
    });

    it('formatFecha devuelve — para fecha nula', async () => {
        dj1837.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 8, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1837 />);
        await waitFor(() => {
            const dashes = screen.getAllByText('—');
            expect(dashes.length).toBeGreaterThan(0);
        });
    });

    it('el selector de año incluye el año anterior al actual', async () => {
        dj1837.listar.mockResolvedValue([]);
        render(<Dj1837 />);
        await waitFor(() => {
            expect(screen.getByDisplayValue(String(new Date().getFullYear() - 1))).toBeTruthy();
        });
    });
});
