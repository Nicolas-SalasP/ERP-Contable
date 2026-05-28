import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { renderHook, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../Servicios/siiApi', () => ({
    default: {
        facturas: {
            obtenerEstado: vi.fn(),
        },
    },
}));

import siiApi from '../Servicios/siiApi';
import useEstadoSii from './useEstadoSii';

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

describe('useEstadoSii', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('carga estado inicial y expone data + cargando=false', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: { factura_id: 1, tiene_dte: true, estado: 'ACEPTADO', es_pollable: false, es_terminal: true },
        });

        const { result } = renderHook(() => useEstadoSii(1));

        expect(result.current.cargando).toBe(true);
        await waitFor(() => {
            expect(result.current.cargando).toBe(false);
        });
        expect(result.current.data?.estado).toBe('ACEPTADO');
        expect(result.current.error).toBeNull();
    });

    it('no llama al backend si facturaId es null', async () => {
        const { result } = renderHook(() => useEstadoSii(null));
        await waitFor(() => {
            expect(result.current.cargando).toBe(false);
        });
        expect(siiApi.facturas.obtenerEstado).not.toHaveBeenCalled();
    });

    it('captura error y lo expone sin tirar', async () => {
        siiApi.facturas.obtenerEstado.mockRejectedValue(new Error('boom'));

        const { result } = renderHook(() => useEstadoSii(42));

        await waitFor(() => {
            expect(result.current.cargando).toBe(false);
        });
        expect(result.current.error).toBeDefined();
        expect(result.current.error?.message).toBe('boom');
        expect(result.current.data).toBeNull();
    });

    it('si estado es pollable arma intervalo de 10s y vuelve a llamar', async () => {
        vi.useFakeTimers();

        siiApi.facturas.obtenerEstado
            .mockResolvedValueOnce({
                data: { factura_id: 1, tiene_dte: true, estado: 'ENVIADO_SII', es_pollable: true, es_terminal: false },
            })
            .mockResolvedValueOnce({
                data: { factura_id: 1, tiene_dte: true, estado: 'ACEPTADO', es_pollable: false, es_terminal: true },
            });

        const { result } = renderHook(() => useEstadoSii(1));

        // Resolver la primera promesa.
        await vi.waitFor(() => {
            expect(siiApi.facturas.obtenerEstado).toHaveBeenCalledTimes(1);
        });
        // Permitir que el useEffect setee el setInterval.
        await act(async () => {
            await Promise.resolve();
        });

        // Avanzar 10 segundos para disparar el polling.
        await act(async () => {
            await vi.advanceTimersByTimeAsync(10_000);
        });

        expect(siiApi.facturas.obtenerEstado.mock.calls.length).toBeGreaterThanOrEqual(2);
        expect(result.current.data?.estado).toBe('ACEPTADO');
    });

    it('si estado NO es pollable NO arma intervalo', async () => {
        vi.useFakeTimers();

        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: { factura_id: 1, tiene_dte: true, estado: 'ACEPTADO', es_pollable: false, es_terminal: true },
        });

        renderHook(() => useEstadoSii(1));

        await vi.waitFor(() => {
            expect(siiApi.facturas.obtenerEstado).toHaveBeenCalledTimes(1);
        });
        // Avanzar 30s: no debe haber llamadas adicionales.
        await act(async () => {
            await vi.advanceTimersByTimeAsync(30_000);
        });

        expect(siiApi.facturas.obtenerEstado).toHaveBeenCalledTimes(1);
    });

    it('recargar() vuelve a invocar al backend manualmente', async () => {
        siiApi.facturas.obtenerEstado.mockResolvedValue({
            data: { factura_id: 1, tiene_dte: true, estado: 'ACEPTADO', es_pollable: false, es_terminal: true },
        });

        const { result } = renderHook(() => useEstadoSii(1));

        await waitFor(() => {
            expect(result.current.cargando).toBe(false);
        });

        await act(async () => {
            await result.current.recargar();
        });

        expect(siiApi.facturas.obtenerEstado).toHaveBeenCalledTimes(2);
    });
});
