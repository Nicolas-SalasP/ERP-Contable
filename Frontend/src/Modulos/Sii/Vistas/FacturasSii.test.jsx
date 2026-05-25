import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent, cleanup } from '@testing-library/react';

vi.mock('../Servicios/siiApi', () => ({
    default: {
        facturas: {
            listar: vi.fn(),
            obtenerEstado: vi.fn(),
            obtener: vi.fn(),
        },
    },
}));

import siiApi from '../Servicios/siiApi';
import FacturasSii from './FacturasSii';

afterEach(cleanup);

const respuestaLista = (overrides = {}) => ({
    data: [
        {
            factura_id: 1, numero_factura: 'F-001', tipo_documento: 'FACTURA',
            fecha_emision: '2026-05-10', monto_bruto: 119000,
            cliente: { id: 1, rut: '11.111.111-1', razon_social: 'Cliente Uno' },
            estado_sii: {
                tiene_dte: true, dte_id: 99, estado: 'ACEPTADO',
                estado_glosa_humana: 'Aceptado por el SII',
                es_terminal: true, es_pollable: false, folio: 1234, track_id: 'TRK',
            },
        },
    ],
    paginacion: { total: 1, por_pagina: 25, pagina_actual: 1, ultima_pagina: 1 },
    ...overrides,
});

const estadoAceptado = {
    data: {
        factura_id: 1, tiene_dte: true, estado: 'ACEPTADO',
        estado_glosa_humana: 'Aceptado por el SII',
        tipo_dte: 33, folio: 1234, track_id: 'TRK',
        fecha_emision: '2026-05-10', fecha_envio_sii: '2026-05-10T15:30:00Z',
        ambiente: 'certificacion', glosa_sii: null, ultimo_evento: null,
        es_terminal: true, es_pollable: false,
    },
};

describe('FacturasSii (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        siiApi.facturas.listar.mockResolvedValue(respuestaLista());
        siiApi.facturas.obtenerEstado.mockResolvedValue(estadoAceptado);
    });

    it('render inicial llama a facturas.listar con por_pagina=25 y pagina=1', async () => {
        render(<FacturasSii />);
        await waitFor(() => {
            expect(siiApi.facturas.listar).toHaveBeenCalledTimes(1);
        });
        expect(siiApi.facturas.listar).toHaveBeenCalledWith({ por_pagina: 25, pagina: 1 });
    });

    it('lista filas de facturas con numero, cliente, monto y badge estado SII', async () => {
        render(<FacturasSii />);
        await waitFor(() => {
            expect(screen.getByTestId('fila-factura-1')).toBeDefined();
        });
        const fila = screen.getByTestId('fila-factura-1');
        expect(fila.textContent).toMatch(/F-001/);
        expect(fila.textContent).toMatch(/Cliente Uno/);
        // Badge dentro de la fila (EstadoSiiBadge usa data-testid="estado-sii-ACEPTADO")
        await waitFor(() => {
            expect(screen.getByTestId('estado-sii-ACEPTADO')).toBeDefined();
        });
    });

    it('empty state si lista vacia', async () => {
        siiApi.facturas.listar.mockResolvedValue({
            data: [],
            paginacion: { total: 0, por_pagina: 25, pagina_actual: 1, ultima_pagina: 1 },
        });
        render(<FacturasSii />);
        await waitFor(() => {
            expect(screen.getByTestId('facturas-sii-vacio')).toBeDefined();
        });
    });

    it('click en "Ver detalle" expande el EstadoSiiPanel y vuelve a colapsar', async () => {
        render(<FacturasSii />);
        const boton = await screen.findByTestId('btn-detalle-1');
        fireEvent.click(boton);
        await waitFor(() => {
            expect(screen.getByTestId('panel-factura-1')).toBeDefined();
        });
        // Toggle off.
        fireEvent.click(screen.getByTestId('btn-detalle-1'));
        await waitFor(() => {
            expect(screen.queryByTestId('panel-factura-1')).toBeNull();
        });
    });

    it('muestra error y boton reintentar si listar falla', async () => {
        siiApi.facturas.listar.mockRejectedValueOnce(new Error('Network down'));
        render(<FacturasSii />);
        await waitFor(() => {
            expect(screen.getByTestId('facturas-sii-error')).toBeDefined();
        });
        // Reintentar dispara segunda llamada.
        siiApi.facturas.listar.mockResolvedValueOnce(respuestaLista());
        fireEvent.click(screen.getByTestId('facturas-sii-reintentar'));
        await waitFor(() => {
            expect(siiApi.facturas.listar).toHaveBeenCalledTimes(2);
        });
    });
});
