import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';
import BuscadorCuentaContable from '../Componentes/BuscadorCuentaContable';

const ReclasificadorAsiento = () => {
    const { id } = useParams();
    const navigate = useNavigate();

    const [loading, setLoading] = useState(true);
    const [data, setData] = useState(null);
    const [cuentasPlan, setCuentasPlan] = useState([]);
    
    const [form, setForm] = useState({
        fecha_ajuste: new Date().toISOString().split('T')[0],
        glosa_auditoria: '',
        lineas_editadas: {}
    });

    useEffect(() => {
        const cargarDatos = async () => {
            try {
                const [resAsiento, resCuentas] = await Promise.all([
                    api.get(`/facturas/${id}/asiento`),
                    api.get('/contabilidad/plan-cuentas')
                ]);

                if (resAsiento.success) setData(resAsiento.data);
                if (resCuentas.success) {
                    setCuentasPlan(resCuentas.data.filter(c => c.imputable));
                }
                
                setForm(prev => ({
                    ...prev,
                    glosa_auditoria: `Ajuste Imputación Fac. N° ${resAsiento.data.cabecera.numero_comprobante || id}`
                }));
            } catch (error) {
                Swal.fire('Error', 'No se pudo cargar la información para reclasificar.', 'error');
                navigate(-1);
            } finally {
                setLoading(false);
            }
        };
        cargarDatos();
    }, [id]);

    const ejecutarReclasificacion = async () => {
        if (Object.keys(form.lineas_editadas).length === 0) {
            return Swal.fire('Sin cambios', 'No has modificado ninguna cuenta.', 'info');
        }

        const result = await Swal.fire({
            title: '¿Confirmar Reclasificación?',
            text: "Se generará un registro de auditoría y se modificará la imputación del asiento.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplicar cambios',
            customClass: {
                confirmButton: 'bg-emerald-600 text-white px-6 py-2.5 rounded-lg font-bold',
                cancelButton: 'bg-slate-100 text-slate-700 px-6 py-2.5 rounded-lg font-bold ml-3'
            },
            buttonsStyling: false
        });

        if (result.isConfirmed) {
            try {
                const payload = {
                    fecha: form.fecha_ajuste,
                    glosa: form.glosa_auditoria,
                    cambios: form.lineas_editadas
                };

                const res = await api.post(`/facturas/${id}/reclasificar`, payload);
                if (res.success) {
                    await Swal.fire('¡Éxito!', 'El asiento ha sido reclasificado correctamente.', 'success');
                    navigate(`/contabilidad/factura/${id}/asiento`);
                }
            } catch (error) {
                Swal.fire('Error', error.response?.data?.message || 'Error al procesar el ajuste.', 'error');
            }
        }
    };

    if (loading) return <div className="p-10 text-center animate-pulse">Preparando Reclasificación...</div>;
    if (!data) return null;

    const { cabecera, detalles } = data;

    // --- LÓGICA DE DETECCIÓN DE REVERSOS (PARES ESPEJO) ---
    const indicesAnulados = new Set();
    
    detalles.forEach((det, i) => {
        if (indicesAnulados.has(i)) return; // Si ya lo emparejamos, lo saltamos
        
        // Buscamos su línea "espejo" (misma cuenta, montos invertidos)
        const indexReverso = detalles.findIndex((otro, j) => 
            j !== i &&
            !indicesAnulados.has(j) &&
            det.cuenta_contable === otro.cuenta_contable &&
            Number(det.debe) === Number(otro.haber) &&
            Number(det.haber) === Number(otro.debe) &&
            (Number(det.debe) > 0 || Number(det.haber) > 0)
        );

        // Si encontramos el espejo, ambas líneas quedan bloqueadas por ser historial
        if (indexReverso !== -1) {
            indicesAnulados.add(i);
            indicesAnulados.add(indexReverso);
        }
    });

    return (
        <div className="p-6 bg-slate-50 min-h-screen">
            <div className="max-w-6xl mx-auto">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h1 className="text-2xl font-black text-slate-900">Panel de Reclasificaciones</h1>
                        <p className="text-slate-500">Asiento #{cabecera.numero_comprobante} | Factura Origen ID {id}</p>
                    </div>
                    <div className="flex gap-3">
                        <button onClick={() => navigate("/facturas/historial")} className="px-5 py-2.5 bg-white border border-slate-300 rounded-xl font-bold text-slate-600 hover:bg-slate-50 transition-all">
                            Cancelar
                        </button>
                        <button onClick={ejecutarReclasificacion} className="px-8 py-2.5 bg-emerald-600 text-white rounded-xl font-black shadow-lg shadow-emerald-200 hover:bg-emerald-700 transition-all">
                            Guardar Cambios
                        </button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <div className="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <label className="block text-xs font-bold text-slate-400 uppercase mb-2">Glosa de Auditoría (Motivo del cambio)</label>
                        <textarea 
                            className="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400 transition-all"
                            rows="2"
                            value={form.glosa_auditoria}
                            onChange={(e) => setForm({...form, glosa_auditoria: e.target.value})}
                        />
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <label className="block text-xs font-bold text-slate-400 uppercase mb-2">Fecha del Ajuste</label>
                        <input 
                            type="date" 
                            className="w-full border border-slate-200 rounded-xl p-3 outline-none focus:ring-2 focus:ring-blue-100"
                            value={form.fecha_ajuste}
                            onChange={(e) => setForm({...form, fecha_ajuste: e.target.value})}
                        />
                    </div>
                </div>

                {/* El div ya NO tiene overflow-hidden para permitir que el buscador se vea completo */}
                <div className="bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <table className="w-full border-collapse">
                        <thead className="bg-slate-900 text-white text-[11px] uppercase tracking-widest font-bold">
                            <tr>
                                <th className="px-6 py-4 text-left rounded-tl-2xl">Línea / Cuenta Actual</th>
                                <th className="px-6 py-4 text-right">Debe</th>
                                <th className="px-6 py-4 text-right">Haber</th>
                                <th className="px-6 py-4 text-left bg-slate-800 rounded-tr-2xl">Nueva Imputación</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {detalles.map((det, idx) => {
                                const nombreFila = (det.cuenta_nombre || '').toUpperCase();
                                
                                // Verificamos si esta línea está en nuestro Set de anuladas
                                const esAnulada = indicesAnulados.has(idx);
                                
                                // Bloqueamos si está anulada, o si es de sistema
                                const esBloqueada = 
                                    esAnulada ||
                                    ['210101', '210201', '110401', '352105'].includes(det.cuenta_contable) || 
                                    nombreFila.includes('IVA') || 
                                    nombreFila.includes('PROVEEDOR');
                                
                                return (
                                    <tr key={idx} className={esBloqueada ? 'bg-slate-50' : 'hover:bg-blue-50/30'}>
                                        <td className="px-6 py-4">
                                            <div className="font-bold text-slate-800">{det.cuenta_nombre}</div>
                                            <div className="text-xs font-mono text-slate-400">{det.cuenta_contable}</div>
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-emerald-600">
                                            {Number(det.debe) > 0 ? Number(det.debe).toLocaleString('es-CL') : '-'}
                                        </td>
                                        <td className="px-6 py-4 text-right font-mono text-red-600">
                                            {Number(det.haber) > 0 ? Number(det.haber).toLocaleString('es-CL') : '-'}
                                        </td>
                                        <td className="px-6 py-4 bg-slate-50/50">
                                            {esAnulada ? (
                                                <span className="text-[10px] font-bold text-slate-400 uppercase italic flex items-center gap-2">
                                                    <svg className="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                                    Historial (Reclasificada)
                                                </span>
                                            ) : esBloqueada ? (
                                                <span className="text-[10px] font-bold text-slate-400 uppercase italic flex items-center gap-2">
                                                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/></svg>
                                                    Línea Protegida
                                                </span>
                                            ) : (
                                                <BuscadorCuentaContable 
                                                    cuentas={cuentasPlan}
                                                    valor={form.lineas_editadas[idx] || ''}
                                                    onChange={(codigo) => {
                                                        setForm({
                                                            ...form,
                                                            lineas_editadas: { ...form.lineas_editadas, [idx]: codigo }
                                                        });
                                                    }}
                                                />
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};

export default ReclasificadorAsiento;