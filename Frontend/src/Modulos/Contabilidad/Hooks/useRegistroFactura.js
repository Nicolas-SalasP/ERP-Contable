import { useState, useEffect, useRef, useCallback } from 'react';
import { api } from '../../../Configuracion/api';
import { logger } from '../../../Configuracion/logger';

export function useRegistroFactura({ currentStep, proveedorId } = {}) {
    const [listaProveedores, setListaProveedores] = useState([]);
    const [cuentasDisponibles, setCuentasDisponibles] = useState([]);
    const [loading, setLoading] = useState(false);

    const [busqueda, setBusqueda] = useState('');
    const [sugerencias, setSugerencias] = useState([]);
    const [mostrarSugerencias, setMostrarSugerencias] = useState(false);

    const searchRef = useRef(null);

    useEffect(() => {
        setLoading(true);
        api.get('/proveedores')
            .then(res => {
                if (res.success) setListaProveedores(res.data);
            })
            .catch(err => logger.error('Error cargando proveedores', err))
            .finally(() => setLoading(false));

        const handleClickOutside = (event) => {
            if (searchRef.current && !searchRef.current.contains(event.target)) {
                setMostrarSugerencias(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Carga lazy de cuentas bancarias cuando se llega al paso 2 con proveedor seleccionado
    useEffect(() => {
        if (currentStep === 2 && proveedorId) {
            api.get(`/cuentas-bancarias/proveedor/${proveedorId}`)
                .then(data => {
                    if (data.success) setCuentasDisponibles(data.data);
                })
                .catch(err => logger.error('Error cargando cuentas bancarias', err));
        }
    }, [currentStep, proveedorId]);

    const handleBusquedaChange = useCallback((e) => {
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
    }, [listaProveedores]);

    return {
        listaProveedores,
        cuentasDisponibles,
        loading,
        busqueda, setBusqueda,
        sugerencias,
        mostrarSugerencias, setMostrarSugerencias,
        searchRef,
        handleBusquedaChange,
    };
}

export default useRegistroFactura;
