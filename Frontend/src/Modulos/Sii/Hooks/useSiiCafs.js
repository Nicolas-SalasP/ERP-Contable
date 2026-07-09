import { useCallback, useEffect, useState } from 'react';
import siiApi from '../Servicios/siiApi';

/**
 * Hook que encapsula la carga y mutacion de CAFs de la empresa autenticada.
 *
 * @returns {{
 *   cafs: import('../Servicios/siiApi').CafResumen[],
 *   saldos: Record<string, import('../Servicios/siiApi').SaldoPorTipo>,
 *   cargando: boolean,
 *   filtroTipo: number|null,
 *   subiendo: boolean,
 *   revocando: boolean,
 *   cambiarFiltro: (tipo: number|null) => Promise<void>,
 *   subirCaf: (file: File) => Promise<import('../Servicios/siiApi').CafResumen|null>,
 *   revocarCaf: (id: number, motivo: string) => Promise<boolean>,
 *   recargar: () => Promise<void>,
 * }}
 */
const useSiiCafs = () => {
    const [cafs, setCafs] = useState([]);
    const [saldos, setSaldos] = useState({});
    const [cargando, setCargando] = useState(true);
    const [filtroTipo, setFiltroTipo] = useState(null);
    const [subiendo, setSubiendo] = useState(false);
    const [revocando, setRevocando] = useState(false);

    const cargar = useCallback(async (tipoFiltro = null) => {
        setCargando(true);
        try {
            const [respSaldos, respLista] = await Promise.all([
                siiApi.caf.saldos(),
                siiApi.caf.listar(tipoFiltro),
            ]);
            setSaldos(respSaldos?.data ?? {});
            setCafs(respLista?.data ?? []);
        } catch (_) {
            // api.js ya notifico al usuario (Swal global).
        } finally {
            setCargando(false);
        }
    }, []);

    const cambiarFiltro = useCallback(async (tipo) => {
        setFiltroTipo(tipo);
        await cargar(tipo);
    }, [cargar]);

    const subirCaf = useCallback(async (file) => {
        setSubiendo(true);
        try {
            const caf = await siiApi.caf.subir(file);
            await cargar(filtroTipo);
            return caf;
        } catch (_) {
            return null;
        } finally {
            setSubiendo(false);
        }
    }, [cargar, filtroTipo]);

    const revocarCaf = useCallback(async (id, motivo) => {
        setRevocando(true);
        try {
            await siiApi.caf.revocar(id, motivo);
            await cargar(filtroTipo);
            return true;
        } catch (_) {
            return false;
        } finally {
            setRevocando(false);
        }
    }, [cargar, filtroTipo]);

    useEffect(() => {
        cargar(null);
    }, [cargar]);

    return {
        cafs,
        saldos,
        cargando,
        filtroTipo,
        subiendo,
        revocando,
        cambiarFiltro,
        subirCaf,
        revocarCaf,
        recargar: () => cargar(filtroTipo),
    };
};

export default useSiiCafs;
