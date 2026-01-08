import React, { useState, useEffect } from 'react';
import { api } from '../../Configuracion/api';
import Swal from 'sweetalert2';

const PerfilEmpresa = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [activeTab, setActiveTab] = useState('general'); 
    
    // Estado Datos Generales (Unificado)
    const [formData, setFormData] = useState({
        rut: '', 
        razon_social: '', 
        direccion: '', 
        email: '', 
        telefono: '', 
        logo_path: '',
        color_primario: '#10b981' // Color por defecto
    });

    // Estado Bancos
    const [bancos, setBancos] = useState([]);
    const [nuevoBanco, setNuevoBanco] = useState({
        banco: '', tipo_cuenta: 'Corriente', numero_cuenta: '', titular: '', rut_titular: '', email_notificacion: ''
    });
    const [listaBancos, setListaBancos] = useState([]); 

    // URL base para imágenes
    const BASE_URL_IMG = import.meta.env.VITE_API_URL ? import.meta.env.VITE_API_URL.replace('/api', '/') : 'http://localhost/ERP-Contable/Backend/Public/';

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
                    color_primario: res.data.color_primario || '#10b981'
                });
                setBancos(res.data.bancos || []);
                
                setNuevoBanco(prev => ({
                    ...prev, 
                    titular: res.data.razon_social,
                    rut_titular: res.data.rut
                }));
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'No se pudo cargar la información.', 'error');
        } finally {
            setLoading(false);
        }
    };

    const cargarCatalogoBancos = async () => {
        try {
            const res = await api.get('/empresas/catalogo-bancos');
            if (res.success) {
                setListaBancos(res.data);
            }
        } catch (error) {
            console.error("Error cargando bancos", error);
        }
    };
    
    // --- LOGICA GENERAL ---
    const handleChange = (e) => setFormData({ ...formData, [e.target.name]: e.target.value });

    const handleGuardarDatos = async (e) => {
        e.preventDefault();
        setSaving(true);
        try {
            const res = await api.put('/empresas/perfil', formData);
            if (res.success) Swal.fire('Guardado', 'Información actualizada.', 'success');
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        } finally {
            setSaving(false);
        }
    };

    // --- LOGICA LOGO ---
    const handleSubirLogo = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const formDataLogo = new FormData();
        formDataLogo.append('logo', file);

        try {
            const token = localStorage.getItem('erp_token') || sessionStorage.getItem('erp_token');
            const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost/ERP-Contable/Backend/Public/api';
            
            const response = await fetch(`${apiUrl}/empresas/logo`, {
                method: 'POST',
                headers: { 'Authorization': `Bearer ${token}` },
                body: formDataLogo
            });
            const data = await response.json();

            if (data.success) {
                setFormData(prev => ({ ...prev, logo_path: data.logo_url || '' }));
                Swal.fire('Logo Actualizado', 'La imagen se ha subido correctamente.', 'success');
                cargarPerfil(); 
            } else {
                throw new Error(data.error || 'Error al subir');
            }
        } catch (error) {
            Swal.fire('Error', 'No se pudo subir el logo.', 'error');
        }
    };

    // --- LOGICA BANCOS ---
    const handleAgregarBanco = async (e) => {
        e.preventDefault();
        if(!nuevoBanco.banco || !nuevoBanco.numero_cuenta) {
            Swal.fire('Atención', 'Selecciona un banco y escribe el número de cuenta.', 'warning');
            return;
        }

        try {
            const res = await api.post('/empresas/bancos', nuevoBanco);
            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Cuenta Agregada', timer: 1500, showConfirmButton: false });
                cargarPerfil(); 
                setNuevoBanco(prev => ({ ...prev, banco: '', numero_cuenta: '' })); 
            }
        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    };

    const handleEliminarBanco = async (id) => {
        const confirm = await Swal.fire({
            title: '¿Eliminar cuenta?', text: "No podrás deshacer esto.", icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar'
        });
        if (confirm.isConfirmed) {
            try {
                await api.delete(`/empresas/bancos/${id}`);
                cargarPerfil();
                Swal.fire('Eliminado', '', 'success');
            } catch (error) {
                Swal.fire('Error', error.message, 'error');
            }
        }
    };

    if (loading) return <div className="p-10 text-center text-slate-400">Cargando perfil...</div>;

    return (
        <div className="max-w-5xl mx-auto p-6 font-sans text-slate-800">
            <div className="flex justify-between items-center mb-8">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">Mi Empresa</h1>
                    <p className="text-slate-500 text-sm">Configuración para documentos PDF (Cotizaciones/Facturas)</p>
                </div>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                {/* TABS */}
                <div className="flex border-b border-slate-100">
                    <button onClick={() => setActiveTab('general')} className={`flex-1 py-4 text-sm font-bold border-b-2 transition-colors ${activeTab === 'general' ? 'border-emerald-500 text-emerald-700 bg-emerald-50/50' : 'border-transparent text-slate-500 hover:bg-slate-50'}`}>
                        <i className="fas fa-building mr-2"></i> Información & Logo
                    </button>
                    <button onClick={() => setActiveTab('bancos')} className={`flex-1 py-4 text-sm font-bold border-b-2 transition-colors ${activeTab === 'bancos' ? 'border-emerald-500 text-emerald-700 bg-emerald-50/50' : 'border-transparent text-slate-500 hover:bg-slate-50'}`}>
                        <i className="fas fa-university mr-2"></i> Cuentas Bancarias
                    </button>
                </div>

                {/* --- PESTAÑA GENERAL --- */}
                {activeTab === 'general' && (
                    <div className="p-8 grid grid-cols-1 md:grid-cols-3 gap-8">
                        {/* COLUMNA LOGO */}
                        <div className="md:col-span-1 flex flex-col items-center space-y-4">
                            <div className="w-48 h-48 border-2 border-dashed border-slate-300 rounded-xl flex items-center justify-center bg-slate-50 overflow-hidden relative group">
                                {formData.logo_path ? (
                                    <img 
                                        src={formData.logo_path.startsWith('http') ? formData.logo_path : `${BASE_URL_IMG}${formData.logo_path}`} 
                                        alt="Logo Empresa" 
                                        className="w-full h-full object-contain p-2" 
                                    />
                                ) : (
                                    <div className="text-center text-slate-400">
                                        <i className="fas fa-image text-3xl mb-2"></i>
                                        <p className="text-xs">Sin Logo</p>
                                    </div>
                                )}
                                <label className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center text-white opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer">
                                    <span className="text-sm font-bold"><i className="fas fa-camera mr-1"></i> Cambiar</span>
                                    <input type="file" className="hidden" accept="image/*" onChange={handleSubirLogo} />
                                </label>
                            </div>
                            <p className="text-xs text-slate-400 text-center">Formato: PNG o JPG. <br/>Se usará en la cabecera de tus PDF.</p>
                        </div>

                        {/* COLUMNA FORMULARIO */}
                        <form onSubmit={handleGuardarDatos} className="md:col-span-2 space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase mb-1">RUT Empresa</label>
                                    <input name="rut" value={formData.rut} onChange={handleChange} className="w-full border rounded p-2 bg-slate-50 font-mono" />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Razón Social</label>
                                    <input name="razon_social" value={formData.razon_social} onChange={handleChange} className="w-full border rounded p-2 font-bold" />
                                </div>
                            </div>
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Dirección Comercial</label>
                                <input name="direccion" value={formData.direccion} onChange={handleChange} className="w-full border rounded p-2" placeholder="Calle, Número, Comuna..." />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Email Contacto</label>
                                    <input name="email" value={formData.email} onChange={handleChange} className="w-full border rounded p-2" />
                                </div>
                                <div>
                                    <label className="block text-xs font-bold text-slate-500 uppercase mb-1">Teléfono</label>
                                    <input name="telefono" value={formData.telefono} onChange={handleChange} className="w-full border rounded p-2" />
                                </div>
                            </div>

                            {/* --- SECCIÓN COLOR DOCUMENTOS (ACTUALIZADO) --- */}
                            <div className="mt-4 border-t pt-4">
                                <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Color de Documentos</label>
                                <div className="flex items-center gap-3">
                                    {/* Selector Visual */}
                                    <input 
                                        type="color" 
                                        name="color_primario" 
                                        value={formData.color_primario} 
                                        onChange={handleChange}
                                        className="w-12 h-10 rounded cursor-pointer border-0 p-0 shadow-sm"
                                    />
                                    
                                    {/* Input de Texto para escribir HEX directo */}
                                    <div className="flex flex-col">
                                        <span className="text-xs font-bold text-slate-500 mb-1">Código HEX</span>
                                        <input
                                            type="text"
                                            name="color_primario"
                                            value={formData.color_primario}
                                            onChange={handleChange}
                                            className="border rounded px-2 py-1 text-sm font-mono uppercase w-28 focus:ring-2 focus:ring-blue-500 outline-none"
                                            placeholder="#000000"
                                            maxLength={7}
                                        />
                                    </div>
                                </div>
                                <p className="text-xs text-slate-400 mt-2">
                                    Puedes seleccionar un color o escribir el código exacto (Ej: #1E40AF).
                                </p>
                            </div>

                            <div className="pt-4 text-right">
                                <button type="submit" disabled={saving} className="bg-slate-900 text-white px-6 py-2 rounded-lg font-bold shadow hover:bg-slate-800 transition-colors disabled:opacity-50">
                                    {saving ? 'Guardando...' : 'Guardar Cambios'}
                                </button>
                            </div>
                        </form>
                    </div>
                )}

                {/* --- PESTAÑA BANCOS (SIN CAMBIOS) --- */}
                {activeTab === 'bancos' && (
                    <div className="p-8">
                        {/* Formulario Agregar */}
                        <form onSubmit={handleAgregarBanco} className="bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                            <div className="md:col-span-1">
                                <label className="block text-xs font-bold text-slate-500 mb-1">Banco</label>
                                <select 
                                    className="w-full border rounded p-2 text-sm bg-white cursor-pointer"
                                    value={nuevoBanco.banco}
                                    onChange={e => setNuevoBanco({...nuevoBanco, banco: e.target.value})}
                                >
                                    <option value="">Seleccione Banco...</option>
                                    {listaBancos.map((banco) => (
                                        <option key={banco.id} value={banco.nombre}>
                                            {banco.nombre}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="md:col-span-1">
                                <label className="block text-xs font-bold text-slate-500 mb-1">Tipo Cuenta</label>
                                <select 
                                    className="w-full border rounded p-2 text-sm bg-white cursor-pointer"
                                    value={nuevoBanco.tipo_cuenta}
                                    onChange={e => setNuevoBanco({...nuevoBanco, tipo_cuenta: e.target.value})}
                                >
                                    <option value="Corriente">Cta. Corriente</option>
                                    <option value="Vista">Cta. Vista / RUT</option>
                                    <option value="Ahorro">Cta. Ahorro</option>
                                </select>
                            </div>
                            <div className="md:col-span-1">
                                <label className="block text-xs font-bold text-slate-500 mb-1">N° Cuenta</label>
                                <input 
                                    placeholder="123456789" 
                                    className="w-full border rounded p-2 text-sm font-mono"
                                    value={nuevoBanco.numero_cuenta}
                                    onChange={e => setNuevoBanco({...nuevoBanco, numero_cuenta: e.target.value})}
                                />
                            </div>
                            <div className="md:col-span-1">
                                <button type="submit" className="w-full bg-emerald-600 text-white font-bold p-2 rounded hover:bg-emerald-700 transition-colors text-sm shadow-sm">
                                    + Agregar
                                </button>
                            </div>
                        </form>

                        {/* Lista de Bancos */}
                        <div className="space-y-3">
                            {bancos.length === 0 ? (
                                <p className="text-center text-slate-400 italic py-8">No hay cuentas registradas. Agrega una para que aparezca en tus cotizaciones.</p>
                            ) : (
                                bancos.map(b => (
                                    <div key={b.id} className="flex justify-between items-center p-4 border rounded-lg hover:shadow-sm transition-shadow bg-white">
                                        <div className="flex items-center gap-4">
                                            <div className="w-10 h-10 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                                                <i className="fas fa-university"></i>
                                            </div>
                                            <div>
                                                <p className="font-bold text-slate-800">{b.banco}</p>
                                                <p className="text-sm text-slate-500 font-mono">{b.tipo_cuenta} • {b.numero_cuenta}</p>
                                            </div>
                                        </div>
                                        <button 
                                            onClick={() => handleEliminarBanco(b.id)} 
                                            className="text-red-400 hover:text-red-600 p-2 rounded-full hover:bg-red-50 transition-colors"
                                            title="Eliminar cuenta"
                                        >
                                            <i className="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default PerfilEmpresa;