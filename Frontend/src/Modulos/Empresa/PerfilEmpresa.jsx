import React, { useState, useEffect } from 'react';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

// --- VALIDADOR DE RUT CHILENO (Módulo 11) ---
const validarRutChileno = (rut) => {
    if (!rut) return false;
    const cleanRut = rut.replace(/[^0-9kK]/ig, '').toUpperCase();
    if (cleanRut.length < 2) return false;

    const cuerpo = cleanRut.slice(0, -1);
    const dv = cleanRut.slice(-1);

    let suma = 0;
    let multiplo = 2;
    for (let i = 1; i <= cuerpo.length; i++) {
        let index = multiplo * cuerpo.charAt(cuerpo.length - i);
        suma = suma + index;
        if (multiplo < 7) { multiplo = multiplo + 1; } else { multiplo = 2; }
    }

    const dvEsperado = 11 - (suma % 11);
    const dvCalculado = (dvEsperado === 11) ? '0' : (dvEsperado === 10) ? 'K' : dvEsperado.toString();

    return dv === dvCalculado;
};

const PerfilEmpresa = () => {
    // --- ESTADOS GENERALES ---
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [activeTab, setActiveTab] = useState('general');

    // --- ESTADOS DE IMAGEN ---
    const [logoPreview, setLogoPreview] = useState(null);
    const [logoFile, setLogoFile] = useState(null);

    // --- ESTADOS DE FORMULARIOS ---
    const [formData, setFormData] = useState({
        rut: '',
        razon_social: '',
        direccion: '',
        email: '',
        telefono: '',
        logo_path: '',
        color_primario: '#10b981',
        regimen_tributario: '14_D3'
    });

    const [bancos, setBancos] = useState([]);
    const [listaBancos, setListaBancos] = useState([]);
    const [nuevoBanco, setNuevoBanco] = useState({
        banco: '', tipo_cuenta: 'Corriente', numero_cuenta: '', titular: '', rut_titular: '', email_notificacion: ''
    });

    const [centros, setCentros] = useState([]);
    const [formCentro, setFormCentro] = useState({ codigo: '', nombre: '' });

    // --- ESTADOS PARA MODALES DE EDICIÓN ---
    const [modalBancoOpen, setModalBancoOpen] = useState(false);
    const [bancoEditado, setBancoEditado] = useState(null);
    
    const [modalCentroOpen, setModalCentroOpen] = useState(false);
    const [centroEditado, setCentroEditado] = useState(null);

    // --- CONSTANTES DE URL ---
    const API_BASE = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api';
    const BASE_URL_IMG = API_BASE.replace('/api', '/storage/');

    // --- EFECTOS AL CARGAR ---
    useEffect(() => {
        cargarPerfil();
        cargarCatalogoBancos();
    }, []);

    const cargarPerfil = async () => {
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
                    regimen_tributario: res.data.regimen_tributario || '14_D3'
                });
                setBancos(res.data.bancos || []);
                setCentros(res.data.centros_costo || []);

                setNuevoBanco(prev => ({
                    ...prev,
                    titular: res.data.razon_social,
                    rut_titular: res.data.rut
                }));
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar la información.',
                buttonsStyling: false,
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }
            });
        } finally {
            setLoading(false);
        }
    };

    const cargarCatalogoBancos = async () => {
        try {
            const res = await api.get('/empresas/catalogo-bancos');
            if (res.success) setListaBancos(res.data);
        } catch (error) {
            console.error(error);
        }
    };

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

            const token = localStorage.getItem('token') || sessionStorage.getItem('erp_token');
            let parsedToken = token;
            if (token && token.startsWith('"')) parsedToken = JSON.parse(token);

            const response = await fetch(`${API_BASE}/empresas/perfil`, {
                method: 'POST', 
                headers: {
                    'Authorization': `Bearer ${parsedToken}`,
                    'Accept': 'application/json'
                },
                body: formDataSend
            });
            const res = await response.json();

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

    if (loading) return <div className="p-10 text-center text-slate-400 font-bold">Cargando perfil...</div>;

    return (
        <div className="max-w-6xl mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 pb-10">
            <div className="flex justify-between items-center mb-8">
                <div>
                    <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Mi Empresa</h1>
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
                    <div className="p-6 md:p-8 grid grid-cols-1 lg:grid-cols-3 gap-8 animate-fade-in">
                        <div className="lg:col-span-1 flex flex-col items-center space-y-4">
                            <div className="w-56 h-56 border-2 border-dashed border-slate-300 rounded-2xl flex items-center justify-center bg-slate-50 overflow-hidden relative group transition-colors hover:border-blue-400">
                                {imagenMostrada ? (
                                    <img src={imagenMostrada} alt="Logo Empresa" className="w-full h-full object-contain p-4" />
                                ) : (
                                    <div className="text-center text-slate-400">
                                        <svg className="w-12 h-12 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        <p className="text-sm font-bold">Sin Logo</p>
                                    </div>
                                )}
                                <label className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-all cursor-pointer">
                                    <span className="text-sm font-bold flex items-center gap-2">
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                        Cambiar Imagen
                                    </span>
                                    <input type="file" className="hidden" accept="image/*" onChange={handleSeleccionarLogo} />
                                </label>
                            </div>
                            <p className="text-xs text-slate-400 text-center font-medium">Formato: PNG o JPG. <br />Se utilizará en tus cotizaciones y documentos.</p>
                        </div>

                        <form onSubmit={handleGuardarDatos} className="lg:col-span-2 space-y-5">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">RUT Empresa</label>
                                    <input
                                        name="rut"
                                        value={formData.rut}
                                        onChange={handleRutChange}
                                        placeholder="76.123.456-K"
                                        className="w-full border border-slate-200 rounded-lg p-2.5 bg-slate-50 font-mono text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Razón Social</label>
                                    <input
                                        name="razon_social"
                                        value={formData.razon_social}
                                        onChange={handleChange}
                                        className="w-full border border-slate-200 rounded-lg p-2.5 font-bold text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                            </div>

                            <div className="bg-blue-50 p-5 border border-blue-100 rounded-xl">
                                <label className="block text-[10px] font-black text-blue-600 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                                    <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                    Régimen Tributario (SII)
                                </label>
                                <select
                                    name="regimen_tributario"
                                    value={formData.regimen_tributario}
                                    onChange={handleChange}
                                    className="w-full border border-blue-200 rounded-lg p-2.5 bg-white text-sm font-bold text-slate-700 focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer"
                                >
                                    <option value="14_D3">Régimen Pro Pyme General (14 D N° 3)</option>
                                    <option value="14_D8">Régimen Pro Pyme Transparente (14 D N° 8)</option>
                                    <option value="14_A">Régimen General Semi Integrado (14 A)</option>
                                </select>
                                <p className="text-[11px] text-blue-500 font-medium mt-2">
                                    Define si el ERP calculará la Operación Renta mediante Flujo de Caja o Devengado.
                                </p>
                            </div>

                            <div>
                                <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Dirección Comercial</label>
                                <input
                                    name="direccion"
                                    value={formData.direccion}
                                    onChange={handleChange}
                                    className="w-full border border-slate-200 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Calle, Número, Comuna..."
                                />
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <div>
                                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Email Contacto</label>
                                    <input
                                        name="email"
                                        type="email"
                                        value={formData.email}
                                        onChange={handleChange}
                                        className="w-full border border-slate-200 rounded-lg p-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1.5">Teléfono</label>
                                    <input
                                        name="telefono"
                                        value={formData.telefono}
                                        onChange={handleTelefonoChange}
                                        placeholder="+56 9 1234 5678"
                                        className="w-full border border-slate-200 rounded-lg p-2.5 text-sm font-mono outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>
                            </div>

                            <div className="pt-2">
                                <label className="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Color Corporativo (Cotizaciones)</label>
                                <div className="flex items-center gap-3">
                                    <input
                                        type="color"
                                        name="color_primario"
                                        value={formData.color_primario}
                                        onChange={handleChange}
                                        className="w-10 h-10 rounded-lg cursor-pointer border-0 p-0 shadow-sm"
                                    />
                                    <input
                                        type="text"
                                        name="color_primario"
                                        value={formData.color_primario}
                                        onChange={handleChange}
                                        className="border border-slate-200 rounded-lg px-3 py-2 text-sm font-mono uppercase w-28 focus:ring-2 focus:ring-blue-500 outline-none"
                                        maxLength={7}
                                    />
                                </div>
                            </div>

                            <div className="pt-4 border-t border-slate-100 flex justify-end">
                                <button type="submit" disabled={saving} className="bg-slate-900 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-slate-900/20 hover:bg-slate-800 transition-all disabled:opacity-50 flex items-center gap-2 text-sm">
                                    {saving ? (
                                        <><svg className="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle><path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Guardando...</>
                                    ) : (
                                        <><svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M5 13l4 4L19 7"></path></svg> Guardar Cambios</>
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* --- PESTAÑA: BANCOS --- */}
                {activeTab === 'bancos' && (
                    <div className="p-6 md:p-8 animate-fade-in">
                        <div className="mb-6">
                            <h3 className="text-xl font-black text-slate-800">Cuentas Bancarias</h3>
                            <p className="text-sm text-slate-500">Administra las cuentas utilizadas para pagos y conciliación.</p>
                        </div>
                        
                        <form onSubmit={handleAgregarBanco} className="bg-slate-50 p-5 rounded-2xl border border-slate-200 mb-8 flex flex-col md:flex-row gap-4 items-end shadow-sm">
                            <div className="flex-1 w-full">
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Institución</label>
                                <select className="w-full border border-slate-200 rounded-xl p-3 text-sm bg-white cursor-pointer outline-none focus:ring-2 focus:ring-emerald-500 font-medium" value={nuevoBanco.banco} onChange={e => setNuevoBanco({ ...nuevoBanco, banco: e.target.value })}>
                                    <option value="">Seleccione banco...</option>
                                    {listaBancos.map((banco) => (<option key={banco.id} value={banco.nombre}>{banco.nombre}</option>))}
                                </select>
                            </div>
                            <div className="w-full md:w-48">
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Tipo</label>
                                <select className="w-full border border-slate-200 rounded-xl p-3 text-sm bg-white cursor-pointer outline-none focus:ring-2 focus:ring-emerald-500 font-medium" value={nuevoBanco.tipo_cuenta} onChange={e => setNuevoBanco({ ...nuevoBanco, tipo_cuenta: e.target.value })}>
                                    <option value="Corriente">Cta. Corriente</option>
                                    <option value="Vista">Cta. Vista / RUT</option>
                                    <option value="Ahorro">Cta. Ahorro</option>
                                </select>
                            </div>
                            <div className="flex-1 w-full">
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">N° Cuenta</label>
                                <input placeholder="123456789" className="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono outline-none focus:ring-2 focus:ring-emerald-500" value={nuevoBanco.numero_cuenta} onChange={e => setNuevoBanco({ ...nuevoBanco, numero_cuenta: e.target.value })} />
                            </div>

                            <button type="submit" className="w-full md:w-auto bg-emerald-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-emerald-500 transition-colors text-sm shadow-lg shadow-emerald-600/30 whitespace-nowrap">
                                Agregar Cuenta
                            </button>
                        </form>

                        <div className="overflow-hidden border border-slate-200 rounded-2xl">
                            <table className="min-w-full text-left bg-white">
                                <thead className="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Banco</th>
                                        <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">N° Cuenta</th>
                                        <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {bancos.length === 0 ? (
                                        <tr><td colSpan="3" className="p-8 text-center text-slate-400 font-medium">No hay cuentas registradas.</td></tr>
                                    ) : (
                                        bancos.map(b => (
                                            <tr key={b.id} className="hover:bg-slate-50 transition-colors">
                                                <td className="px-6 py-4 font-bold text-slate-800">{b.banco}</td>
                                                <td className="px-6 py-4 text-slate-600 font-mono text-sm">{b.tipo_cuenta} • {b.numero_cuenta}</td>
                                                <td className="px-6 py-4 text-center flex justify-center gap-2">
                                                    <button onClick={() => iniciarEdicionBanco(b)} className="text-blue-500 bg-blue-50 hover:bg-blue-600 hover:text-white p-2 rounded-lg transition-colors" title="Editar">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                    </button>
                                                    <button onClick={() => handleEliminarBanco(b.id)} className="text-rose-500 bg-rose-50 hover:bg-rose-600 hover:text-white p-2 rounded-lg transition-colors" title="Eliminar">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {/* --- PESTAÑA: CENTROS DE COSTO --- */}
                {activeTab === 'centros' && (
                    <div className="p-6 md:p-8 animate-fade-in">
                        <div className="mb-6">
                            <h3 className="text-xl font-black text-slate-800">Centros de Costo</h3>
                            <p className="text-sm text-slate-500">Clasifica tus ingresos y gastos para mejorar la analítica contable.</p>
                        </div>

                        <form onSubmit={agregarCentro} className="bg-slate-50 p-5 rounded-2xl border border-slate-200 mb-8 flex flex-col md:flex-row gap-4 items-end shadow-sm">
                            <div className="w-full md:w-32">
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Código</label>
                                <input
                                    type="text"
                                    value={formCentro.codigo}
                                    onChange={e => setFormCentro({ ...formCentro, codigo: e.target.value.toUpperCase() })}
                                    placeholder="ADM01"
                                    className="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono uppercase outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div className="flex-1 w-full">
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nombre del Departamento / Proyecto</label>
                                <input
                                    type="text"
                                    value={formCentro.nombre}
                                    onChange={e => setFormCentro({ ...formCentro, nombre: e.target.value })}
                                    placeholder="Ej: Administración Central"
                                    className="w-full border border-slate-200 rounded-xl p-3 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <button type="submit" className="w-full md:w-auto bg-indigo-600 text-white font-bold py-3 px-6 rounded-xl hover:bg-indigo-500 transition-colors text-sm shadow-lg shadow-indigo-600/30 whitespace-nowrap">
                                Crear Centro
                            </button>
                        </form>

                        <div className="overflow-hidden border border-slate-200 rounded-2xl">
                            <table className="min-w-full text-left bg-white">
                                <thead className="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest w-32">Código</th>
                                        <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Nombre</th>
                                        <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center w-24">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {centros.length === 0 ? (
                                        <tr><td colSpan="3" className="p-8 text-center text-slate-400 font-medium">No hay centros de costo registrados.</td></tr>
                                    ) : (
                                        centros.map(cc => (
                                            <tr key={cc.id} className="hover:bg-slate-50 transition-colors">
                                                <td className="px-6 py-4">
                                                    <span className="bg-slate-200 text-slate-700 font-mono text-xs px-2.5 py-1 rounded-md font-bold">{cc.codigo}</span>
                                                </td>
                                                <td className="px-6 py-4 font-bold text-slate-800">{cc.nombre}</td>
                                                <td className="px-6 py-4 text-center flex justify-center gap-2">
                                                    <button onClick={() => iniciarEdicionCentro(cc)} className="text-blue-500 bg-blue-50 hover:bg-blue-600 hover:text-white p-2 rounded-lg transition-colors" title="Editar">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                    </button>
                                                    <button onClick={() => eliminarCentro(cc.id)} className="text-rose-500 bg-rose-50 hover:bg-rose-600 hover:text-white p-2 rounded-lg transition-colors" title="Eliminar">
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {/* --- MODAL PARA EDITAR BANCO --- */}
            {modalBancoOpen && bancoEditado && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-fade-in">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div className="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                            <h3 className="font-black text-slate-800 text-lg flex items-center gap-2">
                                <svg className="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                Editar Cuenta Bancaria
                            </h3>
                            <button onClick={() => setModalBancoOpen(false)} className="text-slate-400 hover:text-rose-500 transition-colors">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <form onSubmit={handleActualizarBanco} className="p-6 space-y-5">
                            <div>
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Institución</label>
                                <select className="w-full border border-slate-200 rounded-xl p-3 text-sm bg-white cursor-pointer outline-none focus:ring-2 focus:ring-blue-500 font-medium" value={bancoEditado.banco} onChange={e => setBancoEditado({...bancoEditado, banco: e.target.value})}>
                                    <option value="">Seleccione banco...</option>
                                    {listaBancos.map((banco) => (<option key={banco.id} value={banco.nombre}>{banco.nombre}</option>))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Tipo de Cuenta</label>
                                <select className="w-full border border-slate-200 rounded-xl p-3 text-sm bg-white cursor-pointer outline-none focus:ring-2 focus:ring-blue-500 font-medium" value={bancoEditado.tipo_cuenta} onChange={e => setBancoEditado({...bancoEditado, tipo_cuenta: e.target.value})}>
                                    <option value="Corriente">Cta. Corriente</option>
                                    <option value="Vista">Cta. Vista / RUT</option>
                                    <option value="Ahorro">Cta. Ahorro</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">N° Cuenta</label>
                                <input className="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono outline-none focus:ring-2 focus:ring-blue-500" value={bancoEditado.numero_cuenta} onChange={e => setBancoEditado({...bancoEditado, numero_cuenta: e.target.value})} />
                            </div>

                            <div className="pt-3 flex justify-end gap-3 border-t border-slate-100">
                                <button type="button" onClick={() => setModalBancoOpen(false)} className="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                                    Cancelar
                                </button>
                                <button type="submit" className="px-5 py-2.5 rounded-xl font-bold text-sm text-white bg-blue-600 hover:bg-blue-500 shadow-lg shadow-blue-600/30 transition-colors">
                                    Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* --- MODAL PARA EDITAR CENTRO DE COSTO --- */}
            {modalCentroOpen && centroEditado && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4 animate-fade-in">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
                        <div className="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                            <h3 className="font-black text-slate-800 text-lg flex items-center gap-2">
                                <svg className="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                Editar Centro de Costo
                            </h3>
                            <button onClick={() => setModalCentroOpen(false)} className="text-slate-400 hover:text-rose-500 transition-colors">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <form onSubmit={handleActualizarCentro} className="p-6 space-y-5">
                            <div>
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Código</label>
                                <input
                                    type="text"
                                    value={centroEditado.codigo}
                                    onChange={e => setCentroEditado({ ...centroEditado, codigo: e.target.value.toUpperCase() })}
                                    className="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono uppercase outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5">Nombre del Departamento / Proyecto</label>
                                <input
                                    type="text"
                                    value={centroEditado.nombre}
                                    onChange={e => setCentroEditado({ ...centroEditado, nombre: e.target.value })}
                                    className="w-full border border-slate-200 rounded-xl p-3 text-sm font-medium outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>

                            <div className="pt-3 flex justify-end gap-3 border-t border-slate-100">
                                <button type="button" onClick={() => setModalCentroOpen(false)} className="px-5 py-2.5 rounded-xl font-bold text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                                    Cancelar
                                </button>
                                <button type="submit" className="px-5 py-2.5 rounded-xl font-bold text-sm text-white bg-indigo-600 hover:bg-indigo-500 shadow-lg shadow-indigo-600/30 transition-colors">
                                    Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
};

export default PerfilEmpresa;