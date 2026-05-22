import { useState, useEffect, useRef, useCallback } from 'react';
import { api } from '../../../Configuracion/api';
import { logger } from '../../../Configuracion/logger';
export function useFacturasHistorial({ vistaActual = 1, onMenuClickOutside } = {}) {
    const [busqueda, setBusqueda] = useState('');
    const [filtroNumero, setFiltroNumero] = useState('');
    const [filtroEstado, setFiltroEstado] = useState('');

    const [facturas, setFacturas] = useState([]);
    const [loading, setLoading] = useState(false);
    const [searched, setSearched] = useState(false);
    const [pagination, setPagination] = useState({ page: 1, limit: 10, total: 0, totalPages: 0 });

    const [listaProveedores, setListaProveedores] = useState([]);
    const [sugerencias, setSugerencias] = useState([]);
    const [mostrarSugerencias, setMostrarSugerencias] = useState(false);

    const searchRef = useRef(null);

    useEffect(() => {
        api.get('/proveedores/catalogo')
            .then(res => { if (res.success) setListaProveedores(res.data); })
            .catch(err => logger.error('Error catalogo proveedores', err));

        const handleClickOutside = (event) => {
            if (searchRef.current && !searchRef.current.contains(event.target)) {
                setMostrarSugerencias(false);
            }
            if (onMenuClickOutside) onMenuClickOutside(event);
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [onMenuClickOutside]);

    const ejecutarBusqueda = useCallback(async (resetPage = false) => {
        setLoading(true);
        setSearched(true);
        setMostrarSugerencias(false);

        if (resetPage) setPagination(prev => ({ ...prev, page: 1 }));

        const params = new URLSearchParams();
        if (busqueda) params.append('search', busqueda);
        if (filtroNumero) params.append('num', filtroNumero);
        if (filtroEstado) params.append('estado', filtroEstado);
        params.append('page', resetPage ? 1 : pagination.page);
        params.append('limit', pagination.limit);

        try {
            const res = await api.get(`/facturas/historial?${params.toString()}`);
            if (res.success) {
                setFacturas(res.data);
                setPagination(prev => ({
                    ...prev,
                    total: res.pagination.total,
                    totalPages: res.pagination.totalPages,
                }));
            } else {
                setFacturas([]);
            }
        } catch (error) {
            logger.error(error);
        } finally {
            setLoading(false);
        }
    }, [busqueda, filtroNumero, filtroEstado, pagination.page, pagination.limit]);

    useEffect(() => {
        if (vistaActual !== 3) ejecutarBusqueda();
    }, [pagination.page, vistaActual]);

    const handleBusquedaChange = (e) => {
        const termino = e.target.value;
        setBusqueda(termino);
        if (termino.length > 0) {
            const matches = listaProveedores.filter(p =>
                p.razon_social.toLowerCase().includes(termino.toLowerCase()) ||
                (p.rut && p.rut.toLowerCase().includes(termino.toLowerCase())) ||
                p.codigo_interno.toString().includes(termino)
            );
            setSugerencias(matches);
            setMostrarSugerencias(true);
        } else {
            setSugerencias([]);
            setMostrarSugerencias(false);
        }
    };

    const seleccionarProveedor = (prov) => {
        setBusqueda(prov.razon_social);
        setMostrarSugerencias(false);
    };

    return {
        busqueda, setBusqueda,
        filtroNumero, setFiltroNumero,
        filtroEstado, setFiltroEstado,

        facturas, setFacturas,
        loading,
        searched,
        pagination, setPagination,

        listaProveedores,
        sugerencias,
        mostrarSugerencias, setMostrarSugerencias,
        searchRef,

        handleBusquedaChange,
        ejecutarBusqueda,
        seleccionarProveedor,
    };
}

export default useFacturasHistorial;
