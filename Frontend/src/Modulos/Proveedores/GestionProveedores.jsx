import React, { useState, useEffect } from 'react';
import { api } from '../../Configuracion/api'; 
import GenericModal from "../../Componentes/ModalGenerico";
import { formatearIdentificador, validarIdentificador } from '../../Utilidades/identificadores';

// --- SUB-COMPONENTE: TABLA DE CUENTAS BANCARIAS ---
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
        if (!newAccount.banco || !newAccount.numeroCuenta) return alert("Faltan datos");
        try {
            const data = await api.post('/cuentas-bancarias', { ...newAccount, proveedorId });
            if (data.success) {
                loadAccounts();
                setNewAccount({ banco: '', numeroCuenta: '', tipoCuenta: 'Corriente', paisIso: 'CL' });
            } else {
                alert(data.message);
            }
        } catch (err) {
            alert(err.message || "Error al guardar cuenta");
        }
    };

    const handleDelete = async (id) => {
        if (!window.confirm("¿Borrar cuenta?")) return;
        try {
            const data = await api.delete(`/cuentas-bancarias/${id}`);
            if (data.success) loadAccounts();
        } catch (err) {
            alert("Error: " + err.message);
        }
    };

    return (
        <div className="space-y-4">
            <h3 className="text-sm font-bold text-gray-700 uppercase">Cuentas Registradas</h3>
            <div className="border rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50 text-xs">
                        <tr>
                            <th className="px-4 py-2 text-left">Banco</th>
                            <th className="px-4 py-2 text-left">N° Cuenta</th>
                            <th className="px-4 py-2 text-left">Tipo</th>
                            <th className="px-4 py-2 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white text-sm">
                        {loading ? <tr><td colSpan="4" className="p-4 text-center">Cargando...</td></tr> :
                            accounts.length === 0 ? <tr><td colSpan="4" className="p-4 text-center text-gray-400 italic">Sin cuentas asociadas</td></tr> :
                                accounts.map(acc => (
                                    <tr key={acc.id} className="border-t">
                                        <td className="px-4 py-2 font-bold">{acc.banco}</td>
                                        <td className="px-4 py-2 font-mono">{acc.numero_cuenta}</td>
                                        <td className="px-4 py-2">{acc.tipo_cuenta}</td>
                                        <td className="px-4 py-2 text-right">
                                            <button onClick={() => handleDelete(acc.id)} className="text-red-500 hover:text-red-700 text-xs font-bold">ELIMINAR</button>
                                        </td>
                                    </tr>
                                ))}
                    </tbody>
                </table>
            </div>
            <div className="bg-slate-50 p-4 rounded-lg border border-slate-200">
                <h4 className="text-xs font-bold text-slate-500 mb-2 uppercase">Agregar Nueva Cuenta</h4>
                <div className="grid grid-cols-2 gap-2 mb-2">
                    <input className="border p-2 rounded text-sm" placeholder="Banco" value={newAccount.banco} onChange={e => setNewAccount({ ...newAccount, banco: e.target.value })} />
                    <input className="border p-2 rounded text-sm" placeholder="N° Cuenta" value={newAccount.numeroCuenta} onChange={e => setNewAccount({ ...newAccount, numeroCuenta: e.target.value })} />
                    <select className="border p-2 rounded text-sm" value={newAccount.tipoCuenta} onChange={e => setNewAccount({ ...newAccount, tipoCuenta: e.target.value })}>
                        <option value="Corriente">Cta Corriente</option>
                        <option value="Vista">Cta Vista</option>
                        <option value="Ahorro">Cta Ahorro</option>
                    </select>
                    <button onClick={handleAdd} className="bg-slate-800 text-white rounded text-sm font-bold hover:bg-slate-900">Agregar</button>
                </div>
            </div>
        </div>
    );
};

