import React, { useEffect, useState } from 'react';
import { api } from '../../Configuracion/api';
import FormularioCliente from './Componentes/FormularioCliente';
import HistorialCotizaciones from './Componentes/HistorialCotizaciones';
import Swal from 'sweetalert2';

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
            Swal.fire('Error', 'No se pudieron cargar los clientes', 'error');
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

    const handleBloquear = async (id, nombre) => {
        const confirm = await Swal.fire({
            title: '¿Bloquear Cliente?',
            html: `¿Estás seguro de bloquear a <br/><strong class="text-slate-800 text-lg">${nombre}</strong>?<br/>No se podrán emitir nuevos documentos a su nombre.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, bloquear',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'bg-red-600 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-red-700 mx-2 transition-colors',
                cancelButton: 'bg-slate-500 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-slate-600 mx-2 transition-colors',
                popup: 'rounded-2xl'
            }
        });

        if (confirm.isConfirmed) {
            try {
                const res = await api.delete(`/clientes/${id}`);
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Cliente Bloqueado',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    cargarClientes();
                }
            } catch (error) {
                Swal.fire('Error', error.message || 'Error al bloquear el cliente', 'error');
            }
        }
    };

    return (
        <div className="max-w-6xl mx-auto p-4 md:p-6 font-sans text-gray-800">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 className="text-2xl md:text-3xl font-bold text-slate-900">Clientes</h1>
                    <p className="text-slate-500 text-sm mt-1">Base de datos centralizada de clientes activos</p>
                </div>
                <button onClick={openCreate} className="w-full sm:w-auto bg-emerald-600 text-white px-5 py-2.5 rounded-lg shadow hover:bg-emerald-700 font-bold flex justify-center gap-2 items-center transition-transform active:scale-95">
                    <span className="text-lg leading-none">+</span> Nuevo Cliente
                </button>
            </div>

            <div className="mb-6">
                <div className="relative w-full">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input 
                        type="text" 
                        placeholder="Filtrar por RUT, Razón Social o Código..." 
                        className="w-full !pl-10 pr-4 py-3 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 bg-white transition-all shadow-sm text-sm text-slate-700"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                    />
                </div>
            </div>

            {loading ? (
                <div className="p-10 text-center text-slate-400 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500 mx-auto mb-3"></div>
                    <p className="font-medium">Cargando clientes...</p>
                </div>
            ) : clientes.length === 0 ? (
                <div className="p-10 text-center text-slate-400 bg-white rounded-xl border border-slate-200 shadow-sm">
                    <svg className="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <p className="font-medium">No hay registros coincidentes.</p>
                </div>
            ) : (
                <>
                    <div className="grid grid-cols-1 gap-4 md:hidden">
                        {clientes.map(c => (
                            <div key={c.id} className="bg-white rounded-xl border border-slate-200 p-4 shadow-sm relative overflow-hidden">
                                <div className={`absolute top-0 left-0 w-1.5 h-full ${c.estado === 'ACTIVO' ? 'bg-emerald-500' : 'bg-red-400'}`}></div>
                                
                                <div className="flex justify-between items-start mb-2 pl-2">
                                    <div>
                                        <div className="text-xs font-bold text-emerald-600 font-mono mb-0.5">{c.codigo_cliente}</div>
                                        <h3 className="font-bold text-slate-800 leading-tight">{c.razon_social}</h3>
                                    </div>
                                    <span className={`inline-block px-3 py-1 text-xs font-bold rounded-full uppercase border ${c.estado === 'ACTIVO' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200'}`}>
                                        {c.estado || 'SIN ESTADO'}
                                    </span>
                                </div>
                                
                                <div className="pl-2 space-y-1.5 mb-4">
                                    <div className="text-sm font-mono text-slate-600 flex items-center gap-2">
                                        <span className="font-bold text-xs text-slate-400 w-4">RUT</span> {c.rut}
                                    </div>
                                    <div className="text-sm text-slate-700 flex items-center gap-2">
                                        <span className="font-bold text-xs text-slate-400 w-4">CTO</span> 
                                        <span>{c.contacto_nombre || 'Sin contacto'}</span>
                                    </div>
                                </div>

                                <div className="flex gap-2 pt-3 border-t border-slate-100 pl-2">
                                    <button onClick={() => openEdit(c)} className="flex-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-sm py-2 rounded-lg transition-colors border border-emerald-100">
                                        Gestionar
                                    </button>
                                    <button onClick={() => handleBloquear(c.id, c.razon_social)} className="px-4 bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 rounded-lg transition-colors border border-red-100 flex items-center justify-center" title="Bloquear">
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="hidden md:block bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Código / Identificador</th>
                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Razón Social / Empresa</th>
                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Contacto</th>
                                    <th className="px-6 py-4 text-center text-xs font-bold text-slate-500 uppercase tracking-wider">Estado</th>
                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {clientes.map(c => (
                                    <tr key={c.id} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-xs font-bold text-emerald-600 font-mono mb-1">{c.codigo_cliente}</div>
                                            <div className="text-sm font-mono text-slate-600">{c.rut}</div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="font-bold text-slate-800">{c.razon_social}</div>
                                            <div className="text-xs text-slate-400 truncate max-w-xs" title={c.direccion}>{c.direccion || '---'}</div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="text-sm text-slate-700">{c.contacto_nombre || '---'}</div>
                                            <div className="text-xs text-slate-400">{c.contacto_email}</div>
                                        </td>
                                        <td className="px-6 py-4 text-center whitespace-nowrap">
                                            <span className={`inline-block px-3 py-1 text-xs font-bold rounded-full uppercase border ${c.estado === 'ACTIVO' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200'}`}>
                                                {c.estado || 'SIN ESTADO'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 text-right whitespace-nowrap">
                                            <div className="flex justify-end gap-2 items-center">
                                                <button onClick={() => openEdit(c)} className="text-emerald-700 hover:text-emerald-900 font-bold text-sm bg-emerald-50 border border-emerald-100 hover:bg-emerald-100 px-3 py-1.5 rounded transition-colors">
                                                    Gestionar
                                                </button>
                                                <button onClick={() => handleBloquear(c.id, c.razon_social)} className="text-red-500 hover:text-red-700 p-1.5 bg-red-50 border border-red-100 hover:bg-red-100 rounded transition-colors" title="Bloquear Cliente">
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}

            {modalOpen && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 md:p-6 animate-fade-in">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[95vh] md:max-h-[90vh] animate-slide-up">
                        <div className="bg-emerald-600 p-4 md:p-5 flex justify-between items-center text-white shrink-0">
                            <h2 className="text-lg md:text-xl font-bold flex items-center gap-2">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                                {editingId ? 'Gestionar Cliente' : 'Registro de Nuevo Cliente'}
                            </h2>
                            <button onClick={() => setModalOpen(false)} className="text-emerald-200 hover:text-white transition-colors text-3xl leading-none">&times;</button>
                        </div>
                        <div className="flex overflow-x-auto border-b bg-slate-50 hide-scrollbar shrink-0">
                            <button onClick={() => setActiveTab('info')} className={`flex-1 py-3 px-4 text-sm font-bold border-b-2 transition whitespace-nowrap ${activeTab === 'info' ? 'border-emerald-600 text-emerald-700 bg-white' : 'border-transparent text-slate-500 hover:bg-white'}`}>
                                <i className="fas fa-info-circle mr-2"></i>Datos Generales
                            </button>
                            <button onClick={() => setActiveTab('historial')} className={`flex-1 py-3 px-4 text-sm font-bold border-b-2 transition whitespace-nowrap ${activeTab === 'historial' ? 'border-emerald-600 text-emerald-700 bg-white' : 'border-transparent text-slate-500 hover:bg-white'}`}>
                                <i className="fas fa-history mr-2"></i>Histórico Cotizaciones
                            </button>
                        </div>
                        <div className="p-4 md:p-6 overflow-y-auto bg-white flex-grow custom-scrollbar">
                            
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
                            
                            {activeTab === 'historial' && (
                                <HistorialCotizaciones clienteId={editingId} />
                            )}
                            
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionClientes;