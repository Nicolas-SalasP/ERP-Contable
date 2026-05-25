import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';

vi.mock('../Servicios/siiApi', () => ({
    default: {
        facturas: {
            obtenerEstado: vi.fn(),
        },
    },
}));

import siiApi from '../Servicios/siiApi';
import EstadoSiiBadge from './EstadoSiiBadge';

afterEach(cleanup);

describe('EstadoSiiBadge', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza estado cargando mientras la request esta en vuelo', () => {
        siiApi.facturas.obtenerEstado.mockReturnValue(new Promise(() => {}));
        render(<EstadoSiiBadge facturaId={1} />);
        expect(screen.getByTestId('estado-sii-cargando')).toBeDefined();
    });

    it('renderiza estado SIN_DTE si tiene_dte=false', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: { factura_id: 1, tiene_dte: false, es_pollable: false, es_terminal: false },
        });
        render(<EstadoSiiBadge facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-sin-dte')).toBeDefined();
        });
    });

    it('renderiza estado ACEPTADO con clase de color verde', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'ACEPTADO',
                estado_glosa_humana: 'Aceptado por el SII',
                folio: 1234, es_terminal: true, es_pollable: false,
            },
        });
        render(<EstadoSiiBadge facturaId={1} />);
        const badge = await screen.findByTestId('estado-sii-ACEPTADO');
        expect(badge).toBeDefined();
        expect(badge.className).toMatch(/bg-green-100/);
        // Folio inline
        expect(badge.textContent).toMatch(/#1234/);
    });

    it('renderiza estado RECHAZADO con clase de color rojo', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'RECHAZADO',
                estado_glosa_humana: 'Rechazado por el SII',
                folio: null, es_terminal: true, es_pollable: false,
            },
        });
        render(<EstadoSiiBadge facturaId={1} />);
        const badge = await screen.findByTestId('estado-sii-RECHAZADO');
        expect(badge.className).toMatch(/bg-red-100/);
    });

    it('renderiza estado ENVIADO_SII con animacion pulse (pollable)', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'ENVIADO_SII',
                estado_glosa_humana: 'Enviado al SII, esperando respuesta',
                folio: 99, es_terminal: false, es_pollable: true,
            },
        });
        render(<EstadoSiiBadge facturaId={1} />);
        const badge = await screen.findByTestId('estado-sii-ENVIADO_SII');
        expect(badge.className).toMatch(/animate-pulse/);
    });

    it('si la API falla, muestra el badge de error', async () => {
        siiApi.facturas.obtenerEstado.mockRejectedValue(new Error('500'));
        render(<EstadoSiiBadge facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-error')).toBeDefined();
        });
    });
});
