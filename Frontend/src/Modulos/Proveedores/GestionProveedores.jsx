import React, { useState, useEffect } from 'react';
import { api } from '../../Configuracion/api'; 
import Swal from 'sweetalert2';
import { formatearIdentificador, validarIdentificador } from '../../Utilidades/identificadores';

const BankAccountsTab = ({ proveedorId }) => {
    const [accounts, setAccounts] = useState([]);
    const [loading, setLoading] = useState(false);
    const [newAccount, setNewAccount] = useState({ banco: '', numeroCuenta: '', tipoCuenta: 'Corriente', paisIso: 'CL' });

    useEffect(() => {
        if (proveedorId) loadAccounts();
    }, [proveedorId]);

    const loadAccounts = async () => {
        setLoading(true);
        try {
            const data = await api.get(`/cuentas-bancarias/proveedor/${proveedorId}`);
            if (data.success) setAccounts(data.data || []); 
        } catch (err) {
            console.error("Error cargando cuentas", err);
        } finally {
            setLoading(false);
        }
    };

    const handleAdd = async () => {
        if (!newAccount.banco || !newAccount.numeroCuenta) {
            return Swal.fire({
                icon: 'warning',
                title: 'Faltan datos',
                text: 'El Banco y el N° de Cuenta son obligatorios.',
                customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg hover:bg-amber-600 transition-colors' },
                buttonsStyling: false
            });
        }
        
        try {
            const data = await api.post('/cuentas-bancarias', { ...newAccount, proveedorId });
            if (data.success) {
                loadAccounts();
                setNewAccount({ banco: '', numeroCuenta: '', tipoCuenta: 'Corriente', paisIso: 'CL' });
                Swal.fire({
                    icon: 'success',
                    title: 'Cuenta agregada',
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (err) {
            Swal.fire('Error', err.message || "Error al guardar cuenta", 'error');
        }
    };

    const handleDelete = (id) => {
        Swal.fire({
            title: '¿Eliminar cuenta bancaria?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'bg-red-600 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-red-700 mx-2 transition-colors',
                cancelButton: 'bg-slate-500 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-slate-600 mx-2 transition-colors',
                popup: 'rounded-2xl'
            }
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const data = await api.delete(`/cuentas-bancarias/${id}`);
                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Eliminada', timer: 1500, showConfirmButton: false });
                        loadAccounts();
                    }
                } catch (err) {
                    Swal.fire('Error', err.message, 'error');
                }
            }
        });
    };

    return (
        <div className="space-y-6">
            <div>
                <h3 className="text-sm font-bold text-slate-700 uppercase mb-3 flex items-center gap-2">
                    <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    Cuentas Registradas
                </h3>

                {loading ? (
                    <div className="p-8 text-center text-slate-400"><div className="animate-spin rounded-full h-6 w-6 border-b-2 border-emerald-500 mx-auto"></div></div>
                ) : accounts.length === 0 ? (
                    <div className="p-6 text-center text-slate-400 italic bg-slate-50 rounded-xl border border-slate-100">Sin cuentas asociadas</div>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-3 md:hidden">
                            {accounts.map(acc => (
                                <div key={acc.id} className="bg-white border border-slate-200 p-4 rounded-xl shadow-sm relative overflow-hidden">
                                    <div className="absolute top-0 left-0 w-1.5 h-full bg-blue-500"></div>
                                    <div className="flex justify-between items-center mb-2 pl-2">
                                        <span className="font-bold text-slate-800">{acc.banco}</span>
                                        <span className="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1 rounded uppercase border border-slate-200">{acc.tipo_cuenta}</span>
                                    </div>
                                    <div className="font-mono font-bold text-slate-600 text-sm mb-4 pl-2">{acc.numero_cuenta}</div>
                                    <button onClick={() => handleDelete(acc.id)} className="w-full bg-red-50 text-red-600 border border-red-100 font-bold py-2 rounded-lg text-xs hover:bg-red-100 transition-colors flex items-center justify-center gap-2">
                                        <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        Eliminar
                                    </button>
                                </div>
                            ))}
                        </div>

                        <div className="hidden md:block border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider font-bold">
                                    <tr>
                                        <th className="px-5 py-3 text-left">Banco</th>
                                        <th className="px-5 py-3 text-left">N° Cuenta</th>
                                        <th className="px-5 py-3 text-left">Tipo</th>
                                        <th className="px-5 py-3 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white text-sm divide-y divide-slate-100">
                                    {accounts.map(acc => (
                                        <tr key={acc.id} className="hover:bg-slate-50 transition-colors">
                                            <td className="px-5 py-3 font-bold text-slate-800">{acc.banco}</td>
                                            <td className="px-5 py-3 font-mono text-slate-600">{acc.numero_cuenta}</td>
                                            <td className="px-5 py-3 text-slate-600">
                                                <span className="bg-slate-100 text-slate-600 text-[10px] font-bold px-2 py-1 rounded uppercase border border-slate-200">{acc.tipo_cuenta}</span>
                                            </td>
                                            <td className="px-5 py-3 text-right">
                                                <button onClick={() => handleDelete(acc.id)} className="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 p-2 rounded-lg transition-colors" title="Eliminar">
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>

            <div className="bg-slate-50 p-4 md:p-5 rounded-xl border border-slate-200 shadow-sm">
                <h4 className="text-xs font-bold text-slate-500 mb-3 uppercase tracking-wide">Agregar Nueva Cuenta</h4>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <input 
                        className="border border-slate-300 p-2.5 rounded-lg text-sm outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 w-full" 
                        placeholder="Nombre del Banco" 
                        value={newAccount.banco} 
                        onChange={e => setNewAccount({ ...newAccount, banco: e.target.value })} 
                    />
                    <input 
                        className="border border-slate-300 p-2.5 rounded-lg text-sm font-mono outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 w-full" 
                        placeholder="N° de Cuenta" 
                        value={newAccount.numeroCuenta} 
                        onChange={e => setNewAccount({ ...newAccount, numeroCuenta: e.target.value })} 
                    />
                    <select 
                        className="border border-slate-300 p-2.5 rounded-lg text-sm outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 w-full bg-white" 
                        value={newAccount.tipoCuenta} 
                        onChange={e => setNewAccount({ ...newAccount, tipoCuenta: e.target.value })}
                    >
                        <option value="Corriente">Cta Corriente</option>
                        <option value="Vista">Cta Vista</option>
                        <option value="Ahorro">Cta Ahorro</option>
                    </select>
                    <button 
                        onClick={handleAdd} 
                        className="bg-slate-800 text-white rounded-lg text-sm font-bold hover:bg-slate-900 shadow-md transition-colors py-2.5 px-4 w-full flex items-center justify-center gap-2"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 4v16m8-8H4"></path></svg> Agregar
                    </button>
                </div>
            </div>
        </div>
    );
};

const GestionProveedores = () => {
    const [proveedores, setProveedores] = useState([]);
    const [paises, setPaises] = useState([]); 
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(false);
    const [activeTab, setActiveTab] = useState('info'); 
    const [idError, setIdError] = useState(false);

    const initialFormState = {
        codigo: '', rut: '', razonSocial: '', paisIso: 'CL', moneda: 'CLP', nombreContacto: '', emailContacto: '', direccion: '', telefono: ''
    };
    const [formData, setFormData] = useState(initialFormState);
    const [editingId, setEditingId] = useState(null);
    
    useEffect(() => { loadData(); }, []);

    const loadData = async () => {
        setLoading(true);
        try {
            const [provRes, paisRes] = await Promise.all([
                api.get('/proveedores'),
                api.get('/paises')
            ]);

            if (provRes.success) setProveedores(provRes.data || []);
            
            if (paisRes.success && paisRes.data.length > 0) {
                setPaises(paisRes.data);
            } else {
                setPaises([{ iso: 'CL', nombre: 'Chile', moneda_defecto: 'CLP', etiqueta_id: 'RUT' }]);
            }
        } catch (err) {
            console.error("Error cargando datos:", err);
            Swal.fire('Error', 'No se pudieron cargar los datos.', 'error');
        } finally {
            setLoading(false);
        }
    };

    const getEtiquetaId = (iso) => {
        const p = paises.find(pais => pais.iso === iso);
        return p ? p.etiqueta_id : 'Identificador';
    };

    const openCreate = () => {
        setFormData(initialFormState);
        setEditingId(null);
        setActiveTab('info');
        setIdError(false);
        setModalOpen(true);
    };

    const openEdit = (prov) => {
        setFormData({
            codigo: prov.codigo_interno,
            rut: prov.rut,
            razonSocial: prov.razon_social,
            paisIso: prov.pais_iso,
            moneda: prov.moneda_defecto,
            nombreContacto: prov.nombre_contacto || '',
            emailContacto: prov.email_contacto || '',
            direccion: prov.direccion || '',
            telefono: prov.telefono || ''
        });
        setEditingId(prov.id);
        setActiveTab('info');
        setIdError(false);
        setModalOpen(true);
    };

    const handleIdChange = (e) => {
        const val = e.target.value;
        const pais = formData.paisIso;
        const formatted = formatearIdentificador(val, pais);
        setFormData(prev => ({ ...prev, rut: formatted }));
        const cleanVal = formatted.replace(/[^0-9kK]/g, '');
        if (cleanVal.length > 4) {
            const isValid = validarIdentificador(formatted, pais);
            setIdError(!isValid);
        } else {
            setIdError(false);
        }
    };

    const handlePaisChange = (e) => {
        const newIso = e.target.value;
        const pInfo = paises.find(p => p.iso === newIso);
        
        setFormData(prev => ({ 
            ...prev, 
            paisIso: newIso, 
            rut: '',
            moneda: pInfo ? pInfo.moneda_defecto : 'USD'
        }));
        setIdError(false);
    };

    const handleSaveInfo = async () => {
        if (idError) {
            return Swal.fire({
                icon: 'error',
                title: 'Formato Inválido',
                text: `El ${getEtiquetaId(formData.paisIso)} ingresado no es válido.`,
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' },
                buttonsStyling: false
            });
        }
        
        if(!formData.razonSocial) {
            return Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'La Razón Social es obligatoria',
                customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg hover:bg-amber-600' },
                buttonsStyling: false
            });
        }

        if (editingId) {
            return Swal.fire({
                icon: 'info',
                title: 'Aviso',
                text: 'La edición está limitada en esta versión demo.',
                customClass: { confirmButton: 'bg-blue-500 text-white font-bold py-2 px-6 rounded-lg' },
                buttonsStyling: false
            });
        }

        try {
            const data = await api.post('/proveedores', formData);
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Proveedor Guardado',
                    text: `Código generado: ${data.codigo_generado}`,
                    timer: 2000,
                    showConfirmButton: false
                });
                setEditingId(data.id);
                setFormData(prev => ({ ...prev, codigo: data.codigo_generado }));
                const refresh = await api.get('/proveedores');
                if(refresh.success) setProveedores(refresh.data);
                setActiveTab('bank');

            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (err) {
            Swal.fire('Error', err.message || 'Ocurrió un error al guardar.', 'error');
        }
    };

    return (
        <div className="max-w-6xl mx-auto p-4 md:p-6 font-sans text-gray-800 pb-10">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 className="text-2xl md:text-3xl font-bold text-slate-900">Proveedores</h1>
                    <p className="text-slate-500 text-sm mt-1">Base de datos global de acreedores</p>
                </div>
                <button onClick={openCreate} className="w-full sm:w-auto bg-emerald-600 text-white px-5 py-2.5 rounded-lg shadow hover:bg-emerald-700 font-bold flex justify-center gap-2 items-center transition-transform active:scale-95">
                    <span className="text-lg leading-none">+</span> Nuevo Proveedor
                </button>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                {loading ? (
                    <div className="p-10 text-center text-slate-400">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500 mx-auto mb-3"></div>
                        <p className="font-medium">Cargando datos...</p>
                    </div>
                ) : proveedores.length === 0 ? (
                    <div className="p-10 text-center text-slate-400">
                        <svg className="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        <p className="font-medium">No hay proveedores registrados.</p>
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-4 p-4 md:hidden bg-slate-50">
                            {proveedores.map(prov => (
                                <div key={prov.id} className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm relative">
                                    <div className="absolute top-0 left-0 w-1.5 h-full rounded-l-xl bg-slate-400"></div>
                                    <div className="flex justify-between items-start mb-2 pl-2">
                                        <div>
                                            <div className="text-xs font-bold text-emerald-600 font-mono mb-0.5">{prov.codigo_interno}</div>
                                            <h3 className="font-bold text-slate-800 leading-tight">{prov.razon_social}</h3>
                                        </div>
                                        <span className="px-2 py-0.5 text-[10px] font-bold bg-slate-100 rounded text-slate-600 border border-slate-200 uppercase">
                                            {prov.pais_iso}
                                        </span>
                                    </div>
                                    <div className="space-y-1.5 mb-4 pl-2 mt-3">
                                        <div className="text-sm font-mono text-slate-600 flex items-center gap-2">
                                            <span className="font-bold text-xs text-slate-400 w-6">ID:</span> {prov.rut}
                                        </div>
                                        <div className="text-sm text-slate-700 flex items-center gap-2">
                                            <span className="font-bold text-xs text-slate-400 w-6">CTO:</span> 
                                            <span className="truncate">{prov.nombre_contacto || 'Sin contacto'}</span>
                                        </div>
                                    </div>
                                    <div className="border-t border-slate-100 pt-3 pl-2">
                                        <button onClick={() => openEdit(prov)} className="w-full bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-sm py-2 rounded-lg transition-colors border border-emerald-100 flex items-center justify-center gap-2">
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                            Gestionar
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="hidden md:block overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Código</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Empresa / Razón Social</th>
                                        <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Identificador Fiscal</th>
                                        <th className="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">País</th>
                                        <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-slate-100">
                                    {proveedores.map(prov => (
                                        <tr key={prov.id} className="hover:bg-slate-50 transition-colors group">
                                            <td className="px-6 py-4 font-mono font-bold text-emerald-600 text-sm whitespace-nowrap">{prov.codigo_interno}</td>
                                            <td className="px-6 py-4">
                                                <div className="font-bold text-slate-800">{prov.razon_social}</div>
                                                <div className="text-xs text-slate-400 mt-0.5"><i className="fas fa-user mr-1"></i> {prov.nombre_contacto || '---'}</div>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-slate-600 font-mono whitespace-nowrap">{prov.rut}</td>
                                            <td className="px-6 py-4 text-center whitespace-nowrap">
                                                <span className="px-2.5 py-1 text-[10px] font-bold bg-slate-100 rounded text-slate-600 border border-slate-200 uppercase">
                                                    {prov.pais_iso}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 text-right whitespace-nowrap">
                                                <button onClick={() => openEdit(prov)} className="text-emerald-700 hover:text-emerald-900 bg-emerald-50 hover:bg-emerald-100 border border-emerald-100 font-bold text-sm px-4 py-1.5 rounded-lg transition-colors opacity-100 md:opacity-0 group-hover:opacity-100 flex items-center gap-2 ml-auto">
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                    Gestionar
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>
            {modalOpen && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 md:p-6 animate-fade-in">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[95vh] md:max-h-[90vh] animate-slide-up">
                        
                        <div className="bg-slate-900 p-4 md:p-5 border-b border-slate-800 flex justify-between items-center text-white shrink-0">
                            <h2 className="text-lg md:text-xl font-bold flex items-center gap-2">
                                <svg className="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                {editingId ? 'Gestionar Proveedor' : 'Alta de Nuevo Proveedor'}
                            </h2>
                            <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-white transition-colors text-3xl leading-none">&times;</button>
                        </div>

                        <div className="flex overflow-x-auto border-b bg-slate-50 hide-scrollbar shrink-0">
                            <button onClick={() => setActiveTab('info')} className={`flex-1 py-3 px-4 text-sm font-bold border-b-2 transition whitespace-nowrap ${activeTab === 'info' ? 'border-emerald-500 text-emerald-700 bg-white' : 'border-transparent text-slate-500 hover:bg-white'}`}>
                                <i className="fas fa-info-circle mr-2"></i> Datos Generales
                            </button>
                            <button onClick={() => setActiveTab('bank')} disabled={!editingId} className={`flex-1 py-3 px-4 text-sm font-bold border-b-2 transition whitespace-nowrap ${activeTab === 'bank' ? 'border-emerald-500 text-emerald-700 bg-white' : 'border-transparent text-slate-400 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-white'}`}>
                                <i className="fas fa-university mr-2"></i> Datos Bancarios
                            </button>
                        </div>

                        <div className="p-4 md:p-6 overflow-y-auto bg-white flex-grow custom-scrollbar">
                            {activeTab === 'info' && (
                                <div className="space-y-6">
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
                                        <div className="sm:col-span-1">
                                            <label className="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Código Sistema</label>
                                            <input className="w-full border border-slate-200 rounded-lg p-2.5 bg-slate-100 font-mono font-bold text-slate-500 text-center outline-none cursor-not-allowed" value={formData.codigo || 'AUTO'} disabled />
                                        </div>
                                        <div className="sm:col-span-2">
                                            <label className="block text-xs font-bold text-slate-500 mb-1.5 flex justify-between uppercase tracking-wide">
                                                <span>{getEtiquetaId(formData.paisIso)} <span className="text-slate-400 font-normal normal-case ml-1">(Fiscal ID)</span></span>
                                                {idError && <span className="text-red-500 animate-pulse font-black">FORMATO INVÁLIDO</span>}
                                            </label>
                                            <input 
                                                className={`w-full border rounded-lg p-2.5 font-mono text-base outline-none transition-all
                                                    ${idError ? 'border-red-500 bg-red-50 focus:ring-red-200' : 'border-slate-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100'}`} 
                                                value={formData.rut} 
                                                onChange={handleIdChange} 
                                                placeholder="Ingrese número..." 
                                                maxLength={20}
                                            />
                                            <p className="text-[10px] text-slate-400 mt-1 text-right">Se validará automáticamente según el país.</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Razón Social / Nombre Fantasía</label>
                                        <input className="w-full border border-slate-300 rounded-lg p-2.5 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition-all text-slate-800 font-medium" value={formData.razonSocial} onChange={e => setFormData({ ...formData, razonSocial: e.target.value })} placeholder="Ej: Importadora Comercializadora..." />
                                    </div>

                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">País de Residencia</label>
                                            <select className="w-full border border-slate-300 rounded-lg p-2.5 bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition-all font-medium text-slate-700" value={formData.paisIso} onChange={handlePaisChange}>
                                                {paises.map(p => (
                                                    <option key={p.iso} value={p.iso}>{p.nombre}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wide">Moneda de Pago</label>
                                            <select className="w-full border border-slate-300 rounded-lg p-2.5 bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition-all font-medium text-slate-700 font-mono" value={formData.moneda} onChange={e => setFormData({ ...formData, moneda: e.target.value })}>
                                                <option value="CLP">CLP - Peso Chileno</option>
                                                <option value="USD">USD - Dólar Americano</option>
                                                <option value="EUR">EUR - Euro</option>
                                                <option value="PEN">PEN - Sol Peruano</option>
                                                <option value="ARS">ARS - Peso Argentino</option>
                                                <option value="BRL">BRL - Real Brasileño</option>
                                                <option value="MXN">MXN - Peso Mexicano</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div className="border-t border-slate-100 pt-5 mt-2">
                                        <h4 className="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                            <i className="fas fa-address-book"></i> Datos de Contacto
                                        </h4>
                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                            <div>
                                                <label className="block text-xs font-bold text-slate-500 mb-1.5">Nombre Representante</label>
                                                <input className="w-full border border-slate-300 rounded-lg p-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all text-slate-700" value={formData.nombreContacto} onChange={e => setFormData({ ...formData, nombreContacto: e.target.value })} placeholder="Persona de contacto..." />
                                            </div>
                                            <div>
                                                <label className="block text-xs font-bold text-slate-500 mb-1.5">Email de Facturación / Pagos</label>
                                                <input type="email" className="w-full border border-slate-300 rounded-lg p-2.5 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 transition-all text-slate-700" value={formData.emailContacto} onChange={e => setFormData({ ...formData, emailContacto: e.target.value })} placeholder="pagos@empresa.com" />
                                            </div>
                                        </div>
                                    </div>

                                    {!editingId && (
                                        <div className="pt-6 flex flex-col sm:flex-row justify-end border-t border-slate-100 mt-6 gap-3">
                                            <button onClick={() => setModalOpen(false)} className="w-full sm:w-auto px-6 py-2.5 text-slate-500 hover:bg-slate-100 rounded-lg font-bold transition-colors">Cancelar</button>
                                            <button onClick={handleSaveInfo} className="w-full sm:w-auto bg-emerald-600 text-white px-8 py-2.5 rounded-lg font-bold shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 hover:shadow-emerald-600/50 transition-all flex items-center justify-center gap-2">
                                                <i className="fas fa-save"></i> Guardar Proveedor
                                            </button>
                                        </div>
                                    )}
                                </div>
                            )}

                            {activeTab === 'bank' && editingId && <BankAccountsTab proveedorId={editingId} />}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionProveedores;