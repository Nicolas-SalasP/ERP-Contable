import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => ({ tienePermiso: () => true }),
}));

vi.mock('../Servicios/tributarioApi', () => ({
    dj1947: {
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

import { dj1947 } from '../Servicios/tributarioApi';
import Dj1947 from './Dj1947';

afterEach(cleanup);

describe('Dj1947 (vista)', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('renderiza el título con "DJ 1947"', async () => {
        dj1947.listar.mockResolvedValue([]);
        render(<Dj1947 />);
        await waitFor(() => {
            expect(screen.getAllByText(/DJ 1947/i).length).toBeGreaterThan(0);
        });
    });

    it('muestra el selector de año con el año anterior al actual como valor por defecto', async () => {
        dj1947.listar.mockResolvedValue([]);
        render(<Dj1947 />);
        await waitFor(() => {
            const select = screen.getByRole('combobox');
            expect(select).toBeTruthy();
            expect(select.value).toBe(String(new Date().getFullYear() - 1));
        });
    });

    it('llama a dj1947.listar al montar el componente', async () => {
        dj1947.listar.mockResolvedValue([]);
        render(<Dj1947 />);
        await waitFor(() => {
            expect(dj1947.listar).toHaveBeenCalledTimes(1);
        });
    });

    it('muestra filas del historial con año y estado cuando listar retorna declaraciones', async () => {
        dj1947.listar.mockResolvedValue([
            {
                id: 1,
                anio: 2024,
                estado: 'GENERADO',
                cantidad_registros: 5,
                folio_presentacion: null,
                presentado_at: null,
            },
        ]);
        render(<Dj1947 />);
        await waitFor(() => {
            expect(screen.getAllByText('2024').length).toBeGreaterThan(0);
            expect(screen.getAllByText('GENERADO').length).toBeGreaterThan(0);
        });
    });

    it('muestra texto de estado vacío cuando no hay declaraciones DJ 1947', async () => {
        dj1947.listar.mockResolvedValue([]);
        render(<Dj1947 />);
        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1947 generadas/i)).toBeTruthy();
        });
    });

    it('muestra mensaje de error personalizado cuando generar falla', async () => {
        dj1947.listar.mockResolvedValue([]);
        dj1947.generar.mockRejectedValue({
            response: { data: { mensaje: 'Sin propietarios registrados en la empresa' } },
        });
        render(<Dj1947 />);
        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Generar DJ 1947/i })).toBeTruthy();
        });
        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Generar DJ 1947/i }));
        });
        await waitFor(() => {
            expect(screen.getByText(/Sin propietarios registrados en la empresa/i)).toBeTruthy();
        });
    });

    it('muestra mensaje de éxito con cantidad de registros al generar correctamente', async () => {
        dj1947.listar.mockResolvedValue([]);
        dj1947.generar.mockResolvedValue({
            id: 2,
            anio: new Date().getFullYear() - 1,
            estado: 'GENERADO',
            cantidad_registros: 3,
        });
        render(<Dj1947 />);
        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Generar DJ 1947/i })).toBeTruthy();
        });
        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Generar DJ 1947/i }));
        });
        await waitFor(() => {
            expect(screen.getByText(/DJ 1947 año/i)).toBeTruthy();
            expect(screen.getByText(/3 registro/i)).toBeTruthy();
        });
    });

    it('muestra el panel de detalle con el primer envío del historial cargado', async () => {
        dj1947.listar.mockResolvedValue([
            {
                id: 5,
                anio: 2023,
                estado: 'VALIDADO',
                cantidad_registros: 7,
                folio_presentacion: 'F-2023-001',
                presentado_at: null,
            },
        ]);
        render(<Dj1947 />);
        await waitFor(() => {
            expect(screen.getByText(/DJ 1947 — Año 2023/i)).toBeTruthy();
        });
    });
});
