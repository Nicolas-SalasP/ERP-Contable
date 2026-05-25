import { useCallback, useEffect, useRef, useState } from 'react';
import siiApi from '../Servicios/siiApi';

/**
 * F6.3 — Hook que obtiene y mantiene actualizado el estado SII de una factura.
 *
 * Polling automatico cada 10s SOLO si el estado es pollable
 * (BORRADOR/FIRMADO/ENVIADO_SII/EN_PROCESO_SII/etc.). Se DETIENE solo al
 * alcanzar estado terminal (ACEPTADO/RECHAZADO/etc.) o si no hay DTE.
 *
 * Cleanup: el setInterval se limpia al desmontarse el componente o al
 * cambiar facturaId. Si una request esta en flight al desmontar, su
 * resultado se ignora gracias al guard `mounted.current`.
 *
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
    const intervalRef = useRef(null);
    const mounted = useRef(true);

    const cargar = useCallback(async () => {
        if (!facturaId) {
            return null;
        }
        try {
            const respuesta = await siiApi.facturas.obtenerEstado(facturaId);
            // api.get retorna body directamente; shape: { data: {...} }
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

        const iniciar = async () => {
            const inicial = await cargar();
            if (cancelled || !mounted.current) return;

            if (inicial?.es_pollable) {
                intervalRef.current = setInterval(async () => {
                    const nuevo = await cargar();
                    if (!nuevo?.es_pollable && intervalRef.current) {
                        clearInterval(intervalRef.current);
                        intervalRef.current = null;
                    }
                }, 10_000);
            }
        };

        iniciar();

        return () => {
            cancelled = true;
            mounted.current = false;
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
                intervalRef.current = null;
            }
        };
    }, [facturaId, cargar]);

    return { data, cargando, error, recargar: cargar };
}

export default useEstadoSii;
