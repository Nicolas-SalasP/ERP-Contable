import { useCallback, useEffect, useRef, useState } from 'react';
import siiApi from '../Servicios/siiApi';

/**
 * Hook que obtiene y mantiene actualizado el estado SII de una factura, con polling cada 10s mientras el estado sea pollable.
 * @param {number|null} facturaId
 * @returns {{
 *   data: object|null,
 *   cargando: boolean,
 *   error: Error|null,
 *   recargar: () => Promise<object|null>,
 * }}
 */
export function useEstadoSii(facturaId) {
    const [data, setData] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const mounted = useRef(true);

    const cargar = useCallback(async () => {
        if (!facturaId) {
            return null;
        }
        try {
            const respuesta = await siiApi.facturas.obtenerEstado(facturaId);
            const payload = respuesta?.data ?? null;
            if (!mounted.current) return payload;
            setData(payload);
            setError(null);
            setCargando(false);
            return payload;
        } catch (err) {
            if (!mounted.current) return null;
            setError(err);
            setCargando(false);
            return null;
        }
    }, [facturaId]);

    useEffect(() => {
        mounted.current = true;
        setCargando(true);
        setData(null);
        setError(null);

        if (!facturaId) {
            setCargando(false);
            return undefined;
        }

        let cancelled = false;
        // Interval local (no useRef compartido) para que el cleanup no pise el de otra corrida cuando cambia facturaId.
        let intervalId = null;

        const iniciar = async () => {
            const inicial = await cargar();
            if (cancelled || !mounted.current) return;

            if (inicial?.es_pollable) {
                intervalId = setInterval(async () => {
                    const nuevo = await cargar();
                    if (!nuevo?.es_pollable && intervalId) {
                        clearInterval(intervalId);
                        intervalId = null;
                    }
                }, 10_000);
            }
        };

        iniciar();

        return () => {
            cancelled = true;
            mounted.current = false;
            if (intervalId) {
                clearInterval(intervalId);
                intervalId = null;
            }
        };
    }, [facturaId, cargar]);

    return { data, cargando, error, recargar: cargar };
}

export default useEstadoSii;
