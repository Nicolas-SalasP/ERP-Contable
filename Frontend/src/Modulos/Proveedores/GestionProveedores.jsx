import React, { useState, useEffect } from 'react';
import GenericModal from "../../Componentes/ModalGenerico";

// --- SUB-COMPONENTE: TABLA DE CUENTAS BANCARIAS ---
const BankAccountsTab = ({ proveedorId }) => {
    const [accounts, setAccounts] = useState([]);
    const [loading, setLoading] = useState(false);
    const [newAccount, setNewAccount] = useState({ banco: '', numeroCuenta: '', tipoCuenta: 'Corriente', paisIso: 'CL' });

    useEffect(() => {
        if (proveedorId) loadAccounts();
    }, [proveedorId]);

    const loadAccounts = () => {
        setLoading(true);
        fetch(`http://localhost/ERP-Contable/Backend/Public/api/cuentas-bancarias/proveedor/${proveedorId}`)
            .then(res => res.json())
            .then(data => { if (data.success) setAccounts(data.data); })
            .catch(err => console.error("Error cargando cuentas", err))
            .finally(() => setLoading(false));
    };

    const handleAdd = () => {
        if (!newAccount.banco || !newAccount.numeroCuenta) return alert("Faltan datos");

        fetch('http://localhost/ERP-Contable/Backend/Public/api/cuentas-bancarias', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...newAccount, proveedorId })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadAccounts();
                    setNewAccount({ banco: '', numeroCuenta: '', tipoCuenta: 'Corriente', paisIso: 'CL' });
                } else alert(data.message);
            });
    };

    const handleDelete = (id) => {
        if (!window.confirm("¿Borrar cuenta?")) return;
        fetch(`http://localhost/ERP-Contable/Backend/Public/api/cuentas-bancarias/${id}`, { method: 'DELETE' })
            .then(res => res.json())
            .then(data => { if (data.success) loadAccounts(); });
    };

    return (
        <div className="space-y-4">
            <h3 className="text-sm font-bold text-gray-700 uppercase">Cuentas Registradas</h3>

            {/* Lista de Cuentas */}
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

            {/* Formulario Agregar Cuenta */}
            <div className="bg-slate-50 p-4 rounded-lg border border-slate-200">
                <h4 className="text-xs font-bold text-slate-500 mb-2 uppercase">Agregar Nueva Cuenta</h4>
                <div className="grid grid-cols-2 gap-2 mb-2">
                    <input className="border p-2 rounded text-sm" placeholder="Banco (Ej: Banco de Chile)" value={newAccount.banco} onChange={e => setNewAccount({ ...newAccount, banco: e.target.value })} />
                    <input className="border p-2 rounded text-sm" placeholder="N° Cuenta" value={newAccount.numeroCuenta} onChange={e => setNewAccount({ ...newAccount, numeroCuenta: e.target.value })} />
                    <select className="border p-2 rounded text-sm" value={newAccount.tipoCuenta} onChange={e => setNewAccount({ ...newAccount, tipoCuenta: e.target.value })}>
                        <option value="Corriente">Cta Corriente</option>
                        <option value="Vista">Cta Vista / RUT</option>
                        <option value="Ahorro">Cta Ahorro</option>
                    </select>
                    <button onClick={handleAdd} className="bg-slate-800 text-white rounded text-sm font-bold hover:bg-slate-900">Agregar Cuenta</button>
                </div>
            </div>
        </div>
    );
};

