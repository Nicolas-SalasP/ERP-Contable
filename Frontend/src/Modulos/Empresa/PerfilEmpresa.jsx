import React, { useState, useEffect } from 'react';
import AyudaModulo from '../../Componentes/AyudaModulo';
import EstadoCarga from '../../Componentes/EstadoCarga';
import { api, API_BASE_URL } from '../../Configuracion/api';
import { logger } from '../../Configuracion/logger';
import { validarIdentificador } from '../../Utilidades/identificadores';
import Swal from 'sweetalert2';
import ModalBancoEdicion from './Componentes/ModalBancoEdicion';
import ModalCentroEdicion from './Componentes/ModalCentroEdicion';
import PerfilEmpresaBancos from './Componentes/PerfilEmpresaBancos';
import PerfilEmpresaCentros from './Componentes/PerfilEmpresaCentros';
import PerfilEmpresaGeneral from './Componentes/PerfilEmpresaGeneral';
import { usePerfilEmpresa } from './Hooks/usePerfilEmpresa';

const validarRutChileno = (rut) => validarIdentificador(rut, 'CL');

const PerfilEmpresa = () => {
    // --- HOOK: capa de datos del perfil ---
    const {
        formData, setFormData,
        bancos, setBancos,
        centros, setCentros,
        listaBancos,
        loading,
        recargar: cargarPerfil,
    } = usePerfilEmpresa();

    // --- ESTADOS DE UI ---
    const [saving, setSaving] = useState(false);
    const [activeTab, setActiveTab] = useState('general');

    // --- ESTADOS DE IMAGEN ---
    const [logoPreview, setLogoPreview] = useState(null);
    const [logoFile, setLogoFile] = useState(null);

    // --- ESTADO DEL FORM DE NUEVO BANCO ---
    const [nuevoBanco, setNuevoBanco] = useState({
        banco: '', tipo_cuenta: 'Corriente', numero_cuenta: '', titular: '', rut_titular: '', email_notificacion: ''
    });

    useEffect(() => {
        if (formData.razon_social || formData.rut) {
            setNuevoBanco(prev => ({
                ...prev,
                titular: formData.razon_social,
                rut_titular: formData.rut,
            }));
        }
    }, [formData.razon_social, formData.rut]);

    const [formCentro, setFormCentro] = useState({ codigo: '', nombre: '' });

    // --- ESTADOS PARA MODALES DE EDICIÓN ---
    const [modalBancoOpen, setModalBancoOpen] = useState(false);
    const [bancoEditado, setBancoEditado] = useState(null);

    const [modalCentroOpen, setModalCentroOpen] = useState(false);
    const [centroEditado, setCentroEditado] = useState(null);

    // --- CONSTANTES DE URL ---
    const BASE_URL_IMG = API_BASE_URL.replace('/api', '/storage/');

    // --- HANDLERS Y FORMATEADORES ---
    const handleChange = (e) => setFormData({ ...formData, [e.target.name]: e.target.value });

    const handleSeleccionarLogo = (e) => {
        const file = e.target.files[0];
        if (!file) return;
        setLogoFile(file);
        setLogoPreview(URL.createObjectURL(file));
    };

    const handleRutChange = (e) => {
        let value = e.target.value.replace(/[^0-9kK]/ig, '');
        if (value.length > 1) {
            const cuerpo = value.slice(0, -1);
            const dv = value.slice(-1).toUpperCase();
            value = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, ".") + "-" + dv;
        }
        setFormData({ ...formData, rut: value });
    };

    const handleTelefonoChange = (e) => {
        let value = e.target.value;
        let cleaned = value.replace(/[^\d+]/g, '');

        if (cleaned === '') {
            setFormData({ ...formData, telefono: '' });
            return;
        }

        if (cleaned === '9') cleaned = '+569';
        else if (cleaned.length > 0 && !cleaned.startsWith('+')) cleaned = '+' + cleaned;

        let digits = cleaned.replace(/[^\d]/g, '');
        if (digits.startsWith('569')) {
            let formatted = '+56 9';
            if (digits.length > 3) formatted += ' ' + digits.substring(3, 7);
            if (digits.length > 7) formatted += ' ' + digits.substring(7, 11);
            setFormData({ ...formData, telefono: formatted });
            return;
        }

        if (cleaned.length > 15) cleaned = cleaned.substring(0, 15);
        setFormData({ ...formData, telefono: cleaned });
    };

    // --- GUARDAR PERFIL (Texto + Logo) ---
    const handleGuardarDatos = async (e) => {
        e.preventDefault();
        
        if (formData.rut && !validarRutChileno(formData.rut)) {
            Swal.fire({
                icon: 'warning',
                title: 'RUT Inválido',
                text: 'El RUT ingresado no es válido matemáticamente. Por favor, revísalo.',
                buttonsStyling: false,
                customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg' }
            });
            return;
        }

        setSaving(true);

        try {
            const formDataSend = new FormData();
            formDataSend.append('rut', formData.rut);
            formDataSend.append('razon_social', formData.razon_social);
            formDataSend.append('direccion', formData.direccion);
            formDataSend.append('email', formData.email);
            formDataSend.append('telefono', formData.telefono);
            formDataSend.append('color_primario', formData.color_primario);
            formDataSend.append('regimen_tributario', formData.regimen_tributario);
            formDataSend.append('_method', 'PUT');

            if (logoFile) formDataSend.append('logo', logoFile);
            const res = await api.upload('/empresas/perfil', formDataSend, { silent: true });

            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Guardado',
                    text: 'Perfil de empresa actualizado con éxito.',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'bg-emerald-600 text-white font-bold py-2 px-6 rounded-lg' }
                });

                setLogoPreview(null);
                setLogoFile(null);
                cargarPerfil();
            } else {
                throw new Error(res.message || 'Error al guardar');
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message,
                buttonsStyling: false,
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }
            });
        } finally {
            setSaving(false);
        }
    };

    // --- LÓGICA DE BANCOS ---
    const handleAgregarBanco = async (e) => {
        e.preventDefault();
        if (!nuevoBanco.banco || !nuevoBanco.numero_cuenta) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Selecciona un banco y escribe el número de cuenta.', buttonsStyling: false, customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg' } });
            return;
        }

        try {
            const res = await api.post('/empresas/bancos', nuevoBanco);
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Cuenta Agregada', timer: 1500, showConfirmButton: false });
                cargarPerfil();
                setNuevoBanco({ banco: '', tipo_cuenta: 'Corriente', numero_cuenta: '', titular: formData.razon_social, rut_titular: formData.rut, email_notificacion: '' });
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message, buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        }
    };

    const iniciarEdicionBanco = (banco) => {
        setBancoEditado({
            id: banco.id,
            banco: banco.banco,
            tipo_cuenta: banco.tipo_cuenta,
            numero_cuenta: banco.numero_cuenta
        });
        setModalBancoOpen(true);
    };

    const handleActualizarBanco = async (e) => {
        e.preventDefault();
        if (!bancoEditado.banco || !bancoEditado.numero_cuenta) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Todos los campos son obligatorios.', buttonsStyling: false, customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg' } });
            return;
        }

        try {
            const res = await api.put(`/empresas/bancos/${bancoEditado.id}`, bancoEditado);
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Cuenta Actualizada', timer: 1500, showConfirmButton: false });
                cargarPerfil();
                setModalBancoOpen(false);
                setBancoEditado(null);
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message, buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        }
    };

    const handleEliminarBanco = async (id) => {
        const confirm = await Swal.fire({
            title: '¿Eliminar cuenta?', text: "No podrás deshacer esto.", icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: { confirmButton: 'bg-rose-600 text-white font-bold py-2 px-6 rounded-lg mx-2 hover:bg-rose-700', cancelButton: 'bg-slate-200 text-slate-800 font-bold py-2 px-6 rounded-lg mx-2 hover:bg-slate-300' }
        });
        if (confirm.isConfirmed) {
            try {
                await api.delete(`/empresas/bancos/${id}`);
                cargarPerfil();
                Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message, buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
            }
        }
    };

    // --- LÓGICA DE CENTROS DE COSTO ---
    const agregarCentro = async (e) => {
        e.preventDefault();
        if (!formCentro.codigo || !formCentro.nombre) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Completa el código y el nombre del centro.', buttonsStyling: false, customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg' } });
            return;
        }
        try {
            const res = await api.post('/empresas/centros-costo', formCentro);
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Centro Agregado', timer: 1500, showConfirmButton: false });
                setFormCentro({ codigo: '', nombre: '' });
                cargarPerfil();
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.error || error.message, buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        }
    };

    const iniciarEdicionCentro = (centro) => {
        setCentroEditado({
            id: centro.id,
            codigo: centro.codigo,
            nombre: centro.nombre
        });
        setModalCentroOpen(true);
    };

    const handleActualizarCentro = async (e) => {
        e.preventDefault();
        if (!centroEditado.codigo || !centroEditado.nombre) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Todos los campos son obligatorios.', buttonsStyling: false, customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg' } });
            return;
        }

        try {
            const res = await api.put(`/empresas/centros-costo/${centroEditado.id}`, centroEditado);
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Centro Actualizado', timer: 1500, showConfirmButton: false });
                cargarPerfil();
                setModalCentroOpen(false);
                setCentroEditado(null);
            }
        } catch (error) {
            // Lee el error personalizado si viene desde el backend (ej. "código ya está en uso")
            Swal.fire({ icon: 'error', title: 'Error', text: error.response?.data?.error || error.message, buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
        }
    };

    const eliminarCentro = async (id) => {
        const confirm = await Swal.fire({
            title: '¿Eliminar Centro de Costo?', text: "No podrás deshacer esto.", icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: { confirmButton: 'bg-rose-600 text-white font-bold py-2 px-6 rounded-lg mx-2 hover:bg-rose-700', cancelButton: 'bg-slate-200 text-slate-800 font-bold py-2 px-6 rounded-lg mx-2 hover:bg-slate-300' }
        });
        if (confirm.isConfirmed) {
            try {
                await api.delete(`/empresas/centros-costo/${id}`);
                cargarPerfil();
                Swal.fire({ icon: 'success', title: 'Eliminado', timer: 1500, showConfirmButton: false });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message, buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' } });
            }
        }
    };

    const imagenMostrada = logoPreview || (formData.logo_path ? (formData.logo_path.startsWith('http') ? formData.logo_path : `${BASE_URL_IMG}${formData.logo_path}`) : null);

    if (loading) {
        return (
            <EstadoCarga
                cargando={true}
                mensajeCargando="Cargando perfil..."
                tamano="compacto"
                color="emerald"
            />
        );
    }

    return (
        <div className="max-w-6xl mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 pb-10">
            <div className="flex justify-between items-center mb-8">
                <div>
                    <div className="flex items-center gap-3"><h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Mi Empresa</h1><AyudaModulo moduloId="perfilEmpresa" /></div>
                    <p className="text-slate-500 font-medium mt-1">Configuración general, tributaria y contable</p>
                </div>
            </div>

            {/* --- NAVEGACIÓN TABS --- */}
            <div className="flex flex-wrap gap-2 bg-slate-100 p-1.5 rounded-xl w-fit mb-6">
                <button onClick={() => setActiveTab('general')} className={`px-5 py-2.5 rounded-lg font-bold text-sm transition-all ${activeTab === 'general' ? 'bg-white shadow-sm text-blue-600' : 'text-slate-500 hover:text-slate-700'}`}>
                    <svg className="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Información & Logo
                </button>
                <button onClick={() => setActiveTab('bancos')} className={`px-5 py-2.5 rounded-lg font-bold text-sm transition-all ${activeTab === 'bancos' ? 'bg-white shadow-sm text-emerald-600' : 'text-slate-500 hover:text-slate-700'}`}>
                    <svg className="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    Cuentas Bancarias
                </button>
                <button onClick={() => setActiveTab('centros')} className={`px-5 py-2.5 rounded-lg font-bold text-sm transition-all ${activeTab === 'centros' ? 'bg-white shadow-sm text-indigo-600' : 'text-slate-500 hover:text-slate-700'}`}>
                    <svg className="w-4 h-4 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Centros de Costo
                </button>
            </div>

            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                {/* --- PESTAÑA: GENERAL --- */}
                {activeTab === 'general' && (
                    <PerfilEmpresaGeneral
                        formData={formData}
                        imagenMostrada={imagenMostrada}
                        saving={saving}
                        onChange={handleChange}
                        onRutChange={handleRutChange}
                        onTelefonoChange={handleTelefonoChange}
                        onSeleccionarLogo={handleSeleccionarLogo}
                        onSubmit={handleGuardarDatos}
                    />
                )}

                {/* --- PESTAÑA: BANCOS --- */}
                {activeTab === 'bancos' && (
                    <PerfilEmpresaBancos
                        bancos={bancos}
                        listaBancos={listaBancos}
                        nuevoBanco={nuevoBanco}
                        onNuevoBancoChange={setNuevoBanco}
                        onAgregarBanco={handleAgregarBanco}
                        onEditarBanco={iniciarEdicionBanco}
                        onEliminarBanco={handleEliminarBanco}
                    />
                )}

                {/* --- PESTAÑA: CENTROS DE COSTO --- */}
                {activeTab === 'centros' && (
                    <PerfilEmpresaCentros
                        centros={centros}
                        formCentro={formCentro}
                        onFormCentroChange={setFormCentro}
                        onAgregarCentro={agregarCentro}
                        onEditarCentro={iniciarEdicionCentro}
                        onEliminarCentro={eliminarCentro}
                    />
                )}
            </div>

            <ModalBancoEdicion
                isOpen={modalBancoOpen}
                banco={bancoEditado}
                listaBancos={listaBancos}
                onChange={setBancoEditado}
                onClose={() => setModalBancoOpen(false)}
                onSubmit={handleActualizarBanco}
            />

            <ModalCentroEdicion
                isOpen={modalCentroOpen}
                centro={centroEditado}
                onChange={setCentroEditado}
                onClose={() => setModalCentroOpen(false)}
                onSubmit={handleActualizarCentro}
            />
        </div>
    );
};

export default PerfilEmpresa;