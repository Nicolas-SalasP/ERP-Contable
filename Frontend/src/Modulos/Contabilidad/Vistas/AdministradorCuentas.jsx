import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api'; 
import Swal from 'sweetalert2';

const AdministradorCuentas = () => {
    const [cuentas, setCuentas] = useState([]);
    const [loading, setLoading] = useState(true);
    const [busqueda, setBusqueda] = useState('');
    const [filtroTipo, setFiltroTipo] = useState('');
    const [modalOpen, setModalOpen] = useState(false);
    const [cuentaEditando, setCuentaEditando] = useState(null);
    const [formEdit, setFormEdit] = useState({ nombre: '', imputable: 1, activo: 1 });

    const cargarCuentas = async () => {
        setLoading(true);
        try {
            const res = await api.get('/contabilidad/plan-cuentas');
            if (res.success || Array.isArray(res.data)) {
                setCuentas(res.data || res); 
            }
        } catch (error) {
            console.error(error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo cargar el plan de cuentas',
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' },
                buttonsStyling: false
            });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => { cargarCuentas(); }, []);

    const abrirEdicion = (cuenta) => {
        setCuentaEditando(cuenta);
        setFormEdit({
            nombre: cuenta.nombre,
            imputable: cuenta.imputable ? 1 : 0,
            activo: cuenta.activo !== undefined ? (cuenta.activo ? 1 : 0) : 1
        });
        setModalOpen(true);
    };

    const guardarCambios = async () => {
        if (!formEdit.nombre.trim()) {
            return Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'El nombre de la cuenta no puede estar vacío',
                customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg hover:bg-amber-600' },
                buttonsStyling: false
            });
        }

        try {
            const res = await api.put(`/contabilidad/plan-cuentas/${cuentaEditando.id}`, formEdit);
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: 'La cuenta se actualizó correctamente.',
                    timer: 1500,
                    showConfirmButton: false,
                    customClass: { popup: 'rounded-2xl' }
                });
                setModalOpen(false);
                cargarCuentas(); 
            }
        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Hubo un problema al guardar los cambios.',
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' },
                buttonsStyling: false
            });
        }
    };

    const cuentasFiltradas = cuentas.filter(c => {
        const coincideBusqueda = c.codigo.includes(busqueda) || c.nombre.toLowerCase().includes(busqueda.toLowerCase());
        const coincideTipo = filtroTipo === '' || c.tipo === filtroTipo;
        return coincideBusqueda && coincideTipo;
    });

    const getTipoColor = (tipo) => {
        switch (tipo) {
            case 'ACTIVO': return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'PASIVO': return 'bg-red-100 text-red-800 border-red-200';
            case 'PATRIMONIO': return 'bg-purple-100 text-purple-800 border-purple-200';
            case 'INGRESO': return 'bg-emerald-100 text-emerald-800 border-emerald-200';
            case 'GASTO': return 'bg-orange-100 text-orange-800 border-orange-200';
            default: return 'bg-slate-100 text-slate-800 border-slate-200';
        }
    };

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 font-sans pb-10">
            <div className="mb-8">
                <h2 className="text-2xl md:text-3xl font-bold text-slate-900">Configuración: Plan de Cuentas</h2>
                <p className="text-slate-500 text-sm mt-1">Administra la visibilidad, nombres y comportamiento de tus cuentas contables.</p>
            </div>

            <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200 mb-6 flex flex-col md:flex-row gap-4">
                <div className="flex-1 relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg className="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <input 
                        type="text" 
                        className="w-full !pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700 text-sm"
                        placeholder="Buscar por código o nombre..."
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                    />
                </div>
                <div className="w-full md:w-64">
                    <select 
                        className="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-medium text-slate-700 text-sm cursor-pointer"
                        value={filtroTipo}
                        onChange={(e) => setFiltroTipo(e.target.value)}
                    >
                        <option value="">Todos los tipos</option>
                        <option value="ACTIVO">Activos</option>
                        <option value="PASIVO">Pasivos</option>
                        <option value="PATRIMONIO">Patrimonio</option>
                        <option value="INGRESO">Ingresos</option>
                        <option value="GASTO">Gastos</option>
                    </select>
                </div>
            </div>

            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                {loading ? (
                    <div className="p-10 text-center text-slate-400">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-3"></div>
                        <p className="font-medium">Cargando plan maestro...</p>
                    </div>
                ) : cuentasFiltradas.length === 0 ? (
                    <div className="p-10 text-center text-slate-400">
                        <svg className="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        <p className="font-medium">No se encontraron cuentas contables.</p>
                    </div>
                ) : (
                    <>
                        <div className="grid grid-cols-1 gap-4 p-4 md:hidden bg-slate-50">
                            {cuentasFiltradas.map((cuenta) => {
                                const isActiva = cuenta.activo === undefined || cuenta.activo == 1;
                                return (
                                    <div key={cuenta.id} className={`bg-white rounded-xl border border-slate-200 p-4 shadow-sm relative ${!isActiva ? 'opacity-70' : ''}`}>
                                        <div className={`absolute top-0 left-0 w-1.5 h-full rounded-l-xl ${isActiva ? 'bg-blue-500' : 'bg-slate-300'}`}></div>
                                        
                                        <div className="flex justify-between items-start mb-2 pl-2">
                                            <div>
                                                <div className="text-xs font-bold text-slate-500 font-mono mb-0.5">{cuenta.codigo}</div>
                                                <h3 className={`font-bold leading-tight ${isActiva ? 'text-slate-800' : 'text-slate-400 line-through'}`}>{cuenta.nombre}</h3>
                                            </div>
                                        </div>

                                        <div className="flex flex-wrap gap-2 pl-2 mt-3 mb-4">
                                            <span className={`px-2.5 py-0.5 text-[10px] font-bold rounded uppercase border ${getTipoColor(cuenta.tipo)}`}>
                                                {cuenta.tipo}
                                            </span>
                                            <span className={`px-2.5 py-0.5 text-[10px] font-bold rounded uppercase border ${cuenta.imputable == 1 ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : 'bg-slate-100 text-slate-500 border-slate-200'}`}>
                                                {cuenta.imputable == 1 ? 'Imputable' : 'Agrupadora'}
                                            </span>
                                            <span className={`px-2.5 py-0.5 text-[10px] font-bold rounded uppercase border ${isActiva ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200'}`}>
                                                {isActiva ? 'Activa' : 'Inactiva'}
                                            </span>
                                        </div>

                                        <div className="pt-3 border-t border-slate-100 pl-2">
                                            <button onClick={() => abrirEdicion(cuenta)} className="w-full bg-slate-50 text-slate-700 hover:bg-blue-50 hover:text-blue-700 hover:border-blue-200 font-bold text-sm py-2 rounded-lg transition-colors border border-slate-200 flex items-center justify-center gap-2">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                Configurar
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        <div className="hidden md:block overflow-x-auto w-full custom-scrollbar">
                            <table className="min-w-full text-left border-collapse">
                                <thead className="bg-slate-900 text-white text-xs uppercase tracking-wider">
                                    <tr>
                                        <th className="px-6 py-4 font-bold">Código</th>
                                        <th className="px-6 py-4 font-bold">Nombre de la Cuenta</th>
                                        <th className="px-6 py-4 font-bold">Clasificación</th>
                                        <th className="px-6 py-4 font-bold text-center">Imputable</th>
                                        <th className="px-6 py-4 font-bold text-center">Estado</th>
                                        <th className="px-6 py-4 font-bold text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 text-sm">
                                    {cuentasFiltradas.map((cuenta) => {
                                        const isActiva = cuenta.activo === undefined || cuenta.activo == 1;
                                        return (
                                            <tr key={cuenta.id} className={`hover:bg-slate-50 transition-colors group ${!isActiva ? 'opacity-60 bg-slate-50' : 'bg-white'}`}>
                                                <td className="px-6 py-4 font-mono font-bold text-slate-600 whitespace-nowrap">{cuenta.codigo}</td>
                                                <td className={`px-6 py-4 font-bold ${isActiva ? 'text-slate-800' : 'text-slate-500 line-through decoration-slate-300'}`}>
                                                    {cuenta.nombre}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`px-2.5 py-1 rounded text-[10px] font-bold border uppercase ${getTipoColor(cuenta.tipo)}`}>
                                                        {cuenta.tipo}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 text-center whitespace-nowrap">
                                                    {cuenta.imputable == 1 ? 
                                                        <span className="bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded text-[10px] font-bold uppercase" title="Recibe asientos contables">SÍ</span> : 
                                                        <span className="bg-slate-100 text-slate-500 border border-slate-200 px-2 py-0.5 rounded text-[10px] font-bold uppercase" title="Solo agrupa saldos">NO</span>
                                                    }
                                                </td>
                                                <td className="px-6 py-4 text-center whitespace-nowrap">
                                                    {isActiva ? 
                                                        <span className="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">Activa</span> : 
                                                        <span className="bg-slate-200 text-slate-600 border border-slate-300 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">Inactiva</span>
                                                    }
                                                </td>
                                                <td className="px-6 py-4 text-right whitespace-nowrap">
                                                    <button 
                                                        onClick={() => abrirEdicion(cuenta)}
                                                        className="flex items-center gap-1.5 ml-auto bg-slate-50 border border-slate-200 hover:bg-blue-50 text-slate-600 hover:text-blue-700 hover:border-blue-200 px-3 py-1.5 rounded-lg transition-colors font-bold text-xs opacity-100 md:opacity-0 group-hover:opacity-100"
                                                        title="Configurar Cuenta"
                                                    >
                                                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                        Configurar
                                                    </button>
                                                </td>
                                            </tr>
                                        )
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>

            {modalOpen && (
                <div className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200 animate-slide-up">
                        <div className="bg-slate-900 px-6 py-5 flex justify-between items-center text-white">
                            <h2 className="text-lg md:text-xl font-bold flex items-center gap-2">
                                <svg className="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                Propiedades de Cuenta
                            </h2>
                            <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-white transition-colors">
                                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        
                        <div className="p-6 space-y-5 bg-white">
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Código Contable (No editable)</label>
                                <input type="text" value={cuentaEditando?.codigo} disabled className="w-full bg-slate-100 border border-slate-200 rounded-lg p-3 text-slate-500 font-mono font-bold cursor-not-allowed outline-none" />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">Nombre de la Cuenta</label>
                                <input 
                                    type="text" 
                                    value={formEdit.nombre} 
                                    onChange={e => setFormEdit({...formEdit, nombre: e.target.value})}
                                    className="w-full border border-slate-300 rounded-lg p-3 font-bold text-slate-800 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition-all" 
                                />
                            </div>

                            <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between">
                                <div>
                                    <p className="font-bold text-slate-800 text-sm">Cuenta Imputable</p>
                                    <p className="text-xs text-slate-500 mt-0.5">Permite recibir asientos contables.</p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" className="sr-only peer" checked={formEdit.imputable === 1} onChange={() => setFormEdit({...formEdit, imputable: formEdit.imputable === 1 ? 0 : 1})} />
                                    <div className="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            <div className="p-4 bg-emerald-50/50 rounded-xl border border-emerald-100 flex items-center justify-between">
                                <div>
                                    <p className="font-bold text-emerald-900 text-sm">Estado de la Cuenta</p>
                                    <p className="text-xs text-emerald-700 mt-0.5">Actívala o escóndela del sistema.</p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" className="sr-only peer" checked={formEdit.activo === 1} onChange={() => setFormEdit({...formEdit, activo: formEdit.activo === 1 ? 0 : 1})} />
                                    <div className="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                </label>
                            </div>
                        </div>

                        <div className="bg-slate-50 px-6 py-4 border-t border-slate-200 flex flex-col sm:flex-row justify-end gap-3">
                            <button onClick={() => setModalOpen(false)} className="w-full sm:w-auto px-5 py-2.5 text-slate-600 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-sm font-bold transition-all text-center">
                                Cancelar
                            </button>
                            <button onClick={guardarCambios} className="w-full sm:w-auto px-8 py-2.5 bg-slate-900 text-white rounded-lg text-sm font-bold shadow-lg shadow-slate-900/20 hover:bg-slate-800 transition-all flex items-center justify-center gap-2">
                                <svg className="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" d="M5 13l4 4L19 7"></path></svg>
                                Guardar Cambios
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default AdministradorCuentas;