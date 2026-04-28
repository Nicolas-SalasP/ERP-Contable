import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const CierreF29 = () => {
    const d = new Date();
    const mesActual = d.getMonth() + 1;
    const anioActual = d.getFullYear();

    const [mes, setMes] = useState(mesActual.toString().padStart(2, '0'));
    const [anio, setAnio] = useState(anioActual.toString());
    const [datosCierre, setDatosCierre] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        simularCierre();
    }, [mes, anio]);

    const simularCierre = async () => {
        setLoading(true);
        try {
            const res = await api.get(`/impuestos/cierre-f29/simular/${mes}/${anio}`);
            if (res.success) {
                setDatosCierre(res.data);
            }
        } catch (error) {
            Swal.fire({
                icon: 'error', title: 'Error', text: 'No se pudo obtener la información de IVA.',
                buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }
            });
        } finally {
            setLoading(false);
        }
    };

    const ejecutarCentralizacion = async () => {
        const confirm = await Swal.fire({
            title: '¿Generar Asiento F29?',
            text: "Esto dejará las cuentas de IVA en 0 y traspasará el saldo a la cuenta IVA por Pagar o Remanente.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, Centralizar',
            cancelButtonText: 'Cancelar',
            buttonsStyling: false,
            customClass: {
                confirmButton: 'bg-indigo-600 text-white font-bold py-2.5 px-6 rounded-lg mx-2 hover:bg-indigo-700',
                cancelButton: 'bg-slate-200 text-slate-800 font-bold py-2.5 px-6 rounded-lg mx-2 hover:bg-slate-300'
            }
        });

        if (confirm.isConfirmed) {
            try {
                const payload = { 
                    mes: parseInt(mes, 10), 
                    anio: parseInt(anio, 10) 
                };
                const res = await api.post('/impuestos/cierre-f29/ejecutar', payload);
                if (res.success) {
                    Swal.fire({ icon: 'success', title: '¡Asiento Generado!', text: 'El IVA del mes ha sido cerrado.', timer: 2000, showConfirmButton: false });
                    simularCierre();
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error', title: 'Error', text: error.response?.data?.mensaje || 'Error al centralizar.',
                    buttonsStyling: false, customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' }
                });
            }
        }
    };

    const nombresMeses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

    return (
        <div className="max-w-6xl mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 animate-fade-in pb-10">
            {/* CABECERA Y FILTROS */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-6">
                <div>
                    <div className="flex items-center gap-3 mb-2">
                        <span className="bg-indigo-100 text-indigo-700 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-indigo-200">
                            Impuestos Mensuales
                        </span>
                    </div>
                    <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Cierre de IVA (F29)</h1>
                    <p className="text-slate-500 font-medium mt-1">Calcula y centraliza tus impuestos mensuales automáticamente.</p>
                </div>

                <div className="bg-white p-3 rounded-2xl border border-slate-200 shadow-sm flex items-center gap-3 w-full md:w-auto">
                    <div className="flex flex-col">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Mes</label>
                        <select value={mes} onChange={e => setMes(e.target.value)} className="bg-slate-50 border-none font-bold text-slate-700 p-2 rounded-lg outline-none cursor-pointer focus:ring-2 focus:ring-indigo-500">
                            {nombresMeses.map((nombre, i) => {
                                const mesNum = (i + 1).toString().padStart(2, '0');
                                return <option key={mesNum} value={mesNum}>{nombre}</option>;
                            })}
                        </select>
                    </div>
                    <div className="w-px h-10 bg-slate-200"></div>
                    <div className="flex flex-col">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Año</label>
                        <select value={anio} onChange={e => setAnio(e.target.value)} className="bg-slate-50 border-none font-bold text-slate-700 p-2 rounded-lg outline-none cursor-pointer focus:ring-2 focus:ring-indigo-500">
                            {[anioActual - 1, anioActual, anioActual + 1].map(y => (
                                <option key={y} value={y}>{y}</option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {loading || !datosCierre ? (
                <div className="flex flex-col items-center justify-center py-20 text-slate-400">
                    <div className="animate-spin rounded-full h-10 w-10 border-b-4 border-indigo-500 mb-4"></div>
                    <p className="font-bold">Calculando libros de compra y venta...</p>
                </div>
            ) : (
                <div className="space-y-6">
                    {/* DASHBOARD NUMÉRICO */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden group">
                            <div className="absolute top-0 right-0 p-4 opacity-10 text-rose-500 transform group-hover:scale-110 transition-transform">
                                <i className="fas fa-arrow-up text-6xl"></i>
                            </div>
                            <p className="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1 relative z-10">Generado por Ventas</p>
                            <h3 className="text-lg font-bold text-slate-700 mb-2 relative z-10">IVA Débito Fiscal</h3>
                            <p className="text-3xl font-black text-rose-600 relative z-10">{formatCurrency(datosCierre.ventas.iva_debito)}</p>
                        </div>

                        <div className="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm relative overflow-hidden group">
                            <div className="absolute top-0 right-0 p-4 opacity-10 text-emerald-500 transform group-hover:scale-110 transition-transform">
                                <i className="fas fa-arrow-down text-6xl"></i>
                            </div>
                            <p className="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-1 relative z-10">A Favor por Compras</p>
                            <h3 className="text-lg font-bold text-slate-700 mb-2 relative z-10">IVA Crédito Fiscal</h3>
                            <p className="text-3xl font-black text-emerald-600 relative z-10">{formatCurrency(datosCierre.compras.iva_credito)}</p>
                        </div>

                        <div className={`rounded-2xl p-6 border shadow-sm relative overflow-hidden ${datosCierre.resumen.total_a_pagar > 0 ? 'bg-indigo-600 border-indigo-700 text-white' : 'bg-emerald-600 border-emerald-700 text-white'}`}>
                            <p className={`text-[11px] font-black uppercase tracking-widest mb-1 ${datosCierre.resumen.total_a_pagar > 0 ? 'text-indigo-200' : 'text-emerald-200'}`}>Resultado del Mes</p>
                            <h3 className="text-lg font-bold mb-2">
                                {datosCierre.resumen.total_a_pagar > 0 ? 'Monto a Pagar (F29)' : 'Remanente para mes sgte.'}
                            </h3>
                            <p className="text-4xl font-black">
                                {formatCurrency(datosCierre.resumen.total_a_pagar > 0 ? datosCierre.resumen.total_a_pagar : datosCierre.resumen.remanente)}
                            </p>
                        </div>
                    </div>

                    {/* SECCIÓN DE ACCIÓN (ASIENTO CONTABLE) */}
                    {!datosCierre.ya_cerrado && (datosCierre.ventas.iva_debito > 0 || datosCierre.compras.iva_credito > 0) && (
                        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mt-8">
                            <div className="bg-slate-50 px-6 py-4 border-b border-slate-200">
                                <h3 className="font-bold text-slate-800 flex items-center gap-2">
                                    <i className="fas fa-book-open text-indigo-500"></i>
                                    Vista Previa del Asiento de Centralización
                                </h3>
                                <p className="text-xs text-slate-500 mt-1">Este asiento dejará en cero las cuentas mensuales y registrará el PPM como activo.</p>
                            </div>

                            <div className="p-6">
                                <table className="w-full text-sm text-left mb-6">
                                    <thead className="text-xs text-slate-400 border-b border-slate-100">
                                        <tr>
                                            <th className="pb-3 font-medium">Cuenta Contable</th>
                                            <th className="pb-3 font-medium text-right">Debe</th>
                                            <th className="pb-3 font-medium text-right">Haber</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50">
                                        {/* DEBE: Reversa Débito */}
                                        {datosCierre.ventas.iva_debito > 0 && (
                                            <tr>
                                                <td className="py-3 font-bold text-slate-700">[210201] IVA Débito Fiscal</td>
                                                <td className="py-3 font-black text-slate-800 text-right">{formatCurrency(datosCierre.ventas.iva_debito)}</td>
                                                <td className="py-3 text-right text-slate-300">-</td>
                                            </tr>
                                        )}
                                        {/* DEBE: Reconocimiento PPM como Activo (LA NUEVA LÍNEA) */}
                                        {datosCierre.ppm.monto > 0 && (
                                            <tr>
                                                <td className="py-3 font-bold text-indigo-600">[110403] PPM por Recuperar</td>
                                                <td className="py-3 font-black text-indigo-600 text-right">{formatCurrency(datosCierre.ppm.monto)}</td>
                                                <td className="py-3 text-right text-slate-300">-</td>
                                            </tr>
                                        )}
                                        {/* DEBE: Remanente (si hay) */}
                                        {datosCierre.resumen.remanente > 0 && (
                                            <tr>
                                                <td className="py-3 font-bold text-emerald-600">[110402] IVA Remanente</td>
                                                <td className="py-3 font-black text-emerald-600 text-right">{formatCurrency(datosCierre.resumen.remanente)}</td>
                                                <td className="py-3 text-right text-slate-300">-</td>
                                            </tr>
                                        )}
                                        {/* HABER: Reversa Crédito */}
                                        {datosCierre.compras.iva_credito > 0 && (
                                            <tr>
                                                <td className="py-3 font-bold text-slate-700">[110001] IVA Crédito Fiscal</td>
                                                <td className="py-3 text-right text-slate-300">-</td>
                                                <td className="py-3 font-black text-slate-800 text-right">{formatCurrency(datosCierre.compras.iva_credito)}</td>
                                            </tr>
                                        )}
                                        {/* HABER: A pagar (Incluye el PPM) */}
                                        {datosCierre.resumen.total_a_pagar > 0 && (
                                            <tr>
                                                <td className="py-3 font-bold text-rose-600">[210301] IVA por Pagar (F29)</td>
                                                <td className="py-3 text-right text-slate-300">-</td>
                                                <td className="py-3 font-black text-rose-600 text-right">{formatCurrency(datosCierre.resumen.total_a_pagar)}</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>

                                <div className="border-t border-slate-100 pt-6 flex justify-end">
                                    <button
                                        onClick={ejecutarCentralizacion}
                                        className="bg-indigo-600 hover:bg-indigo-700 text-white font-black py-3 px-8 rounded-xl shadow-lg shadow-indigo-500/30 transition-all flex items-center gap-2"
                                    >
                                        <i className="fas fa-magic"></i>
                                        Generar Centralización
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

export default CierreF29;