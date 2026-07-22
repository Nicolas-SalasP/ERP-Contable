import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => ({
        tienePermiso: (p) => ['tributario.ver', 'contabilidad.dj.procesar'].includes(p),
    }),
}));

vi.mock('../Servicios/tributarioApi', () => ({
    dj1926: {
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

import { dj1926 } from '../Servicios/tributarioApi';
import Dj1926 from './Dj1926';

afterEach(cleanup);

describe('Dj1926 (vista)', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('renderiza el título de la declaración', async () => {
        dj1926.listar.mockResolvedValue([]);
        render(<Dj1926 />);
        await waitFor(() => {
            expect(screen.getAllByText(/DJ 1926/i).length).toBeGreaterThan(0);
        });
    });

    it('muestra estado vacío cuando no hay envíos', async () => {
        dj1926.listar.mockResolvedValue([]);
        render(<Dj1926 />);
        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1926/i)).toBeTruthy();
        });
    });

    it('muestra historial cuando listar retorna envíos', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1926 />);
        await waitFor(() => {
            expect(screen.getAllByText('2024').length).toBeGreaterThan(0);
        });
    });

    it('BadgeEstado renderiza estado GENERADO', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1926 />);
        await waitFor(() => {
            expect(screen.getAllByText('GENERADO').length).toBeGreaterThan(0);
        });
    });

    it('handleGenerar llama a dj1926.generar con el año seleccionado', async () => {
        dj1926.listar.mockResolvedValue([]);
        dj1926.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 4, estado: 'GENERADO' });

        render(<Dj1926 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1926/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1926/i });
        await act(async () => { fireEvent.click(btn); });

        expect(dj1926.generar).toHaveBeenCalledWith(new Date().getFullYear() - 1);
    });

    it('muestra mensaje de éxito al generar', async () => {
        dj1926.listar.mockResolvedValue([]);
        dj1926.generar.mockResolvedValue({ id: 2, anio: 2024, cantidad_registros: 4, estado: 'GENERADO' });

        render(<Dj1926 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1926/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1926/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            // Success message: "DJ 1926 año XXXX generada con N cuenta(s) rechazada(s)."
            expect(screen.getByText(/DJ 1926 año/i)).toBeTruthy();
        });
    });

    it('muestra mensaje de error cuando generar falla', async () => {
        dj1926.listar.mockResolvedValue([]);
        dj1926.generar.mockRejectedValue({ response: { data: { mensaje: 'Sin gastos rechazados' } } });

        render(<Dj1926 />);

        await waitFor(() => {
            expect(screen.getByText(/No hay declaraciones DJ 1926/i)).toBeTruthy();
        });

        const btn = screen.getByRole('button', { name: /Generar DJ 1926/i });
        await act(async () => { fireEvent.click(btn); });

        await waitFor(() => {
            expect(screen.getByText(/Sin gastos rechazados/i)).toBeTruthy();
        });
    });

    it('formatFecha devuelve — para fecha nula', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1926 />);
        await waitFor(() => {
            const dashes = screen.getAllByText('—');
            expect(dashes.length).toBeGreaterThan(0);
        });
    });

    it('el selector de año incluye el año anterior al actual', async () => {
        dj1926.listar.mockResolvedValue([]);
        render(<Dj1926 />);
        await waitFor(() => {
            expect(screen.getByDisplayValue(String(new Date().getFullYear() - 1))).toBeTruthy();
        });
    });

    it('muestra mensaje de error cuando falla el historial', async () => {
        dj1926.listar.mockRejectedValue({ response: { data: { message: 'Error del servidor' } } });
        render(<Dj1926 />);
        await waitFor(() => {
            expect(screen.getByText(/Error del servidor/i)).toBeTruthy();
        });
    });

    it('handleValidar llama a dj1926.validar y muestra mensaje de éxito', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        dj1926.validar.mockResolvedValue({ envio: { id: 1, anio: 2024, estado: 'VALIDADO', cantidad_registros: 4 } });
        render(<Dj1926 />);
        const btn = await screen.findByRole('button', { name: /Validar/i });
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(dj1926.validar).toHaveBeenCalledWith(1);
            expect(screen.getByText(/DJ 1926 validada correctamente/i)).toBeTruthy();
        });
    });

    it('handleValidar muestra mensaje de error cuando falla', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        dj1926.validar.mockRejectedValue({ response: { data: { mensaje: 'Faltan cuentas marcadas' } } });
        render(<Dj1926 />);
        const btn = await screen.findByRole('button', { name: /Validar/i });
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(screen.getByText(/Faltan cuentas marcadas/i)).toBeTruthy();
        });
    });

    it('handleDescargar crea el enlace de descarga y recarga el historial', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        const blob = new Blob(['contenido']);
        dj1926.descargar.mockResolvedValue(blob);
        global.URL.createObjectURL = vi.fn(() => 'blob:url');
        global.URL.revokeObjectURL = vi.fn();
        render(<Dj1926 />);
        const btn = (await screen.findAllByRole('button', { name: /Descargar \.txt/i }))[0];
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(dj1926.descargar).toHaveBeenCalledWith(1);
            expect(global.URL.createObjectURL).toHaveBeenCalledWith(blob);
            expect(dj1926.listar).toHaveBeenCalledTimes(2);
        });
    });

    it('handleDescargar muestra mensaje de error cuando falla', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        dj1926.descargar.mockRejectedValue(new Error('fallo de red'));
        render(<Dj1926 />);
        const btn = (await screen.findAllByRole('button', { name: /Descargar \.txt/i }))[0];
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(screen.getByText(/Error al descargar el archivo de la DJ 1926/i)).toBeTruthy();
        });
    });

    it('handleConfirmarPresentacion envía el folio y muestra mensaje de éxito', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'VALIDADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        dj1926.confirmarPresentacion.mockResolvedValue({ id: 1, anio: 2024, estado: 'PRESENTADO', folio_presentacion: '2024-DJ1926-000001' });
        render(<Dj1926 />);
        const input = await screen.findByLabelText(/Folio de presentación SII/i);
        fireEvent.change(input, { target: { value: '2024-DJ1926-000001' } });
        const btn = screen.getByRole('button', { name: /Confirmar presentada/i });
        await act(async () => { fireEvent.click(btn); });
        await waitFor(() => {
            expect(dj1926.confirmarPresentacion).toHaveBeenCalledWith(1, '2024-DJ1926-000001');
            expect(screen.getByText(/Presentación ante el SII confirmada correctamente/i)).toBeTruthy();
        });
    });

    it('handleConfirmarPresentacion muestra mensaje de error cuando falla', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'VALIDADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        dj1926.confirmarPresentacion.mockRejectedValue({ response: { data: { mensaje: 'Folio inválido' } } });
        render(<Dj1926 />);
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
        const { default: Dj1926SinPermiso } = await import('./Dj1926');
        const { dj1926: dj1926Mock } = await import('../Servicios/tributarioApi');
        dj1926Mock.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
        ]);
        render(<Dj1926SinPermiso />);
        await waitFor(() => {
            expect(screen.getAllByText('2024').length).toBeGreaterThan(0);
        });
        expect(screen.queryByRole('button', { name: /Generar DJ 1926/i })).toBeNull();
        expect(screen.queryByRole('button', { name: /Validar/i })).toBeNull();
        vi.doUnmock('../../../Contextos/Permisos');
    });

    it('clic en una fila del historial selecciona ese envío como activo', async () => {
        dj1926.listar.mockResolvedValue([
            { id: 1, anio: 2024, estado: 'GENERADO', cantidad_registros: 4, folio_presentacion: null, presentado_at: null },
            { id: 2, anio: 2023, estado: 'PRESENTADO', cantidad_registros: 2, folio_presentacion: 'F-2023', presentado_at: '2023-12-01' },
        ]);
        render(<Dj1926 />);
        await waitFor(() => expect(screen.getAllByText('2024').length).toBeGreaterThan(0));
        const celda2023 = screen.getAllByText('2023').find((el) => el.tagName === 'TD');
        fireEvent.click(celda2023);
        await waitFor(() => {
            expect(screen.getByText('DJ 1926 — Año 2023')).toBeTruthy();
        });
    });

    it('boton Actualizar recarga el historial', async () => {
        dj1926.listar.mockResolvedValue([]);
        render(<Dj1926 />);
        await waitFor(() => expect(screen.getByText(/No hay declaraciones DJ 1926/i)).toBeTruthy());
        fireEvent.click(screen.getByText('Actualizar'));
        await waitFor(() => {
            expect(dj1926.listar).toHaveBeenCalledTimes(2);
        });
    });
});
