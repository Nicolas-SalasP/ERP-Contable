import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => ({ tienePermiso: () => true }),
}));

vi.mock('../Servicios/tributarioApi', () => ({
    dj1887: {
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

import { dj1887 } from '../Servicios/tributarioApi';
import Dj1887 from './Dj1887';

afterEach(cleanup);

describe('Dj1887 (vista)', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('renderiza el título DJ 1887', async () => {
        dj1887.listar.mockResolvedValue([]);
        render(<Dj1887 />);
        await waitFor(() => {
            expect(screen.getAllByText(/DJ 1887/i).length).toBeGreaterThan(0);
        });
    });

    it('muestra estado vacío cuando no hay envíos', async () => {
        dj1887.listar.mockResolvedValue([]);
        render(<Dj1887 />);
        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1887/i)).toBeTruthy();
        });
    });

    it('muestra historial cuando listar retorna envíos', async () => {
        dj1887.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 10, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1887 />);
        await waitFor(() => {
            expect(screen.getAllByText('2024').length).toBeGreaterThan(0);
        });
    });

    it('BadgeEstado renderiza GENERADO', async () => {
        dj1887.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 10, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1887 />);
        await waitFor(() => {
            expect(screen.getAllByText('GENERADO').length).toBeGreaterThan(0);
        });
    });

    it('handleGenerar llama a dj1887.generar con anio y anio40Horas', async () => {
        dj1887.listar.mockResolvedValue([]);
        dj1887.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 5, estado: 'GENERADO' });

        render(<Dj1887 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1887/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1887/i });
        await act(async () => { fireEvent.click(btn); });

        const anioEsperado = new Date().getFullYear() - 1;
        const anio40Esperado = new Date().getFullYear();
        expect(dj1887.generar).toHaveBeenCalledWith(anioEsperado, anio40Esperado);
    });

    it('muestra mensaje de éxito al generar', async () => {
        dj1887.listar.mockResolvedValue([]);
        dj1887.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 5, estado: 'GENERADO' });

        render(<Dj1887 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1887/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1887/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            expect(screen.getByText(/DJ 1887 año/i)).toBeTruthy();
        });
    });

    it('muestra mensaje de error cuando generar falla', async () => {
        dj1887.listar.mockResolvedValue([]);
        dj1887.generar.mockRejectedValue({ response: { data: { mensaje: 'Sin empleados activos' } } });

        render(<Dj1887 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1887/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1887/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            expect(screen.getByText(/Sin empleados activos/i)).toBeTruthy();
        });
    });

    it('el selector de año tributario incluye el año anterior al actual', async () => {
        dj1887.listar.mockResolvedValue([]);
        render(<Dj1887 />);
        await waitFor(() => {
            expect(screen.getByDisplayValue(String(new Date().getFullYear() - 1))).toBeTruthy();
        });
    });

    it('el selector anio40Horas existe con etiqueta Ley 21.561', async () => {
        dj1887.listar.mockResolvedValue([]);
        render(<Dj1887 />);
        await waitFor(() => {
            expect(screen.getByText(/Año inicio 40h/i)).toBeTruthy();
        });
        // Hay dos <select>: uno para año tributario y otro para año 40h
        const selects = screen.getAllByRole('combobox');
        expect(selects.length).toBeGreaterThanOrEqual(2);
    });
});
