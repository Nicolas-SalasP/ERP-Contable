import { useState, useEffect, useCallback } from 'react';
import { api } from '../../../Configuracion/api';
import { logger } from '../../../Configuracion/logger';

export const INITIAL_FORM_DATA = {
    rut: '',
    razon_social: '',
    direccion: '',
    email: '',
    telefono: '',
    logo_path: '',
    color_primario: '#10b981',
    regimen_tributario: '14_D3',
};

export function usePerfilEmpresa() {
    const [formData, setFormData] = useState(INITIAL_FORM_DATA);
    const [bancos, setBancos] = useState([]);
    const [centros, setCentros] = useState([]);
    const [listaBancos, setListaBancos] = useState([]);
    const [loading, setLoading] = useState(true);

    const cargarPerfil = useCallback(async () => {
        setLoading(true);
        try {
            const res = await api.get('/empresas/perfil');
            if (res.success && res.data) {
                setFormData({
                    rut: res.data.rut || '',
                    razon_social: res.data.razon_social || '',
                    direccion: res.data.direccion || '',
                    email: res.data.email || '',
                    telefono: res.data.telefono || '',
                    logo_path: res.data.logo_path || '',
                    color_primario: res.data.color_primario || '#10b981',
                    regimen_tributario: res.data.regimen_tributario || '14_D3',
                });
                setBancos(res.data.bancos || []);
                setCentros(res.data.centros_costo || []);
            }
        } catch (error) {
            logger.error('Error al cargar perfil de empresa', error);
        } finally {
            setLoading(false);
        }
    }, []);

    const cargarCatalogoBancos = useCallback(async () => {
        try {
            const res = await api.get('/empresas/catalogo-bancos');
            if (res.success) setListaBancos(res.data);
        } catch (error) {
            logger.warn('No se pudo cargar el catalogo de bancos', error);
        }
    }, []);

    useEffect(() => {
        cargarPerfil();
        cargarCatalogoBancos();
    }, [cargarPerfil, cargarCatalogoBancos]);

    return {
        formData, setFormData,
        bancos, setBancos,
        centros, setCentros,
        listaBancos,
        loading,
        recargar: cargarPerfil,
    };
}

export default usePerfilEmpresa;
