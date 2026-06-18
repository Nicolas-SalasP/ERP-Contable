import React, { useEffect, useState } from 'react';
import AyudaModulo from '../../Componentes/AyudaModulo';
import EstadoCarga from '../../Componentes/EstadoCarga';
import { api } from '../../Configuracion/api';
import FormularioCliente from './Componentes/FormularioCliente';
import HistorialCotizaciones from './Componentes/HistorialCotizaciones';
import Swal from 'sweetalert2';
import { logger } from '../../Configuracion/logger';
import { enmascararIdentificador } from '../../Utilidades/identificadores';
import { Search, UserPlus, Ban, Check } from 'lucide-react';

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
            logger.error("Error al cargar clientes", error);
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

    const handleToggleEstado = async (cliente) => {
        const isActivo = cliente.estado === 'ACTIVO';

        const confirm = await Swal.fire({
            title: isActivo ? '¿Bloquear Cliente?' : '¿Activar Cliente?',
            html: isActivo
                ? `¿Estás seguro de bloquear a <br/><strong class="text-slate-800 text-lg">${cliente.razon_social}</strong>?<br/>No se podrán emitir nuevos documentos a su nombre.`
                : `¿Estás seguro de reactivar a <br/><strong class="text-slate-800 text-lg">${cliente.razon_social}</strong>?<br/>Podrá volver a operar normalmente.`,
            icon: isActivo ? 'warning' : 'info',
            showCancelButton: true,
            confirmButtonText: isActivo ? 'Sí, bloquear' : 'Sí, activar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: isActivo
                    ? 'bg-red-600 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-red-700 mx-2 transition-colors'
                    : 'bg-emerald-600 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-emerald-700 mx-2 transition-colors',
                cancelButton: 'bg-slate-500 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-slate-600 mx-2 transition-colors',
                popup: 'rounded-2xl'
            }
        });

        if (confirm.isConfirmed) {
            try {
                let res;
                if (isActivo) {
                    res = await api.delete(`/clientes/${cliente.id}`);
                } else {
                    res = await api.put(`/clientes/${cliente.id}/activar`);
                }

                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: isActivo ? 'Cliente Bloqueado' : 'Cliente Activado',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    cargarClientes();
                }
            } catch (error) {
                Swal.fire('Error', error.message || 'Error al cambiar el estado del cliente', 'error');
            }
        }
    };

    return (
        <div className="max-w-6xl mx-auto p-4 md:p-6 font-sans text-gray-800 dark:text-slate-200">
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <div className="flex items-center gap-3"><h1 className="text-2xl md:text-3xl font-bold text-slate-900 dark:text-slate-100">Clientes</h1><AyudaModulo moduloId="gestionClientes" /></div>
                    <p className="text-slate-500 dark:text-slate-400 text-sm mt-1">Base de datos centralizada de clientes activos</p>
                </div>
                <button onClick={openCreate} className="w-full sm:w-auto bg-emerald-600 text-white px-5 py-2.5 rounded-lg shadow hover:bg-emerald-700 font-bold flex justify-center gap-2 items-center transition-transform active:scale-95">
                    <span className="text-lg leading-none">+</span> Nuevo Cliente
                </button>
            </div>

            <div className="mb-6">
                <div className="relative w-full">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Search size={20} strokeWidth={1.75} className="text-slate-400" />
                    </div>
                    <input
                        type="text"
                        placeholder="Filtrar por RUT, Razón Social o Código..."
                        className="w-full !pl-10 pr-4 py-3 border border-slate-300 dark:border-slate-600 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 bg-white dark:bg-slate-700 transition-all shadow-sm text-sm text-slate-700 dark:text-slate-300"
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                    />
                </div>
            </div>

            {loading || clientes.length === 0 ? (
                <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <EstadoCarga
                        cargando={loading}
                        vacio={!loading && clientes.length === 0}
                        mensajeCargando="Cargando clientes..."
                        mensajeVacio="No hay registros coincidentes."
                        iconoVacio="👥"
                        tamano="compacto"
                        color="emerald"
                    />
                </div>
            ) : (
                <>
                    <div className="grid grid-cols-1 gap-4 md:hidden">
                        {clientes.map(c => (
                            <div key={c.id} className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4 shadow-sm relative overflow-hidden">
                                <div className={`absolute top-0 left-0 w-1.5 h-full ${c.estado === 'ACTIVO' ? 'bg-emerald-500' : 'bg-red-400'}`}></div>

                                <div className="flex justify-between items-start mb-2 pl-2">
                                    <div>
                                        <div className="text-xs font-bold text-emerald-600 font-mono mb-0.5">{c.codigo_cliente}</div>
                                        <h3 className="font-bold text-slate-800 dark:text-slate-200 leading-tight">{c.razon_social}</h3>
                                    </div>
                                    <span className={`inline-block px-3 py-1 text-xs font-bold rounded-full uppercase border ${c.estado === 'ACTIVO' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-600 border-red-200'}`}>
                                        {c.estado || 'SIN ESTADO'}
                                    </span>
                                </div>

                                <div className="pl-2 space-y-1.5 mb-4">
                                    <div className="text-sm font-mono text-slate-600 dark:text-slate-400 flex items-center gap-2">
                                        <span className="font-bold text-xs text-slate-400 w-4">RUT</span> {enmascararIdentificador(c.rut)}
                                    </div>
                                    <div className="text-sm text-slate-700 dark:text-slate-300 flex items-center gap-2">
                                        <span className="font-bold text-xs text-slate-400 w-4">CTO</span>
                                        <span>{c.contacto_nombre || 'Sin contacto'}</span>
                                    </div>
                                </div>

                                <div className="flex gap-2 pt-3 border-t border-slate-100 dark:border-slate-700 pl-2">
                                    <button onClick={() => openEdit(c)} className="flex-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-sm py-2 rounded-lg transition-colors border border-emerald-100">
                                        Gestionar
                                    </button>
                                    <button
                                        onClick={() => handleToggleEstado(c)}
                                        className={`px-4 rounded-lg transition-colors border flex items-center justify-center ${c.estado === 'ACTIVO' ? 'bg-red-50 text-red-600 hover:bg-red-100 hover:text-red-700 border-red-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700 border-emerald-100'}`}
                                        title={c.estado === 'ACTIVO' ? 'Bloquear' : 'Activar'}
                                        aria-label={c.estado === 'ACTIVO' ? 'Bloquear cliente' : 'Activar cliente'}
                                    >
                                        {c.estado === 'ACTIVO' ? (
                                            <Ban size={20} strokeWidth={1.75} />
                                        ) : (
                                            <Check size={20} strokeWidth={1.75} />
                                        )}
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="hidden md:block bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        <div className="overflow-x-auto custom-scrollbar">
                        <table className="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                            <thead className="bg-slate-50 dark:bg-slate-900">
                                <tr>
                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Código / Identificador</th>
                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Razón Social / Empresa</th>
                                    <th className="px-6 py-4 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Contacto</th>
                                    <th className="px-6 py-4 text-center text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Estado</th>
                                    <th className="px-6 py-4 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                                {clientes.map(c => (
                                    <tr key={c.id} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-xs font-bold text-emerald-600 font-mono mb-1">{c.codigo_cliente}</div>
                                            <div className="text-sm font-mono text-slate-600 dark:text-slate-400">{enmascararIdentificador(c.rut)}</div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="font-bold text-slate-800 dark:text-slate-200">{c.razon_social}</div>
                                            <div className="text-xs text-slate-400 truncate max-w-xs" title={c.direccion}>{c.direccion || '---'}</div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="text-sm text-slate-700 dark:text-slate-300">{c.contacto_nombre || '---'}</div>
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
                                                <button
                                                    onClick={() => handleToggleEstado(c)}
                                                    className={`p-2 rounded transition-colors border ${c.estado === 'ACTIVO' ? 'text-red-500 hover:text-red-700 bg-red-50 border-red-100 hover:bg-red-100' : 'text-emerald-500 hover:text-emerald-700 bg-emerald-50 border-emerald-100 hover:bg-emerald-100'}`}
                                                    title={c.estado === 'ACTIVO' ? 'Bloquear Cliente' : 'Activar Cliente'}
                                                    aria-label={c.estado === 'ACTIVO' ? 'Bloquear cliente' : 'Activar cliente'}
                                                >
                                                    {c.estado === 'ACTIVO' ? (
                                                        <Ban size={20} strokeWidth={1.75} />
                                                    ) : (
                                                        <Check size={20} strokeWidth={1.75} />
                                                    )}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        </div>
                    </div>
                </>
            )}

            {modalOpen && (
                <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 md:p-6 animate-fade-in">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden flex flex-col max-h-[95vh] md:max-h-[90vh] animate-slide-up">
                        <div className="bg-emerald-600 p-4 md:p-5 flex justify-between items-center text-white shrink-0">
                            <h2 className="text-lg md:text-xl font-bold flex items-center gap-2">
                                <UserPlus size={20} strokeWidth={1.75} />
                                {editingId ? 'Gestionar Cliente' : 'Registro de Nuevo Cliente'}
                            </h2>
                            <button onClick={() => setModalOpen(false)} className="text-emerald-200 hover:text-white transition-colors text-3xl leading-none" aria-label="Cerrar">&times;</button>
                        </div>
                        <div className="flex overflow-x-auto border-b bg-slate-50 dark:bg-slate-900 hide-scrollbar shrink-0">
                            <button onClick={() => setActiveTab('info')} className={`flex-1 py-3 px-4 text-sm font-bold border-b-2 transition whitespace-nowrap ${activeTab === 'info' ? 'border-emerald-600 text-emerald-700 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800'}`}>
                                <i className="fas fa-info-circle mr-2"></i>Datos Generales
                            </button>
                            <button onClick={() => setActiveTab('historial')} className={`flex-1 py-3 px-4 text-sm font-bold border-b-2 transition whitespace-nowrap ${activeTab === 'historial' ? 'border-emerald-600 text-emerald-700 bg-white dark:bg-slate-800' : 'border-transparent text-slate-500 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800'}`}>
                                <i className="fas fa-history mr-2"></i>Histórico Cotizaciones
                            </button>
                        </div>
                        <div className="p-4 md:p-6 overflow-y-auto bg-white dark:bg-slate-800 flex-grow custom-scrollbar">

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
