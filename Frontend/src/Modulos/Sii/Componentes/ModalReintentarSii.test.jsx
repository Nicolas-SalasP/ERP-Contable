import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

vi.mock('../Servicios/siiApi', () => ({
    default: {
        facturas: {
            reintentar: vi.fn(),
        },
    },
}));

import siiApi from '../Servicios/siiApi';
import ModalReintentarSii from './ModalReintentarSii';

afterEach(cleanup);

describe('ModalReintentarSii', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('render modal con campos cuando abierto=true', () => {
        render(
            <ModalReintentarSii
                abierto={true}
                facturaId={42}
                resumenEstado={{ estado: 'BORRADOR', ultimo_envio_estado: null }}
                onCerrar={vi.fn()}
                onReintentoExitoso={vi.fn()}
            />
        );
        expect(screen.getByTestId('modal-reintentar-sii')).toBeDefined();
        expect(screen.getByTestId('razon-reintento-textarea')).toBeDefined();
        expect(screen.getByTestId('btn-confirmar-reintento')).toBeDefined();
        expect(screen.getByTestId('btn-cancelar-reintento')).toBeDefined();
        expect(screen.getByTestId('reintentar-sii-estado-actual').textContent).toMatch(/BORRADOR/);
    });

    it('no renderiza nada si abierto=false', () => {
        render(<ModalReintentarSii abierto={false} facturaId={1} onCerrar={vi.fn()} />);
        expect(screen.queryByTestId('modal-reintentar-sii')).toBeNull();
    });

    it('confirmar invoca siiApi.reintentar con razon', async () => {
        siiApi.facturas.reintentar.mockResolvedValue({
            data: { factura_id: 42, accion_encolada: 'reanudar_envio', mensaje: 'OK' },
        });
        const onReintentoExitoso = vi.fn();
        const onCerrar = vi.fn();

        render(
            <ModalReintentarSii
                abierto={true}
                facturaId={42}
                onCerrar={onCerrar}
                onReintentoExitoso={onReintentoExitoso}
            />
        );

        fireEvent.change(screen.getByTestId('razon-reintento-textarea'), {
            target: { value: 'red intermitente' },
        });
        fireEvent.click(screen.getByTestId('btn-confirmar-reintento'));

        await waitFor(() => {
            expect(siiApi.facturas.reintentar).toHaveBeenCalledWith(42, { razon: 'red intermitente' });
        });
        await waitFor(() => {
            expect(onReintentoExitoso).toHaveBeenCalledTimes(1);
            expect(onCerrar).toHaveBeenCalledTimes(1);
        });
    });

    it('confirmar sin razon envia payload vacio (no razon undefined)', async () => {
        siiApi.facturas.reintentar.mockResolvedValue({
            data: { factura_id: 42, accion_encolada: 'reanudar_firma', mensaje: 'OK' },
        });

        render(
            <ModalReintentarSii
                abierto={true}
                facturaId={42}
                onCerrar={vi.fn()}
                onReintentoExitoso={vi.fn()}
            />
        );
        fireEvent.click(screen.getByTestId('btn-confirmar-reintento'));

        await waitFor(() => {
            expect(siiApi.facturas.reintentar).toHaveBeenCalledWith(42, {});
        });
    });

    it('422 muestra mensaje inline y NO cierra el modal', async () => {
        siiApi.facturas.reintentar.mockRejectedValue({
            status: 422,
            message: 'DTE en estado terminal',
            raw: {
                error: {
                    razon: 'estado_terminal',
                    mensaje: 'DTE en estado terminal ACEPTADO no se puede reintentar.',
                    estado_actual: 'ACEPTADO',
                },
            },
        });
        const onCerrar = vi.fn();
        const onReintentoExitoso = vi.fn();

        render(
            <ModalReintentarSii
                abierto={true}
                facturaId={42}
                onCerrar={onCerrar}
                onReintentoExitoso={onReintentoExitoso}
            />
        );
        fireEvent.click(screen.getByTestId('btn-confirmar-reintento'));

        const inline = await screen.findByTestId('reintentar-sii-error-inline');
        expect(inline.textContent).toMatch(/ACEPTADO/);
        expect(onCerrar).not.toHaveBeenCalled();
        expect(onReintentoExitoso).not.toHaveBeenCalled();
    });

    it('error generico (no 422) muestra mensaje inline', async () => {
        siiApi.facturas.reintentar.mockRejectedValue({
            status: 500,
            message: 'Internal Server Error',
        });
        render(
            <ModalReintentarSii
                abierto={true}
                facturaId={42}
                onCerrar={vi.fn()}
                onReintentoExitoso={vi.fn()}
            />
        );
        fireEvent.click(screen.getByTestId('btn-confirmar-reintento'));
        const inline = await screen.findByTestId('reintentar-sii-error-inline');
        expect(inline.textContent).toMatch(/Internal Server Error/);
    });

    it('cancelar cierra sin invocar siiApi', () => {
        const onCerrar = vi.fn();
        render(
            <ModalReintentarSii
                abierto={true}
                facturaId={42}
                onCerrar={onCerrar}
                onReintentoExitoso={vi.fn()}
            />
        );
        fireEvent.click(screen.getByTestId('btn-cancelar-reintento'));
        expect(onCerrar).toHaveBeenCalledTimes(1);
        expect(siiApi.facturas.reintentar).not.toHaveBeenCalled();
    });

    it('contador de caracteres se actualiza con la razon', () => {
        render(
            <ModalReintentarSii
                abierto={true}
                facturaId={1}
                onCerrar={vi.fn()}
                onReintentoExitoso={vi.fn()}
            />
        );
        fireEvent.change(screen.getByTestId('razon-reintento-textarea'), {
            target: { value: 'hola' },
        });
        expect(screen.getByTestId('razon-reintento-contador').textContent).toMatch(/4\s*\/\s*200/);
    });
});
