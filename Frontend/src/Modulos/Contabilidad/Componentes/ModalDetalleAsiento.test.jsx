import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';
import ModalDetalleAsiento from './ModalDetalleAsiento';

// ── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn() },
}));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn() },
}));

// Importar DESPUÉS de los mocks para que vi.mock ya esté activo
import { api } from '../../../Configuracion/api';

// ── Datos de prueba ──────────────────────────────────────────────────────────

const asientoMock = {
    cabecera: {
        numero_comprobante: 'AC-2026-001',
        fecha: '2026-06-15',
        tipo_asiento: 'diario',
        estado: 'MAYORIZADO',
        glosa: 'Compra equipos',
    },
    detalles: [
        {
            id: 1,
            cuenta_contable: '1100',
            cuenta_nombre: 'Caja',
            descripcion: 'Ingreso',
            debe: 100000,
            haber: 0,
        },
        {
            id: 2,
            cuenta_contable: '3100',
            cuenta_nombre: 'Capital',
            descripcion: 'Aporte',
            debe: 0,
            haber: 100000,
        },
    ],
};

const respuestaExitosa = { success: true, data: asientoMock };

// ── Suite ────────────────────────────────────────────────────────────────────

afterEach(cleanup);

describe('ModalDetalleAsiento', () => {
    beforeEach(() => {
        vi.resetAllMocks();
    });

    it('no renderiza cuando isOpen=false', () => {
        api.get.mockResolvedValueOnce(respuestaExitosa);
        render(
            <ModalDetalleAsiento
                isOpen={false}
                asientoId={1}
                onClose={vi.fn()}
            />
        );
        expect(screen.queryByText('AC-2026-001')).toBeNull();
        expect(screen.queryByText('Comprobante Contable')).toBeNull();
    });

    it('muestra spinner cuando isOpen=true y la carga está en curso', () => {
        // Promesa que nunca resuelve → simula carga indefinida
        api.get.mockImplementation(() => new Promise(() => {}));
        render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={1}
                onClose={vi.fn()}
            />
        );
        expect(screen.getByText('Cargando comprobante…')).toBeTruthy();
    });

    it('muestra número de comprobante tras carga exitosa', async () => {
        api.get.mockResolvedValueOnce(respuestaExitosa);
        render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={1}
                onClose={vi.fn()}
            />
        );
        await waitFor(() =>
            expect(screen.getByText('AC-2026-001')).toBeTruthy()
        );
    });

    it('muestra la glosa del asiento tras carga exitosa', async () => {
        api.get.mockResolvedValueOnce(respuestaExitosa);
        render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={1}
                onClose={vi.fn()}
            />
        );
        await waitFor(() =>
            expect(screen.getByText(/"Compra equipos"/)).toBeTruthy()
        );
    });

    it('muestra línea de detalle con cuenta debe (1100 / Caja)', async () => {
        api.get.mockResolvedValueOnce(respuestaExitosa);
        render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={1}
                onClose={vi.fn()}
            />
        );
        await waitFor(() => {
            expect(screen.getByText('1100')).toBeTruthy();
            expect(screen.getByText('Caja')).toBeTruthy();
        });
    });

    it('muestra línea de detalle con cuenta haber (3100 / Capital)', async () => {
        api.get.mockResolvedValueOnce(respuestaExitosa);
        render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={1}
                onClose={vi.fn()}
            />
        );
        await waitFor(() => {
            expect(screen.getByText('3100')).toBeTruthy();
            expect(screen.getByText('Capital')).toBeTruthy();
        });
    });

    it('llama onClose al hacer click en el botón X (aria-label="Cerrar")', async () => {
        api.get.mockResolvedValueOnce(respuestaExitosa);
        const onClose = vi.fn();
        render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={1}
                onClose={onClose}
            />
        );
        // Esperar a que el modal esté completamente renderizado
        await waitFor(() => expect(screen.getByText('AC-2026-001')).toBeTruthy());
        // Usar getByLabelText para seleccionar específicamente el botón X (aria-label="Cerrar")
        fireEvent.click(screen.getByLabelText('Cerrar'));
        expect(onClose).toHaveBeenCalled();
    });

    it('llama onClose al hacer click en el botón "Cerrar" del pie', async () => {
        api.get.mockResolvedValueOnce(respuestaExitosa);
        const onClose = vi.fn();
        render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={1}
                onClose={onClose}
            />
        );
        await waitFor(() => expect(screen.getByText('AC-2026-001')).toBeTruthy());
        // El pie siempre tiene un botón con texto "Cerrar"
        const botonesCerrar = screen.getAllByRole('button', { name: /cerrar/i });
        fireEvent.click(botonesCerrar[botonesCerrar.length - 1]);
        expect(onClose).toHaveBeenCalled();
    });

    it('llama onClose al hacer click en el overlay de fondo', async () => {
        api.get.mockResolvedValueOnce(respuestaExitosa);
        const onClose = vi.fn();
        const { container } = render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={1}
                onClose={onClose}
            />
        );
        await waitFor(() => expect(screen.getByText('AC-2026-001')).toBeTruthy());
        // El overlay es el primer div del portal (fixed inset-0)
        const overlay = container.firstChild;
        fireEvent.click(overlay);
        expect(onClose).toHaveBeenCalled();
    });

    it('muestra mensaje de error si api.get falla', async () => {
        api.get.mockRejectedValueOnce(new Error('Error de red'));
        render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={1}
                onClose={vi.fn()}
            />
        );
        await waitFor(() =>
            expect(
                screen.getByText('Error de conexión al cargar el comprobante.')
            ).toBeTruthy()
        );
    });

    it('no llama api.get cuando asientoId es null', () => {
        render(
            <ModalDetalleAsiento
                isOpen={true}
                asientoId={null}
                onClose={vi.fn()}
            />
        );
        expect(api.get).not.toHaveBeenCalled();
    });
});
