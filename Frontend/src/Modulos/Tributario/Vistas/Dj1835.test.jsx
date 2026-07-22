import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => ({
        tienePermiso: (p) => ['tributario.ver', 'contabilidad.dj.procesar'].includes(p),
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

    it('muestra mensaje de error cuando falla el historial', async () => {
        dj1835.listar.mockRejectedValue({ response: { data: { message: 'Error del servidor' } } });
        render(<Dj1835 />);
        await waitFor(() => {
            expect(screen.getByText(/Error del servidor/i)).toBeTruthy();
        });
    });

    it('handleValidar llama a dj1835.validar y muestra mensaje de éxito', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        dj1835.validar.mockResolvedValue({ envio: { id: 1, anio: 2024, estado: 'VALIDADO', cantidad_registros: 5 } });
        render(<Dj1835 />);
        const btn = await screen.findByRole('button', { name: /Validar/i });
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(dj1835.validar).toHaveBeenCalledWith(1);
            expect(screen.getByText(/DJ 1835 validada correctamente/i)).toBeTruthy();
        });
    });

    it('handleValidar muestra mensaje de error cuando falla', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        dj1835.validar.mockRejectedValue({ response: { data: { mensaje: 'Faltan datos de retención' } } });
        render(<Dj1835 />);
        const btn = await screen.findByRole('button', { name: /Validar/i });
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(screen.getByText(/Faltan datos de retención/i)).toBeTruthy();
        });
    });

    it('handleDescargar crea el enlace de descarga y recarga el historial', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        const blob = new Blob(['contenido']);
        dj1835.descargar.mockResolvedValue(blob);
        global.URL.createObjectURL = vi.fn(() => 'blob:url');
        global.URL.revokeObjectURL = vi.fn();
        render(<Dj1835 />);
        const btn = (await screen.findAllByRole('button', { name: /Descargar \.txt/i }))[0];
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(dj1835.descargar).toHaveBeenCalledWith(1);
            expect(global.URL.createObjectURL).toHaveBeenCalledWith(blob);
            expect(dj1835.listar).toHaveBeenCalledTimes(2);
        });
    });

    it('handleDescargar muestra mensaje de error cuando falla', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        dj1835.descargar.mockRejectedValue(new Error('fallo de red'));
        render(<Dj1835 />);
        const btn = (await screen.findAllByRole('button', { name: /Descargar \.txt/i }))[0];
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(screen.getByText(/Error al descargar el archivo de la DJ 1835/i)).toBeTruthy();
        });
    });

    it('handleConfirmarPresentacion envía el folio y muestra mensaje de éxito', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'VALIDADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        dj1835.confirmarPresentacion.mockResolvedValue({ id: 1, anio: 2024, estado: 'PRESENTADO', folio_presentacion: '2024-DJ1835-000001' });
        render(<Dj1835 />);
        const input = await screen.findByLabelText(/Folio de presentación SII/i);
        fireEvent.change(input, { target: { value: '2024-DJ1835-000001' } });
        const btn = screen.getByRole('button', { name: /Confirmar presentada/i });
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(dj1835.confirmarPresentacion).toHaveBeenCalledWith(1, '2024-DJ1835-000001');
            expect(screen.getByText(/Presentación ante el SII confirmada correctamente/i)).toBeTruthy();
        });
    });

    it('handleConfirmarPresentacion muestra mensaje de error cuando falla', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'VALIDADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        dj1835.confirmarPresentacion.mockRejectedValue({ response: { data: { mensaje: 'Folio inválido' } } });
        render(<Dj1835 />);
        const btn = await screen.findByRole('button', { name: /Confirmar presentada/i });
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(screen.getByText(/Folio inválido/i)).toBeTruthy();
        });
    });

    it('oculta acciones de procesar cuando no hay permiso', async () => {
        vi.doMock('../../../Contextos/Permisos', () => ({
            usePermisos: () => ({ tienePermiso: (p) => p === 'tributario.ver' }),
        }));
        vi.resetModules();
        const { default: Dj1835SinPermiso } = await import('./Dj1835');
        const { dj1835: dj1835Mock } = await import('../Servicios/tributarioApi');
        dj1835Mock.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1835SinPermiso />);
        await waitFor(() => {
            expect(screen.getAllByText('2024').length).toBeGreaterThan(0);
        });
        expect(screen.queryByRole('button', { name: /Generar DJ 1835/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /Validar/i })).toBeNull();
        vi.doUnmock('../../../Contextos/Permisos');
    });

    it('clic en una fila del historial selecciona ese envío como activo', async () => {
        dj1835.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 5, folio_presentacion: null, presentado_at: null },
            { id: 2, anio: 2023, estado: 'PRESENTADO', cantidad_registros: 3, folio_presentacion: 'F-2023', presentado_at: '2023-12-01' },
        ]);
        render(<Dj1835 />);
        await waitFor(() => expect(screen.getAllByText('2024').length).toBeGreaterThan(0));
        const celda2023 = screen.getAllByText('2023').find((el) => el.tagName === 'TD');
        fireEvent.click(celda2023);
        await waitFor(() => {
            expect(screen.getByText('DJ 1835 — Año 2023')).toBeTruthy();
        });
    });

    it('boton Actualizar recarga el historial', async () => {
        dj1835.listar.mockResolvedValue([]);
        render(<Dj1835 />);
        await waitFor(() => expect(screen.getByText(/No hay declaraciones DJ 1835/i)).toBeTruthy());
        fireEvent.click(screen.getByText('Actualizar'));
        await waitFor(() => {
            expect(dj1835.listar).toHaveBeenCalledTimes(2);
        });
    });
});