// --- COMPONENTE PRINCIPAL ---
const GestionProveedores = () => {
    const [proveedores, setProveedores] = useState([]);
    const [loading, setLoading] = useState(true);
    const [modalOpen, setModalOpen] = useState(false);
    const [activeTab, setActiveTab] = useState('info'); // 'info' o 'bank'
    const [successMsg, setSuccessMsg] = useState(null);

    const initialFormState = {
        codigo: '', rut: '', razonSocial: '', paisIso: 'CL', moneda: 'CLP', nombreContacto: '', emailContacto: '', direccion: '', telefono: ''
    };
    const [formData, setFormData] = useState(initialFormState);
    const [editingId, setEditingId] = useState(null);

    useEffect(() => { loadProveedores(); }, []);

    const loadProveedores = () => {
        setLoading(true);
        fetch('http://localhost/ERP-Contable/Backend/Public/api/proveedores')
            .then(res => res.json())
            .then(data => { if (data.success) setProveedores(data.data); })
            .catch(err => console.error("Error:", err))
            .finally(() => setLoading(false));
    };

    const openCreate = () => {
        setFormData(initialFormState);
        setEditingId(null);
        setActiveTab('info');
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
        setModalOpen(true);
    };

    const handleSaveInfo = () => {
        if (editingId) {
            alert("La edición de datos principales está deshabilitada en esta demo."); return;
        }

        fetch('http://localhost/ERP-Contable/Backend/Public/api/proveedores', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    setSuccessMsg(`Proveedor Creado. Código asignado: ${data.codigo_generado}`);

                    setEditingId(data.id);
                    setFormData(prev => ({ ...prev, codigo: data.codigo_generado }));

                    loadProveedores();

                    if (window.confirm(`Proveedor creado con código ${data.codigo_generado}. ¿Desea agregar cuentas ahora?`)) {
                        setActiveTab('bank');
                    } else {
                        setModalOpen(false);
                    }
                } else {
                    alert("Error: " + data.message);
                }
            });
    };

    return (
        <div className="max-w-6xl mx-auto p-6 font-sans text-gray-800">

            {/* Modal para Mensajes de Éxito */}
            <GenericModal
                isOpen={!!successMsg}
                type="success"
                title="Éxito"
                message={successMsg}
                onConfirm={() => setSuccessMsg(null)}
                onClose={() => setSuccessMsg(null)}
            />

            <div className="flex justify-between items-center mb-8">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">Proveedores</h1>
                    <p className="text-slate-500 text-sm">Gestión de acreedores y datos bancarios</p>
                </div>
                <button onClick={openCreate} className="bg-emerald-600 text-white px-5 py-2.5 rounded-lg shadow hover:bg-emerald-700 font-bold transition flex items-center gap-2">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4v16m8-8H4"></path></svg>
                    Nuevo Proveedor
                </button>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-slate-50">
                        <tr>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Código</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Razón Social</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">RUT</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">País</th>
                            <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {loading ? (
                            <tr><td colSpan="5" className="p-10 text-center text-slate-400">Cargando proveedores...</td></tr>
                        ) : proveedores.length === 0 ? (
                            <tr><td colSpan="5" className="p-10 text-center text-slate-400">No hay proveedores registrados.</td></tr>
                        ) : proveedores.map(prov => (
                            <tr key={prov.id} className="hover:bg-slate-50 transition">
                                <td className="px-6 py-4 font-mono font-bold text-slate-600">{prov.codigo_interno}</td>
                                <td className="px-6 py-4">
                                    <div className="font-bold text-slate-800">{prov.razon_social}</div>
                                    <div className="text-xs text-slate-400">{prov.nombre_contacto}</div>
                                </td>
                                <td className="px-6 py-4 text-sm text-slate-600">{prov.rut || '-'}</td>
                                <td className="px-6 py-4">
                                    <span className={`px-2 py-1 text-xs font-bold rounded ${prov.pais_iso === 'CL' ? 'bg-blue-50 text-blue-700' : 'bg-orange-50 text-orange-700'}`}>
                                        {prov.pais_iso}
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-right">
                                    <button onClick={() => openEdit(prov)} className="text-emerald-600 hover:text-emerald-800 font-bold text-sm">Gestionar</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* MODAL PRINCIPAL (CREAR / EDITAR) */}
            {modalOpen && (
                <div className="fixed inset-0 bg-slate-900 bg-opacity-75 flex items-center justify-center z-50 p-4 backdrop-blur-sm animate-fade-in">
                    <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">

                        <div className="bg-slate-100 p-4 border-b flex justify-between items-center">
                            <h2 className="text-lg font-bold text-slate-800">
                                {editingId ? `Gestionar: ${formData.razonSocial}` : 'Nuevo Proveedor'}
                            </h2>
                            <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <div className="flex border-b">
                            <button
                                onClick={() => setActiveTab('info')}
                                className={`flex-1 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'info' ? 'border-emerald-500 text-emerald-700 bg-emerald-50' : 'border-transparent text-slate-500 hover:bg-slate-50'}`}
                            >
                                Información General
                            </button>
                            <button
                                onClick={() => setActiveTab('bank')}
                                disabled={!editingId}
                                className={`flex-1 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'bank' ? 'border-emerald-500 text-emerald-700 bg-emerald-50' : 'border-transparent text-slate-500 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed'}`}
                            >
                                Cuentas Bancarias {(!editingId) && <span className="text-xs font-normal">(Guarde primero)</span>}
                            </button>
                        </div>

                        {/* Modal Body */}
                        <div className="p-6 overflow-y-auto">

                            {/* TAB 1: INFO GENERAL */}
                            {activeTab === 'info' && (
                                <div className="space-y-4">
                                    <div className="grid grid-cols-3 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1">Código Interno</label>
                                            {editingId ? (
                                                <input
                                                    className="w-full border rounded p-2 bg-gray-100 font-mono font-bold text-slate-600"
                                                    value={formData.codigo}
                                                    disabled
                                                />
                                            ) : (
                                                <div className="w-full border rounded p-2 bg-slate-50 text-slate-400 text-sm italic border-dashed">
                                                    (Autogenerado)
                                                </div>
                                            )}
                                        </div>
                                        <div className="col-span-2">
                                            <label className="block text-xs font-bold text-slate-500 mb-1">RUT / Tax ID</label>
                                            <input className="w-full border rounded p-2 focus:ring-2 focus:ring-emerald-500 outline-none" value={formData.rut} onChange={e => setFormData({ ...formData, rut: e.target.value })} placeholder="Ej: 76.111.222-3" />
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-bold text-slate-500 mb-1">Razón Social</label>
                                        <input className="w-full border rounded p-2 focus:ring-2 focus:ring-emerald-500 outline-none" value={formData.razonSocial} onChange={e => setFormData({ ...formData, razonSocial: e.target.value })} placeholder="Nombre de la empresa" />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1">País</label>
                                            <select className="w-full border rounded p-2" value={formData.paisIso} onChange={e => setFormData({ ...formData, paisIso: e.target.value })}>
                                                <option value="CL">Chile</option>
                                                <option value="US">Estados Unidos</option>
                                                <option value="DK">Dinamarca</option>
                                                <option value="PE">Perú</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1">Moneda Defecto</label>
                                            <select className="w-full border rounded p-2" value={formData.moneda} onChange={e => setFormData({ ...formData, moneda: e.target.value })}>
                                                <option value="CLP">Peso Chileno (CLP)</option>
                                                <option value="USD">Dólar (USD)</option>
                                                <option value="EUR">Euro (EUR)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4 border-t pt-4 mt-4">
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1">Contacto Principal</label>
                                            <input className="w-full border rounded p-2" value={formData.nombreContacto} onChange={e => setFormData({ ...formData, nombreContacto: e.target.value })} placeholder="Nombre persona" />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-bold text-slate-500 mb-1">Email</label>
                                            <input className="w-full border rounded p-2" value={formData.emailContacto} onChange={e => setFormData({ ...formData, emailContacto: e.target.value })} placeholder="contacto@empresa.com" />
                                        </div>
                                    </div>

                                    {!editingId && (
                                        <div className="pt-4 flex justify-end">
                                            <button onClick={handleSaveInfo} className="bg-emerald-600 text-white px-6 py-2 rounded-lg font-bold shadow hover:bg-emerald-700">
                                                Guardar Proveedor
                                            </button>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* TAB 2: CUENTAS BANCARIAS */}
                            {activeTab === 'bank' && editingId && (
                                <BankAccountsTab proveedorId={editingId} />
                            )}

                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionProveedores;