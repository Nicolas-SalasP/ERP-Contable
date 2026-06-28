import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => ({
        tienePermiso: (p) => ['tributario.ver', 'tributario.procesar'].includes(p),
    }),
}));

vi.mock('../Servicios/tributarioApi', () => ({
    dj1835: {
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

import { dj1835 } from '../Servicios/tributarioApi';
import Dj1835 from './Dj1835';

afterEach(cleanup);

describe('Dj1835 (vista)', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('renderiza el título de la declaración', async () => {
        dj1835.listar.mockResolvedValue([]);
        render(<Dj1835 />);
        await waitFor(() => {
            // getByRole looks for the h1 only; the <i> icon has no text so the name is the text node
            expect(screen.getAllByText(/DJ 1835/i).length).toBeGreaterThan(0);
        });
    });

    it('muestra estado vacío cuando no hay envíos', async () => {
        dj1835.listar.mockResolvedValue([]);
        render(<Dj1835 />);
        await waitFor(() => {
            // The text node "No hay declaraciones DJ 1835 generadas." is unique via getNodeText
            expect(screen.getByText(/No hay declaraciones DJ 1835/i)).toBeTruthy();
        });
    });

    it('muestra historial cuando listar retorna envíos', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1835 />);
        await waitFor(() => {
            // 2024 appears in both the select options and the table cell; check at least one exists
            expect(screen.getAllByText('2024').length).toBeGreaterThan(0);
        });
    });

    it('BadgeEstado renderiza estado GENERADO', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1835 />);
        await waitFor(() => {
            // BadgeEstado renders in detail card and table row; use getAllByText
            expect(screen.getAllByText('GENERADO').length).toBeGreaterThan(0);
        });
    });

    it('handleGenerar llama a dj1835.generar con el año seleccionado', async () => {
        dj1835.listar.mockResolvedValue([]);
        dj1835.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 3, estado: 'GENERADO' });

        render(<Dj1835 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1835/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1835/i });
        await act(async () => { fireEvent.click(btn); });

        expect(dj1835.generar).toHaveBeenCalledWith(new Date().getFullYear() - 1);
    });

    it('muestra mensaje de éxito al generar', async () => {
        dj1835.listar.mockResolvedValue([]);
        dj1835.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 3, estado: 'GENERADO' });

        render(<Dj1835 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1835/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1835/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            // Success message: "DJ 1835 año XXXX generada con N línea(s)..."
            // The pattern "DJ 1835 año" is unique (heading uses "DJ 1835 — Año")
            expect(screen.getByText(/DJ 1835 año/i)).toBeTruthy();
        });
    });

    it('muestra mensaje de error cuando generar falla', async () => {
        dj1835.listar.mockResolvedValue([]);
        dj1835.generar.mockRejectedValue({ response: { data: { mensaje: 'Sin facturas exterior' } } });

        render(<Dj1835 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1835/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1835/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            expect(screen.getByText(/Sin facturas exterior/i)).toBeTruthy();
        });
    });

    it('formatFecha devuelve — para fecha nula', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1835 />);
        await waitFor(() => {
            const dashes = screen.getAllByText('—');
            expect(dashes.length).toBeGreaterThan(0);
        });
    });

    it('el selector de año incluye el año anterior al actual', async () => {
        dj1835.listar.mockResolvedValue([]);
        render(<Dj1835 />);
        await waitFor(() => {
            expect(screen.getByDisplayValue(String(new Date().getFullYear() - 1))).toBeTruthy();
        });
    });
});
