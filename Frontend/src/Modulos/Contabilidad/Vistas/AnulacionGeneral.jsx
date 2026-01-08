import React, { useState } from 'react';
import Swal from 'sweetalert2';
import { api } from '../../../Configuracion/api'; 

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);
const formatDate = (dateString) => {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('es-CL', { timeZone: 'UTC' });
};

const AnulacionGeneral = () => {
    const [codigo, setCodigo] = useState('');
    const [documento, setDocumento] = useState(null);
    const [loading, setLoading] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const [motivo, setMotivo] = useState('');
    const [error, setError] = useState(null);
    const [exito, setExito] = useState(null);

    const buscarDocumento = async (e) => {
        e.preventDefault();
        if (!codigo.trim()) return;

        setLoading(true);
        setError(null);
        setDocumento(null);
        setExito(null);
        setMotivo('');

        try {
            const res = await api.post('/anulacion/buscar', { codigo });
            if (res.success) {
                setDocumento(res.data);
            } else {
                setError(res.mensaje || 'Documento no encontrado o código inválido.');
            }
        } catch (err) {
            console.error(err);
            setError('Error de conexión al buscar el documento.');
        } finally {
            setLoading(false);
        }
    };

    const confirmarAnulacion = async () => {
        if (!motivo.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Falta información',
                text: 'Por favor, ingrese un motivo para justificar la anulación.',
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'bg-slate-900 text-white px-6 py-2 rounded-lg hover:bg-slate-800 transition-colors'
                }
            });
            return;
        }
        const confirmacion = await Swal.fire({
            title: '¿Estás seguro?',
            text: "ADVERTENCIA: Esta acción es irreversible y generará un contra-asiento contable automático.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anular documento',
            cancelButtonText: 'Cancelar',
            reverseButtons: true, 
            buttonsStyling: false, 
            customClass: {
                confirmButton: 'bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 focus:ring-4 focus:ring-red-300 font-bold ml-3 transition-all',
                cancelButton: 'bg-slate-500 text-white px-4 py-2 rounded-lg hover:bg-slate-600 focus:ring-4 focus:ring-slate-300 font-medium transition-all'
            }
        });

        if (!confirmacion.isConfirmed) return;

        setProcesando(true);
        Swal.fire({
            title: 'Procesando...',
            text: 'Anulando documento y generando contabilidad...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const res = await api.post('/anulacion/ejecutar', { 
                codigo: documento.codigo, 
                tipo: documento.tipo_origen, 
                motivo 
            });

            if (res.success) {
                const mensajeExito = `Documento anulado correctamente. Se generó el Asiento de Reverso N° ${res.nuevo_asiento_id}`;
                
                await Swal.fire({
                    icon: 'success',
                    title: '¡Anulación Exitosa!',
                    text: mensajeExito,
                    confirmButtonColor: '#10b981'
                });

                setExito(mensajeExito);
                setDocumento(null); 
                setCodigo('');
                setMotivo('');
            } else {
                throw new Error(res.mensaje || 'Error desconocido');
            }
        } catch (err) {
            console.error(err);
            let msgError = 'Error crítico al procesar la anulación.';
            if (err.response && err.response.data && err.response.data.mensaje) {
                msgError = err.response.data.mensaje;
            } else if (err.message) {
                msgError = err.message;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error al anular',
                text: msgError
            });
            setError(msgError);
        } finally {
            setProcesando(false);
        }
    };

    return (
        <div className="max-w-5xl mx-auto font-sans text-slate-800 pb-10">
            
            {/* ENCABEZADO */}
            <div className="mb-8">
                <h1 className="text-2xl font-bold text-slate-900">Anulación de Documentos</h1>
                <p className="text-slate-500 text-sm mt-1">Busque cualquier documento por su código único (Ej: 2626... o 2610...) para proceder con su reversa contable.</p>
            </div>

            {/* TARJETA DE BÚSQUEDA */}
            <div className="bg-white p-6 rounded-xl shadow-sm border border-slate-200 mb-6">
                <form onSubmit={buscarDocumento} className="flex flex-col md:flex-row gap-4 items-end">
                    <div className="flex-1 w-full">
                        <label className="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">
                            Código Único del Documento
                        </label>
                        <div className="relative group">
                            <input 
                                type="text" 
                                className="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-lg focus:bg-white focus:ring-2 focus:ring-blue-500 outline-none transition-all font-mono text-lg font-bold text-slate-700 placeholder-slate-300"
                                placeholder="Ej: 2626000001"
                                value={codigo}
                                onChange={(e) => setCodigo(e.target.value)}
                                autoFocus
                            />
                        </div>
                    </div>
                    <button 
                        type="submit"
                        disabled={loading || !codigo}
                        className="w-full md:w-auto px-8 py-3 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 shadow-lg disabled:opacity-70 transition-all flex justify-center items-center gap-2"
                    >
                        {loading ? (
                            <><i className="fas fa-circle-notch fa-spin"></i> Buscando...</>
                        ) : (
                            <>Buscar Documento</>
                        )}
                    </button>
                </form>

                {/* MENSAJES DE ESTADO */}
                {error && (
                    <div className="mt-4 p-4 bg-red-50 text-red-700 rounded-lg border border-red-100 flex items-start gap-3 animate-fade-in">
                        <svg className="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div>
                            <span className="font-bold">Error:</span> {error}
                        </div>
                    </div>
                )}
                
                {exito && (
                    <div className="mt-4 p-4 bg-emerald-50 text-emerald-700 rounded-lg border border-emerald-100 flex items-start gap-3 animate-fade-in">
                        <svg className="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <div>
                            <span className="font-bold">¡Operación Exitosa!</span> {exito}
                        </div>
                    </div>
                )}
            </div>

            {/* RESULTADO Y ACCIONES */}
            {documento && (
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-fade-in-up">
                    
                    {/* COLUMNA IZQUIERDA: DETALLES DEL DOCUMENTO */}
                    <div className="lg:col-span-2 space-y-6">
                        
                        {/* Header del Documento */}
                        <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                            <div className={`px-6 py-4 border-b flex justify-between items-center ${documento.tipo_origen === 'FACTURA' ? 'bg-blue-50 border-blue-100' : 'bg-purple-50 border-purple-100'}`}>
                                <div className="flex items-center gap-3">
                                    <div className={`p-2 rounded-lg ${documento.tipo_origen === 'FACTURA' ? 'bg-blue-100 text-blue-600' : 'bg-purple-100 text-purple-600'}`}>
                                        {documento.tipo_origen === 'FACTURA' 
                                            ? <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                            : <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        }
                                    </div>
                                    <div>
                                        <p className="text-xs font-bold uppercase tracking-wider opacity-60">Tipo de Documento</p>
                                        <p className="font-bold text-slate-800">{documento.tipo_origen === 'FACTURA' ? 'Factura de Compra' : 'Asiento Manual'}</p>
                                    </div>
                                </div>
                                <div className="text-right">
                                    <span className="block text-xs font-bold text-slate-400 uppercase">Estado Actual</span>
                                    {documento.estado === 'ANULADA' ? (
                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-red-100 text-red-800 border border-red-200 mt-1">
                                            ANULADA
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-200 mt-1">
                                            VIGENTE
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div className="p-6 grid grid-cols-2 gap-6">
                                <div>
                                    <p className="text-xs text-slate-400 font-bold uppercase">Descripción / Glosa</p>
                                    <p className="text-slate-800 font-medium mt-1">{documento.descripcion}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-400 font-bold uppercase">Entidad Relacionada</p>
                                    <p className="text-slate-800 font-medium mt-1">{documento.entidad}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-400 font-bold uppercase">Fecha de Emisión</p>
                                    <p className="text-slate-800 font-mono mt-1">{formatDate(documento.fecha)}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-slate-400 font-bold uppercase">Monto Total</p>
                                    <p className="text-slate-900 font-bold text-lg mt-1">{formatCurrency(documento.monto)}</p>
                                </div>
                            </div>
                        </div>

                        {/* Detalle Contable */}
                        <div className="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                            <div className="px-6 py-3 bg-slate-50 border-b border-slate-200">
                                <h3 className="font-bold text-slate-700 text-sm uppercase">Detalle del Asiento Original</h3>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-white text-slate-400 border-b border-slate-100">
                                        <tr>
                                            <th className="px-6 py-3 text-left font-normal text-xs uppercase">Cuenta</th>
                                            <th className="px-6 py-3 text-right font-normal text-xs uppercase w-32">Debe</th>
                                            <th className="px-6 py-3 text-right font-normal text-xs uppercase w-32">Haber</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50">
                                        {documento.asiento_detalle.map((linea, i) => (
                                            <tr key={i} className="hover:bg-slate-50 transition-colors">
                                                <td className="px-6 py-3">
                                                    <div className="font-bold text-slate-700">{linea.cuenta_contable}</div>
                                                    <div className="text-xs text-slate-400">{linea.nombre_cuenta}</div>
                                                </td>
                                                <td className="px-6 py-3 text-right font-mono text-emerald-600 bg-emerald-50/30">
                                                    {parseFloat(linea.debe) > 0 ? formatCurrency(linea.debe) : '-'}
                                                </td>
                                                <td className="px-6 py-3 text-right font-mono text-red-600 bg-red-50/30">
                                                    {parseFloat(linea.haber) > 0 ? formatCurrency(linea.haber) : '-'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* COLUMNA DERECHA: ACCIÓN DE ANULAR */}
                    <div className="lg:col-span-1">
                        <div className={`rounded-xl border shadow-sm sticky top-6 ${documento.estado === 'ANULADA' ? 'bg-slate-50 border-slate-200' : 'bg-white border-red-200 ring-4 ring-red-50'}`}>
                            <div className="p-5 border-b border-inherit">
                                <h3 className={`font-bold flex items-center gap-2 ${documento.estado === 'ANULADA' ? 'text-slate-500' : 'text-red-700'}`}>
                                    {documento.estado === 'ANULADA' 
                                        ? <><svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> Documento Anulado</>
                                        : <><svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg> Zona de Anulación</>
                                    }
                                </h3>
                            </div>
                            
                            <div className="p-5">
                                {documento.estado === 'ANULADA' ? (
                                    <p className="text-slate-500 text-sm">
                                        Este documento ya fue procesado y su reverso contable generado. No se pueden realizar más acciones.
                                    </p>
                                ) : (
                                    <>
                                        <div className="mb-4">
                                            <label className="block text-xs font-bold text-slate-700 uppercase mb-2">
                                                Motivo de la Anulación <span className="text-red-500">*</span>
                                            </label>
                                            <textarea 
                                                className="w-full p-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 outline-none text-sm min-h-[100px] bg-slate-50 focus:bg-white transition-colors"
                                                placeholder="Describa claramente por qué se está anulando este documento (error de digitación, devolución, etc.)"
                                                value={motivo}
                                                onChange={(e) => setMotivo(e.target.value)}
                                            ></textarea>
                                        </div>

                                        <div className="bg-red-50 p-3 rounded-lg border border-red-100 mb-4">
                                            <p className="text-xs text-red-800 leading-relaxed">
                                                <strong>Atención:</strong> Al confirmar, se generará un asiento contable inverso automáticamente. Esta acción no se puede deshacer.
                                            </p>
                                        </div>

                                        <button 
                                            onClick={confirmarAnulacion}
                                            disabled={procesando || !motivo}
                                            className="w-full py-3 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 shadow-md hover:shadow-lg disabled:opacity-50 disabled:shadow-none transition-all"
                                        >
                                            {procesando ? 'Procesando...' : 'Confirmar Anulación'}
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>

                </div>
            )}
        </div>
    );
};

export default AnulacionGeneral;