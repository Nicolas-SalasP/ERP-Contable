import { useCallback, useEffect, useState } from 'react';
import siiApi from '../Servicios/siiApi';

/**
 * Hook que encapsula la carga y persistencia de la configuracion SII de la
 * empresa autenticada. Los errores HTTP se delegan al toast global de api.js.
 *
 * @returns {{
 *   configuracion: import('../Servicios/siiApi').ConfiguracionSii | null,
 *   cargando: boolean,
 *   guardando: boolean,
 *   recargar: () => Promise<void>,
 *   actualizar: (payload: Partial<import('../Servicios/siiApi').ConfiguracionSii>) => Promise<import('../Servicios/siiApi').ConfiguracionSii | null>,
 * }}
 */
const useSiiConfiguracion = () => {
    const [configuracion, setConfiguracion] = useState(null);
    const [cargando, setCargando] = useState(true);
    const [guardando, setGuardando] = useState(false);

    const cargar = useCallback(async () => {
        setCargando(true);
        try {
            const data = await siiApi.configuracion.obtener();
            setConfiguracion(data);
        } catch (_) {
            // El error ya fue notificado al usuario por api.js (Swal).
        } finally {
            setCargando(false);
        }
    }, []);

    const actualizar = useCallback(async (payload) => {
        setGuardando(true);
        try {
            const actualizada = await siiApi.configuracion.actualizar(payload);
            setConfiguracion(actualizada);
            return actualizada;
        } catch (_) {
            return null;
        } finally {
            setGuardando(false);
        }
    }, []);

    useEffect(() => {
        cargar();
    }, [cargar]);

    return { configuracion, cargando, guardando, recargar: cargar, actualizar };
};

export default useSiiConfiguracion;
