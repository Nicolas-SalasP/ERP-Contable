import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';

const ModalMapeoSII = ({ onClose }) => {
    const [mapeadas, setMapeadas] = useState([]);
    const [disponibles, setDisponibles] = useState([]);
    const [conceptos, setConceptos] = useState({});
    const [loading, setLoading] = useState(true);

    const [nuevoMapeo, setNuevoMapeo] = useState({ codigo_cuenta: '', concepto_sii: '' });

    const cargarDatos = async () => {
        setLoading(true);
        try {
            const res = await api.get('/renta/mapeo');
            if (res.success) {
                setMapeadas(res.data.mapeadas);
                setDisponibles(res.data.disponibles);
                setConceptos(res.data.conceptos);
            }
        } catch (error) {
            console.error("Error cargando mapeo:", error);
            Swal.fire('Error', 'No se pudieron cargar las cuentas', 'error');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const handleGuardar = async (e) => {
        e.preventDefault();
        try {
            const res = await api.post('/renta/mapeo', nuevoMapeo);
            if (res.success) {
                Swal.fire({ 
                    icon: 'success', 
                    title: 'Cuenta vinculada', 
                    text: 'El cálculo de impuestos se ha actualizado.',
                    timer: 1500, 
                    showConfirmButton: false 
                });
                
                setNuevoMapeo({ codigo_cuenta: '', concepto_sii: '' });
                cargarDatos(); 
            }
        } catch (error) {
            Swal.fire('Error', error.message || 'Error al guardar el mapeo', 'error');
        }
    };

    // Actualizamos la función para recibir nombre y código
    const handleEliminar = async (id, nombre, codigo) => {
        const confirm = await Swal.fire({
            title: '¿Desvincular cuenta?',
            // Usamos HTML para poner en negrita la cuenta específica
            html: `La cuenta <br/><strong>${codigo} - ${nombre}</strong><br/> dejará de sumar o restar en tu cálculo de impuestos.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, desvincular',
            cancelButtonText: 'Cancelar',
            // Desactivamos los estilos de SweetAlert para usar los de Tailwind
            buttonsStyling: false,
            customClass: {
                confirmButton: 'bg-rose-600 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-rose-700 mx-2 transition-colors',
                cancelButton: 'bg-slate-500 text-white font-bold py-2.5 px-5 rounded-lg shadow-sm hover:bg-slate-600 mx-2 transition-colors',
                popup: 'rounded-2xl'
            }
        });

        if (confirm.isConfirmed) {
            try {
                const res = await api.delete(`/renta/mapeo/${id}`);
                if (res.success) {
                    Swal.fire({ 
                        icon: 'success', 
                        title: 'Desvinculada', 
                        timer: 1500, 
                        showConfirmButton: false 
                    });
                    cargarDatos();
                }
            } catch (error) {
                Swal.fire('Error', error.message || 'Error al eliminar', 'error');
            }
        }
    };

    return (
        <div className="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col max-h-[90vh] animate-fade-in-up">
                
                <div className="flex justify-between items-center p-6 border-b border-slate-100 bg-indigo-600 text-white">
                    <div>
                        <h3 className="text-xl font-black flex items-center gap-3">
                            <i className="fas fa-project-diagram text-indigo-200"></i> Mapeo del Plan de Cuentas (SII)
                        </h3>
                        <p className="text-indigo-200 text-sm mt-1">Asigna tus cuentas contables a los conceptos tributarios del Formulario 22.</p>
                    </div>
                    <button onClick={onClose} className="text-indigo-200 hover:text-white transition-colors text-2xl">
                        <i className="fas fa-times"></i>
                    </button>
                </div>

                <div className="p-6 overflow-y-auto bg-slate-50 flex-grow">
                    
                    <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm mb-6">
                        <h4 className="font-bold text-slate-700 mb-4"><i className="fas fa-link text-indigo-500 mr-2"></i>Vincular Nueva Cuenta</h4>
                        <form onSubmit={handleGuardar} className="flex flex-col md:flex-row gap-4 items-end">
                            <div className="flex-1">
                                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">1. Cuenta de Ingreso o Gasto</label>
                                <select 
                                    required value={nuevoMapeo.codigo_cuenta} 
                                    onChange={(e) => setNuevoMapeo({...nuevoMapeo, codigo_cuenta: e.target.value})}
                                    className="w-full border border-slate-300 rounded-lg p-2.5 bg-white text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer"
                                >
                                    <option value="">Seleccione cuenta disponible...</option>
                                    {disponibles.map(c => <option key={c.codigo} value={c.codigo}>{c.codigo} - {c.nombre}</option>)}
                                </select>
                            </div>
                            <div className="flex-1">
                                <label className="block text-xs font-bold text-slate-500 uppercase mb-1">2. Asignar al Concepto SII</label>
                                <select 
                                    required value={nuevoMapeo.concepto_sii} 
                                    onChange={(e) => setNuevoMapeo({...nuevoMapeo, concepto_sii: e.target.value})}
                                    className="w-full border border-slate-300 rounded-lg p-2.5 bg-white text-slate-700 focus:ring-2 focus:ring-indigo-500 outline-none cursor-pointer"
                                >
                                    <option value="">Seleccione concepto tributario...</option>
                                    {Object.entries(conceptos).map(([key, label]) => (
                                        <option key={key} value={key}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <button type="submit" className="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg shadow-sm transition-colors whitespace-nowrap">
                                Agregar Mapeo
                            </button>
                        </form>
                    </div>

                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div className="bg-slate-50 px-5 py-3 border-b border-slate-200">
                            <h4 className="font-bold text-slate-700">Cuentas Mapeadas Actualmente</h4>
                        </div>
                        {loading ? (
                            <div className="p-8 text-center text-slate-400"><i className="fas fa-spinner fa-spin text-2xl"></i></div>
                        ) : (
                            <table className="w-full text-left text-sm">
                                <thead className="bg-white border-b border-slate-100 text-slate-500 uppercase text-xs">
                                    <tr>
                                        <th className="px-5 py-3 font-bold">Código</th>
                                        <th className="px-5 py-3 font-bold">Nombre Cuenta</th>
                                        <th className="px-5 py-3 font-bold">Concepto SII Asignado</th>
                                        <th className="px-5 py-3 font-bold text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {mapeadas.length === 0 ? (
                                        <tr><td colSpan="4" className="text-center p-6 text-slate-400">No hay cuentas vinculadas.</td></tr>
                                    ) : (
                                        mapeadas.map(m => (
                                            <tr key={m.id} className="hover:bg-slate-50 transition-colors">
                                                <td className="px-5 py-3 font-mono text-slate-600">{m.codigo_cuenta}</td>
                                                <td className="px-5 py-3 font-bold text-slate-800">{m.nombre}</td>
                                                <td className="px-5 py-3">
                                                    <span className={`px-2.5 py-1 rounded text-xs font-bold ${m.concepto_sii.includes('INGRESO') ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}`}>
                                                        {conceptos[m.concepto_sii] || m.concepto_sii}
                                                    </span>
                                                </td>
                                                <td className="px-5 py-3 text-center">
                                                    <button 
                                                        onClick={() => handleEliminar(m.id, m.nombre, m.codigo_cuenta)} 
                                                        className="px-3 py-1.5 bg-rose-50 text-rose-600 font-bold rounded hover:bg-rose-600 hover:text-white transition-colors"
                                                    >
                                                        Eliminar
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        )}
                    </div>

                </div>

                <div className="p-5 border-t border-slate-100 bg-white flex justify-end">
                    <button onClick={onClose} className="px-6 py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-lg transition-colors">
                        Cerrar y Actualizar Dashboard
                    </button>
                </div>
            </div>
        </div>
    );
};

export default ModalMapeoSII;