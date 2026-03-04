import React, { useEffect, useState } from 'react';
import { api } from '../../Configuracion/api';
import ModalGenerico from '../../Componentes/ModalGenerico';
import FormularioCliente from './Componentes/FormularioCliente';

const GestionClientes = () => {
    const [clientes, setClientes] = useState([]);
    const [loading, setLoading] = useState(true);
    const [busqueda, setBusqueda] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [activeTab, setActiveTab] = useState('info'); 
    const [editingId, setEditingId] = useState(null);
    const [clienteSeleccionado, setClienteSeleccionado] = useState(null);

    const cargarClientes = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/clientes?search=${busqueda}`);
            if (res.success) setClientes(res.data || []);
        } catch (error) {
            console.error("Error al cargar clientes", error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timeoutId = setTimeout(cargarClientes, 300);
        return () => clearTimeout(timeoutId);
    }, [busqueda]);

    const openCreate = () => {
        setClienteSeleccionado(null);
        setEditingId(null);
        setActiveTab('info');
        setModalOpen(true);
    };

    const openEdit = (cliente) => {
        setClienteSeleccionado(cliente);
        setEditingId(cliente.id);
        setActiveTab('info');
        setModalOpen(true);
    };

    const handleBloquear = async (id) => {
        if (!window.confirm("¿Estás seguro de bloquear este cliente?")) return;
        try {
            const res = await api.delete(`/clientes/${id}`);
            if (res.success) cargarClientes();
        } catch (error) {
            alert("Error al bloquear: " + error.message);
        }
    };

    return (
        <div className="max-w-6xl mx-auto p-6 font-sans text-gray-800">
            <div className="flex justify-between items-center mb-8">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">Clientes</h1>
                    <p className="text-slate-500 text-sm">Base de datos centralizada de clientes activos</p>
                </div>
                <button onClick={openCreate} className="bg-emerald-600 text-white px-5 py-2.5 rounded-lg shadow hover:bg-emerald-700 font-bold flex gap-2 items-center transition-transform active:scale-95">
                    <span>+</span> Nuevo Cliente
                </button>
            </div>

            {/* BARRA DE BÚSQUEDA IGUAL A PROVEEDORES */}
            <div className="mb-4">
                <input 
                    type="text" 
                    placeholder="Filtrar por RUT, Razón Social o Código..." 
                    className="w-full md:w-96 border border-slate-200 p-2.5 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white transition-all shadow-sm"
                    value={busqueda}
                    onChange={(e) => setBusqueda(e.target.value)}
                />
            </div>

            {/* TABLA PRINCIPAL */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-slate-50">
                        <tr>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase">Código / Identificador</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase">Razón Social / Empresa</th>
                            <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase">Contacto</th>
                            <th className="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase">Estado</th>
                            <th className="px-6 py-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {loading ? (
                            <tr><td colSpan="5" className="p-10 text-center text-slate-400">Cargando clientes...</td></tr>
                        ) : clientes.length === 0 ? (
                            <tr><td colSpan="5" className="p-10 text-center text-slate-400">No hay registros coincidentes.</td></tr>
                        ) : clientes.map(c => (
                            <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                                <td className="px-6 py-4">
                                    <div className="text-xs font-bold text-emerald-600 font-mono mb-1">{c.codigo_cliente}</div>
                                    <div className="text-sm font-mono text-slate-600">{c.rut}</div>
                                </td>
                                <td className="px-6 py-4">
                                    <div className="font-bold text-slate-800">{c.razon_social}</div>
                                    <div className="text-xs text-slate-400 truncate max-w-xs">{c.direccion}</div>
                                </td>
                                <td className="px-6 py-4">
                                    <div className="text-sm text-slate-700">{c.contacto_nombre || 'Sin contacto'}</div>
                                    <div className="text-xs text-slate-400">{c.contacto_email}</div>
                                </td>
                                <td className="px-6 py-4 text-center">
                                    <span className="px-2 py-1 text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100 rounded uppercase">
                                        {c.estado}
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-right">
                                    <div className="flex justify-end gap-3">
                                        <button onClick={() => openEdit(c)} className="text-emerald-600 hover:text-emerald-800 font-bold text-sm">Gestionar</button>
                                        <button onClick={() => handleBloquear(c.id)} className="text-red-400 hover:text-red-600">
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* MODAL CREAR/EDITAR IGUAL A PROVEEDORES */}
            {modalOpen && (
                <div className="fixed inset-0 bg-slate-900 bg-opacity-75 flex items-center justify-center z-50 p-4 backdrop-blur-sm">
                    <div className="bg-white rounded-xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
                        <div className="bg-slate-100 p-4 border-b flex justify-between items-center">
                            <h2 className="text-lg font-bold text-slate-800">{editingId ? 'Gestionar Cliente' : 'Registro de Nuevo Cliente'}</h2>
                            <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-red-500 text-xl">&times;</button>
                        </div>

                        <div className="flex border-b bg-white">
                            <button onClick={() => setActiveTab('info')} className={`flex-1 py-3 text-sm font-bold border-b-2 transition ${activeTab === 'info' ? 'border-emerald-500 text-emerald-700 bg-emerald-50' : 'border-transparent text-slate-500 hover:bg-slate-50'}`}>
                                Datos Generales
                            </button>
                            <button disabled className="flex-1 py-3 text-sm font-bold border-b-2 border-transparent text-slate-300 cursor-not-allowed">
                                Histórico Cotizaciones
                            </button>
                        </div>

                        <div className="p-6 overflow-y-auto bg-white">
                            {activeTab === 'info' && (
                                <FormularioCliente 
                                    clienteInicial={clienteSeleccionado} 
                                    onSuccess={() => {
                                        setModalOpen(false);
                                        cargarClientes();
                                    }}
                                    onCancel={() => setModalOpen(false)}
                                />
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionClientes;