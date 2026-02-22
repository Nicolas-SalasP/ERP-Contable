import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api'; // Ajusta la ruta a tu api.js
import Swal from 'sweetalert2';

const AdministradorCuentas = () => {
    const [cuentas, setCuentas] = useState([]);
    const [loading, setLoading] = useState(true);
    const [busqueda, setBusqueda] = useState('');
    const [filtroTipo, setFiltroTipo] = useState('');
    
    // Estados para el Modal de Edición
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
            Swal.fire('Error', 'No se pudo cargar el plan de cuentas', 'error');
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
            activo: cuenta.activo !== undefined ? (cuenta.activo ? 1 : 0) : 1 // Por si la BD aún no refresca
        });
        setModalOpen(true);
    };

    const guardarCambios = async () => {
        if (!formEdit.nombre.trim()) return Swal.fire('Atención', 'El nombre no puede estar vacío', 'warning');

        try {
            const res = await api.put(`/contabilidad/plan-cuentas/${cuentaEditando.id}`, formEdit);
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Guardado!',
                    text: 'La cuenta se actualizó correctamente.',
                    customClass: { confirmButton: 'bg-emerald-600 text-white font-bold py-2 px-6 rounded-lg' },
                    buttonsStyling: false
                });
                setModalOpen(false);
                cargarCuentas(); // Refrescamos la tabla
            }
        } catch (error) {
            Swal.fire('Error', 'Hubo un problema al guardar los cambios.', 'error');
        }
    };

    // Filtrado inteligente
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
        <div className="max-w-7xl mx-auto p-4 md:p-6 font-sans">
            <div className="mb-8">
                <h2 className="text-2xl md:text-3xl font-bold text-slate-900">Configuración: Plan de Cuentas</h2>
                <p className="text-slate-500 text-sm mt-1">Administra la visibilidad, nombres y comportamiento de tus cuentas contables.</p>
            </div>

            {/* BARRA DE HERRAMIENTAS / FILTROS */}
            <div className="bg-white p-4 rounded-xl shadow-sm border border-slate-200 mb-6 flex flex-col md:flex-row gap-4">
                <div className="flex-1 relative">
                    <i className="fas fa-search absolute left-4 top-3.5 text-slate-400"></i>
                    <input 
                        type="text" 
                        className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white outline-none font-medium text-slate-700"
                        placeholder="Buscar por código o nombre..."
                        value={busqueda}
                        onChange={(e) => setBusqueda(e.target.value)}
                    />
                </div>
                <div className="w-full md:w-64">
                    <select 
                        className="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg outline-none font-medium text-slate-700"
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

            {/* TABLA DE CUENTAS */}
            <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div className="overflow-x-auto w-full">
                    <table className="min-w-full text-left border-collapse">
                        <thead className="bg-slate-900 text-white text-xs uppercase tracking-wider">
                            <tr>
                                <th className="p-4 font-bold">Código</th>
                                <th className="p-4 font-bold">Nombre de la Cuenta</th>
                                <th className="p-4 font-bold">Clasificación</th>
                                <th className="p-4 font-bold text-center">Imputable</th>
                                <th className="p-4 font-bold text-center">Estado</th>
                                <th className="p-4 font-bold text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 text-sm">
                            {loading ? (
                                <tr><td colSpan="6" className="p-10 text-center text-slate-400"><i className="fas fa-spinner fa-spin mr-2"></i> Cargando plan maestro...</td></tr>
                            ) : cuentasFiltradas.length === 0 ? (
                                <tr><td colSpan="6" className="p-10 text-center text-slate-400">No se encontraron cuentas contables.</td></tr>
                            ) : (
                                cuentasFiltradas.map((cuenta) => {
                                    const isActiva = cuenta.activo === undefined || cuenta.activo == 1; // Manejo seguro si la columna es nueva
                                    return (
                                        <tr key={cuenta.id} className={`hover:bg-slate-50 transition-colors ${!isActiva ? 'opacity-60 bg-slate-50' : ''}`}>
                                            <td className="p-4 font-mono font-bold text-slate-700">{cuenta.codigo}</td>
                                            <td className={`p-4 font-bold ${isActiva ? 'text-slate-900' : 'text-slate-500 line-through decoration-slate-300'}`}>
                                                {cuenta.nombre}
                                            </td>
                                            <td className="p-4">
                                                <span className={`px-2 py-1 rounded text-[10px] font-bold border ${getTipoColor(cuenta.tipo)}`}>
                                                    {cuenta.tipo}
                                                </span>
                                            </td>
                                            <td className="p-4 text-center">
                                                {cuenta.imputable == 1 ? 
                                                    <span className="text-emerald-500" title="Recibe movimientos"><i className="fas fa-check-circle"></i></span> : 
                                                    <span className="text-slate-300" title="Cuenta Agrupadora (No recibe movimientos)"><i className="fas fa-minus-circle"></i></span>
                                                }
                                            </td>
                                            <td className="p-4 text-center">
                                                {isActiva ? 
                                                    <span className="bg-emerald-100 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full text-[10px] font-bold">ACTIVA</span> : 
                                                    <span className="bg-slate-200 text-slate-600 border border-slate-300 px-2 py-0.5 rounded-full text-[10px] font-bold">INACTIVA</span>
                                                }
                                            </td>
                                            <td className="p-4 text-right">
                                                <button 
                                                    onClick={() => abrirEdicion(cuenta)}
                                                    className="bg-slate-100 hover:bg-blue-100 text-slate-600 hover:text-blue-700 p-2 rounded transition-colors"
                                                    title="Configurar Cuenta"
                                                >
                                                    <i className="fas fa-cog"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    )
                                })
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* MODAL DE EDICIÓN */}
            {modalOpen && (
                <div className="fixed inset-0 bg-slate-900/80 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fade-in">
                    <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden border border-slate-200">
                        <div className="bg-slate-900 px-6 py-4 flex justify-between items-center text-white">
                            <h2 className="text-lg font-bold">Propiedades de la Cuenta</h2>
                            <button onClick={() => setModalOpen(false)} className="text-slate-400 hover:text-white transition-colors">
                                <i className="fas fa-times text-xl"></i>
                            </button>
                        </div>
                        
                        <div className="p-6 space-y-5">
                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Código Contable (No editable)</label>
                                <input type="text" value={cuentaEditando?.codigo} disabled className="w-full bg-slate-100 border border-slate-200 rounded-lg p-3 text-slate-500 font-mono font-bold cursor-not-allowed" />
                            </div>

                            <div>
                                <label className="block text-xs font-bold text-slate-500 uppercase mb-2">Nombre de la Cuenta</label>
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
                                    <p className="text-xs text-slate-500">Permite recibir asientos contables.</p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" className="sr-only peer" checked={formEdit.imputable === 1} onChange={() => setFormEdit({...formEdit, imputable: formEdit.imputable === 1 ? 0 : 1})} />
                                    <div className="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>

                            <div className="p-4 bg-emerald-50 rounded-xl border border-emerald-200 flex items-center justify-between">
                                <div>
                                    <p className="font-bold text-emerald-900 text-sm">Estado de la Cuenta</p>
                                    <p className="text-xs text-emerald-700">Actívala o escóndela del sistema.</p>
                                </div>
                                <label className="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" className="sr-only peer" checked={formEdit.activo === 1} onChange={() => setFormEdit({...formEdit, activo: formEdit.activo === 1 ? 0 : 1})} />
                                    <div className="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                </label>
                            </div>
                        </div>

                        <div className="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-end gap-3">
                            <button onClick={() => setModalOpen(false)} className="px-5 py-2.5 text-slate-600 bg-white border border-slate-300 hover:bg-slate-100 rounded-lg text-sm font-bold transition-all">
                                Cancelar
                            </button>
                            <button onClick={guardarCambios} className="px-6 py-2.5 bg-slate-900 text-white rounded-lg text-sm font-bold shadow hover:bg-slate-800 transition-all">
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