// --- COMPONENTE PRINCIPAL ---
const GestionProveedores = () => {
    const [proveedores, setProveedores] = useState([]);
    const [paises, setPaises] = useState([]); 
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(false);
    const [activeTab, setActiveTab] = useState('info'); 
    const [successMsg, setSuccessMsg] = useState(null);
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
            alert(`El ${getEtiquetaId(formData.paisIso)} ingresado no es válido.`);
            return;
        }
        
        if(!formData.razonSocial) return alert("La Razón Social es obligatoria");

        if (editingId) {
            alert("Edición limitada en demo."); return;
        }

        try {
            const data = await api.post('/proveedores', formData);
            if (data.success) {
                setSuccessMsg(`Proveedor guardado exitosamente. Código: ${data.codigo_generado}`);
                setEditingId(data.id);
                setFormData(prev => ({ ...prev, codigo: data.codigo_generado }));
                const refresh = await api.get('/proveedores');
                if(refresh.success) setProveedores(refresh.data);
                setActiveTab('bank');

            } else {
                alert("Error: " + data.message);
            }
        } catch (err) {
            alert("Error al guardar: " + err.message);
        }
    };

    return (
        <div className="max-w-6xl mx-auto p-6 font-sans text-gray-800">
            <GenericModal isOpen={!!successMsg} type="success" title="Operación Exitosa" message={successMsg} onClose={() => setSuccessMsg(null)} />

            <div className="flex justify-between items-center mb-8">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">Proveedores</h1>
                    <p className="text-slate-500 text-sm">Base de datos global de acreedores</p>
                </div>
                <button onClick={openCreate} className="bg-emerald-600 text-white px-5 py-2.5 rounded-lg shadow hover:bg-emerald-700 font-bold flex gap-2 items-center transition-transform active:scale-95">
                    <span>+</span> Nuevo Proveedor
                </button>
            </div>

            {/* TABLA PRINCIPAL */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-slate-50">
                        <tr>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase">Código</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase">Empresa / Razón Social</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase">Identificador Fiscal</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase">País</th>
                            <th className="px-6 py-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {loading ? <tr><td colSpan="5" className="p-10 text-center text-slate-400">Cargando datos...</td></tr> : 
                        proveedores.length === 0 ? <tr><td colSpan="5" className="p-10 text-center text-slate-400">No hay registros.</td></tr> :
                        proveedores.map(prov => (
                            <tr key={prov.id} className="hover:bg-slate-50 transition-colors">
                                <td className="px-6 py-4 font-mono font-bold text-slate-600">{prov.codigo_interno}</td>
                                <td className="px-6 py-4">
                                    <div className="font-bold text-slate-800">{prov.razon_social}</div>
                                    <div className="text-xs text-slate-400">{prov.nombre_contacto}</div>
                                </td>
                                <td className="px-6 py-4 text-sm text-slate-600 font-mono">{prov.rut}</td>
                                <td className="px-6 py-4">
                                    <span className="px-2 py-1 text-xs font-bold bg-slate-100 rounded text-slate-600 border border-slate-200">
                                        {prov.pais_iso}
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-right">
                                    <button onClick={() => openEdit(prov)} className="text-emerald-600 font-bold text-sm hover:underline">Gestionar</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* MODAL CREAR/EDITAR */}
            {modalOpen && (
                <div className="fixed inset-0 bg-slate-900 bg-opacity-75 flex items-center justify-center z-50 p-4 backdrop-blur-sm animate-fade-in">
                    <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                        
                        <div className="bg-slate-100 p-4 border-b flex justify-between items-center">
                            <h2 className="text-lg font-bold text-slate-800">{editingId ? 'Editar Proveedor' : 'Alta de Proveedor'}</h2>
                            <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-red-500 text-xl transition-colors">&times;</button>
                        </div>

                        <div className="flex border-b bg-white">
                            <button onClick={() => setActiveTab('info')} className={`flex-1 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'info' ? 'border-emerald-500 text-emerald-700 bg-emerald-50' : 'border-transparent text-slate-500 hover:bg-slate-50'}`}>
                                Datos Generales
                            </button>
                            <button onClick={() => setActiveTab('bank')} disabled={!editingId} className={`flex-1 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'bank' ? 'border-emerald-500 text-emerald-700 bg-emerald-50' : 'border-transparent text-slate-500 disabled:opacity-50'}`}>
                                Datos Bancarios
                            </button>
                        </div>

                        <div className="p-6 overflow-y-auto bg-white">
                            {activeTab === 'info' && (
                                <div className="space-y-5">
                                    <div className="grid grid-cols-3 gap-5">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1">Código Sistema</label>
                                            <input className="w-full border rounded p-2 bg-gray-100 font-mono font-bold text-slate-500 text-center" value={formData.codigo || 'AUTO'} disabled />
                                        </div>
                                        <div className="col-span-2">
                                            <label className="block text-xs font-bold text-slate-500 mb-1 flex justify-between">
                                                <span>{getEtiquetaId(formData.paisIso)} <span className="text-slate-300 font-normal ml-1">(Fiscal ID)</span></span>
                                                {idError && <span className="text-red-500 animate-pulse">FORMATO INVÁLIDO</span>}
                                            </label>
                                            <input 
                                                className={`w-full border rounded p-2 font-mono text-lg outline-none transition-all
                                                    ${idError ? 'border-red-500 bg-red-50 focus:ring-red-200' : 'border-gray-300 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100'}`} 
                                                value={formData.rut} 
                                                onChange={handleIdChange} 
                                                placeholder="Ingrese número..." 
                                                maxLength={20}
                                            />
                                            <p className="text-[10px] text-slate-400 mt-1 text-right">El sistema validará el formato automáticamente según el país.</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 mb-1">Razón Social / Nombre Fantasía</label>
                                        <input className="w-full border border-gray-300 rounded p-2 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none" value={formData.razonSocial} onChange={e => setFormData({ ...formData, razonSocial: e.target.value })} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-5">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1">País de Residencia</label>
                                            <select className="w-full border border-gray-300 rounded p-2 bg-white" value={formData.paisIso} onChange={handlePaisChange}>
                                                {paises.map(p => (
                                                    <option key={p.iso} value={p.iso}>{p.nombre}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1">Moneda de Pago</label>
                                            <select className="w-full border border-gray-300 rounded p-2 bg-white" value={formData.moneda} onChange={e => setFormData({ ...formData, moneda: e.target.value })}>
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

                                    <div className="border-t border-slate-100 pt-4 mt-2">
                                        <h4 className="text-xs font-bold text-slate-400 uppercase mb-3">Datos de Contacto</h4>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <label className="block text-xs text-slate-500 mb-1">Nombre Contacto</label>
                                                <input className="w-full border border-gray-300 rounded p-2 text-sm" value={formData.nombreContacto} onChange={e => setFormData({ ...formData, nombreContacto: e.target.value })} />
                                            </div>
                                            <div>
                                                <label className="block text-xs text-slate-500 mb-1">Email Facturación</label>
                                                <input className="w-full border border-gray-300 rounded p-2 text-sm" value={formData.emailContacto} onChange={e => setFormData({ ...formData, emailContacto: e.target.value })} />
                                            </div>
                                        </div>
                                    </div>

                                    {!editingId && (
                                        <div className="pt-4 flex justify-end border-t mt-4">
                                            <button onClick={() => setModalOpen(false)} className="px-4 py-2 text-slate-500 hover:text-slate-700 font-bold mr-2">Cancelar</button>
                                            <button onClick={handleSaveInfo} className="bg-emerald-600 text-white px-8 py-2 rounded-lg font-bold shadow hover:bg-emerald-700 transition-colors">
                                                Guardar Proveedor
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