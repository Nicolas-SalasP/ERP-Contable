import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent, cleanup } from '@testing-library/react';

vi.mock('../Servicios/siiApi', () => ({
    default: {
        facturas: {
            obtenerEstado: vi.fn(),
            reintentar: vi.fn(),
        },
    },
}));

import siiApi from '../Servicios/siiApi';
import EstadoSiiPanel from './EstadoSiiPanel';

afterEach(cleanup);

describe('EstadoSiiPanel', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza placeholder mientras carga', () => {
        siiApi.facturas.obtenerEstado.mockReturnValue(new Promise(() => {}));
        render(<EstadoSiiPanel facturaId={1} />);
        expect(screen.getByTestId('estado-sii-panel-cargando')).toBeDefined();
    });

    it('renderiza mensaje si no hay DTE asociado', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: { factura_id: 1, tiene_dte: false, es_pollable: false, es_terminal: false },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-sin-dte')).toBeDefined();
        });
    });

    it('renderiza panel completo con folio, track_id, ambiente y glosa humana', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'ACEPTADO',
                estado_glosa_humana: 'Aceptado por el SII',
                tipo_dte: 33, folio: 1234, track_id: 'TRK-XYZ',
                fecha_emision: '2026-05-10',
                fecha_envio_sii: '2026-05-10T15:30:00Z',
                ambiente: 'certificacion', glosa_sii: null,
                ultimo_evento: null,
                es_terminal: true, es_pollable: false,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-ACEPTADO')).toBeDefined();
        });
        expect(screen.getByTestId('estado-sii-panel-folio').textContent).toMatch(/1234/);
        expect(screen.getByTestId('estado-sii-panel-track-id').textContent).toMatch(/TRK-XYZ/);
        expect(screen.getByTestId('estado-sii-panel-ambiente').textContent).toMatch(/certificacion/);
        expect(screen.getByTestId('estado-sii-panel-glosa-humana').textContent).toMatch(/Aceptado por el SII/);
        expect(screen.getByTestId('estado-sii-panel-terminal')).toBeDefined();
        expect(screen.queryByTestId('estado-sii-panel-spinner-pollable')).toBeNull();
    });

    it('muestra spinner En seguimiento si es_pollable', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'ENVIADO_SII',
                estado_glosa_humana: 'Enviado al SII',
                tipo_dte: 33, folio: 99, track_id: 'T1',
                fecha_emision: '2026-05-10', fecha_envio_sii: null,
                ambiente: 'certificacion', glosa_sii: null,
                ultimo_evento: null,
                es_terminal: false, es_pollable: true,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-spinner-pollable')).toBeDefined();
        });
        expect(screen.queryByTestId('estado-sii-panel-terminal')).toBeNull();
    });

    it('muestra glosa SII en bloque rojo cuando esta presente', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'RECHAZADO',
                estado_glosa_humana: 'Rechazado por el SII',
                tipo_dte: 33, folio: 50, track_id: 'TX',
                fecha_emision: '2026-05-10', fecha_envio_sii: '2026-05-10T15:30:00Z',
                ambiente: 'certificacion',
                glosa_sii: 'Schema invalido: tag DTE faltante',
                ultimo_evento: { estado_anterior: 'ENVIADO_SII', estado_nuevo: 'RECHAZADO', fecha: '2026-05-10T15:35:00Z' },
                es_terminal: true, es_pollable: false,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        const glosa = await screen.findByTestId('estado-sii-panel-glosa-sii');
        expect(glosa.textContent).toMatch(/Schema invalido/);
        expect(screen.getByTestId('estado-sii-panel-ultimo-evento').textContent).toMatch(/ENVIADO_SII/);
    });

    it('boton refrescar llama a recargar (segunda llamada al api)', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'ACEPTADO',
                estado_glosa_humana: 'Aceptado', tipo_dte: 33, folio: 1, track_id: 'T',
                fecha_emision: '2026-05-10', fecha_envio_sii: null,
                ambiente: 'certificacion', glosa_sii: null, ultimo_evento: null,
                es_terminal: true, es_pollable: false,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        const boton = await screen.findByTestId('estado-sii-panel-refrescar');
        siiApi.facturas.obtenerEstado.mockClear();
        fireEvent.click(boton);
        await waitFor(() => {
            expect(siiApi.facturas.obtenerEstado).toHaveBeenCalledTimes(1);
        });
    });

    it('si la API falla, muestra el panel de error con boton reintentar', async () => {
        siiApi.facturas.obtenerEstado.mockRejectedValue(new Error('Network'));
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-error')).toBeDefined();
        });
        expect(screen.getByTestId('estado-sii-panel-reintentar')).toBeDefined();
    });

    // ====================================================================
    // F6.4 — boton condicional "Reintentar emision"
    // ====================================================================

    it('F6.4: boton reintentar visible si estado=BORRADOR', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'BORRADOR',
                estado_glosa_humana: 'Borrador',
                tipo_dte: 33, folio: 1, track_id: 'T',
                fecha_emision: '2026-05-10', fecha_envio_sii: null,
                ambiente: 'certificacion', glosa_sii: null, ultimo_evento: null,
                ultimo_envio_estado: null, ultimo_envio_estado_error: false,
                es_terminal: false, es_pollable: true,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-btn-reintentar')).toBeDefined();
        });
    });

    it('F6.4: boton reintentar visible si estado=FIRMADO', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'FIRMADO',
                estado_glosa_humana: 'Firmado', tipo_dte: 33, folio: 1, track_id: 'T',
                fecha_emision: '2026-05-10', fecha_envio_sii: null,
                ambiente: 'certificacion', glosa_sii: null, ultimo_evento: null,
                ultimo_envio_estado: null, ultimo_envio_estado_error: false,
                es_terminal: false, es_pollable: true,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-btn-reintentar')).toBeDefined();
        });
    });

    it('F6.4: boton reintentar visible si ENVIADO_SII y ultimo_envio_estado_error=true', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'ENVIADO_SII',
                estado_glosa_humana: 'Enviado al SII',
                tipo_dte: 33, folio: 99, track_id: 'TX',
                fecha_emision: '2026-05-10', fecha_envio_sii: '2026-05-10T15:30:00Z',
                ambiente: 'certificacion', glosa_sii: null, ultimo_evento: null,
                ultimo_envio_estado: 'ERROR_TRANSPORTE',
                ultimo_envio_estado_error: true,
                es_terminal: false, es_pollable: true,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-btn-reintentar')).toBeDefined();
        });
    });

    it('F6.4: boton reintentar OCULTO si estado=ACEPTADO (terminal)', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'ACEPTADO',
                estado_glosa_humana: 'Aceptado', tipo_dte: 33, folio: 1, track_id: 'T',
                fecha_emision: '2026-05-10', fecha_envio_sii: null,
                ambiente: 'certificacion', glosa_sii: null, ultimo_evento: null,
                ultimo_envio_estado: null, ultimo_envio_estado_error: false,
                es_terminal: true, es_pollable: false,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-ACEPTADO')).toBeDefined();
        });
        expect(screen.queryByTestId('estado-sii-panel-btn-reintentar')).toBeNull();
    });

    it('F6.4: boton reintentar OCULTO si ENVIADO_SII en proceso normal (sin error)', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'ENVIADO_SII',
                estado_glosa_humana: 'En proceso',
                tipo_dte: 33, folio: 1, track_id: 'T',
                fecha_emision: '2026-05-10', fecha_envio_sii: '2026-05-10T15:30:00Z',
                ambiente: 'certificacion', glosa_sii: null, ultimo_evento: null,
                ultimo_envio_estado: 'ENVIADO', ultimo_envio_estado_error: false,
                es_terminal: false, es_pollable: true,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-ENVIADO_SII')).toBeDefined();
        });
        expect(screen.queryByTestId('estado-sii-panel-btn-reintentar')).toBeNull();
    });

    it('F6.4: boton reintentar visible en rama sin DTE (factura sin emitir)', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: { factura_id: 1, tiene_dte: false, es_pollable: false, es_terminal: false },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-panel-sin-dte')).toBeDefined();
        });
        expect(screen.getByTestId('estado-sii-panel-btn-reintentar')).toBeDefined();
    });

    it('F6.4: click en boton reintentar abre el modal', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: {
                factura_id: 1, tiene_dte: true, estado: 'BORRADOR',
                estado_glosa_humana: 'Borrador',
                tipo_dte: 33, folio: 1, track_id: 'T',
                fecha_emision: '2026-05-10', fecha_envio_sii: null,
                ambiente: 'certificacion', glosa_sii: null, ultimo_evento: null,
                ultimo_envio_estado: null, ultimo_envio_estado_error: false,
                es_terminal: false, es_pollable: true,
            },
        });
        render(<EstadoSiiPanel facturaId={1} />);
        const boton = await screen.findByTestId('estado-sii-panel-btn-reintentar');
        fireEvent.click(boton);
        await waitFor(() => {
            expect(screen.getByTestId('modal-reintentar-sii')).toBeDefined();
        });
    });
});
