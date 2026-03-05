import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../Configuracion/api';

const formatMoneda = (valor) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(valor);

const Dashboard = () => {
    const [loading, setLoading] = useState(true);
    const [metricas, setMetricas] = useState({
        cotizacionesAprobadas: 0,
        facturasPendientesMonto: 0,
        clientesActivos: 0,
        facturasPendientesCount: 0
    });
    const [facturasUrgentes, setFacturasUrgentes] = useState([]);

    useEffect(() => {
        cargarDatosReales();
    }, []);

    const cargarDatosReales = async () => {
        setLoading(true);
        try {
            // 1. Obtener Clientes (Para contar cuántos hay)
            const resClientes = await api.get('/clientes');
            const totalClientes = resClientes.success && resClientes.data ? resClientes.data.length : 0;

            // 2. Obtener Cotizaciones (Para sumar el total de las aceptadas/aprobadas)
            const resCoti = await api.get('/cotizaciones');
            let montoCotizaciones = 0;
            if (resCoti.success && resCoti.data) {
                montoCotizaciones = resCoti.data
                    .filter(c => c.estado_nombre === 'ACEPTADA' || c.estado_nombre === 'APROBADA')
                    .reduce((acc, curr) => acc + parseFloat(curr.total || 0), 0);
            }

            // 3. Obtener Facturas Pendientes (Para sumar deuda y llenar la tabla de alertas)
            const resFacturas = await api.get('/facturas/historial?estado=REGISTRADA');
            let montoPendiente = 0;
            let pendientesTop5 = [];
            let totalPendientes = 0;

            if (resFacturas.success && resFacturas.data) {
                const facturas = resFacturas.data;
                totalPendientes = facturas.length;
                pendientesTop5 = facturas.slice(0, 5); // Tomamos solo las 5 más recientes para la tabla
                montoPendiente = facturas.reduce((acc, curr) => acc + parseFloat(curr.monto_bruto || 0), 0);
            }

            setMetricas({
                cotizacionesAprobadas: montoCotizaciones,
                facturasPendientesMonto: montoPendiente,
                clientesActivos: totalClientes,
                facturasPendientesCount: totalPendientes
            });
            setFacturasUrgentes(pendientesTop5);

        } catch (error) {
            console.error("Error al cargar el dashboard:", error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return (
            <div className="h-[70vh] flex flex-col items-center justify-center text-slate-400">
                <div className="animate-spin rounded-full h-12 w-12 border-b-4 border-emerald-500 mb-4"></div>
                <h2 className="text-xl font-bold">Calculando métricas del sistema...</h2>
            </div>
        );
    }

    return (
        // Contenedor expandido para aprovechar mejor la pantalla (max-w-[90rem])
        <div className="w-full max-w-[90rem] mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 pb-10">
            
            {/* CABECERA */}
            <div className="mb-8 md:mb-10">
                <h1 className="text-3xl md:text-4xl font-black text-slate-900">Resumen Ejecutivo</h1>
                <p className="text-slate-500 text-base mt-2">Radiografía financiera y operativa en tiempo real.</p>
            </div>

            {/* SECCIÓN 1: KPIs (Tarjetas más grandes y con datos reales) */}
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 md:gap-8 mb-10">
                
                {/* Ingresos / Cotizaciones */}
                <div className="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden group">
                    <div className="absolute top-0 right-0 p-4 opacity-5 transform translate-x-4 -translate-y-4 group-hover:scale-110 transition-transform">
                        <svg className="w-32 h-32 text-emerald-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    </div>
                    <p className="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">Ventas / Cot. Aprobadas</p>
                    <h3 className="text-4xl font-black text-slate-800 truncate" title={formatMoneda(metricas.cotizacionesAprobadas)}>
                        {formatMoneda(metricas.cotizacionesAprobadas)}
                    </h3>
                    <div className="mt-4 flex items-center text-sm font-bold text-emerald-600 bg-emerald-50 w-fit px-3 py-1 rounded-lg">
                        <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        Ingresos proyectados
                    </div>
                </div>

                {/* Cuentas por Pagar / Facturas */}
                <div className="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden group">
                    <div className="absolute top-0 right-0 p-4 opacity-5 transform translate-x-4 -translate-y-4 group-hover:scale-110 transition-transform">
                        <svg className="w-32 h-32 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M3 3h18v18H3V3zm16 16V5H5v14h14zm-7-2h-2v-4H8v-2h2V9h2v2h2v2h-2v4z"/></svg>
                    </div>
                    <p className="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">Cuentas por Pagar</p>
                    <h3 className="text-4xl font-black text-slate-800 truncate" title={formatMoneda(metricas.facturasPendientesMonto)}>
                        {formatMoneda(metricas.facturasPendientesMonto)}
                    </h3>
                    <div className="mt-4 flex items-center text-sm font-bold text-red-600 bg-red-50 w-fit px-3 py-1 rounded-lg">
                        <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Deuda pendiente
                    </div>
                </div>

                {/* Clientes */}
                <div className="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden group">
                    <div className="absolute top-0 right-0 p-4 opacity-5 transform translate-x-4 -translate-y-4 group-hover:scale-110 transition-transform">
                        <svg className="w-32 h-32 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <p className="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">Clientes Activos</p>
                    <h3 className="text-4xl font-black text-slate-800">{metricas.clientesActivos}</h3>
                    <div className="mt-4 flex items-center text-sm font-bold text-blue-600 bg-blue-50 w-fit px-3 py-1 rounded-lg">
                        <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        En la base de datos
                    </div>
                </div>

                {/* Facturas Físicas Pendientes */}
                <div className="bg-white p-8 rounded-2xl border border-slate-200 shadow-sm relative overflow-hidden group">
                    <div className="absolute top-0 right-0 p-4 opacity-5 transform translate-x-4 -translate-y-4 group-hover:scale-110 transition-transform">
                        <svg className="w-32 h-32 text-amber-500" fill="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <p className="text-sm font-bold text-slate-400 uppercase tracking-widest mb-2">Doc. por Pagar</p>
                    <h3 className="text-4xl font-black text-slate-800">{metricas.facturasPendientesCount}</h3>
                    <div className="mt-4 flex items-center text-sm font-bold text-amber-600 bg-amber-50 w-fit px-3 py-1 rounded-lg">
                        <svg className="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Requieren gestión
                    </div>
                </div>
            </div>

            {/* SECCIÓN 2: GRID DE ACCIONES Y ALERTAS */}
            <div className="grid grid-cols-1 xl:grid-cols-3 gap-8">
                
                {/* ACCIONES RÁPIDAS (Más grandes y claras) */}
                <div className="xl:col-span-1 space-y-5">
                    <h3 className="text-xl font-bold text-slate-800 mb-5 flex items-center gap-2">
                        <svg className="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Accesos Rápidos
                    </h3>
                    
                    <Link to="/cotizaciones" className="block w-full bg-white border border-slate-200 p-5 rounded-2xl hover:border-emerald-500 hover:shadow-lg transition-all group">
                        <div className="flex items-center gap-5">
                            <div className="bg-emerald-50 text-emerald-600 p-4 rounded-xl group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                                <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <div>
                                <h4 className="text-lg font-bold text-slate-800">Nueva Cotización</h4>
                                <p className="text-sm text-slate-500 mt-1">Generar propuesta comercial a cliente</p>
                            </div>
                        </div>
                    </Link>

                    <Link to="/facturas/historial" className="block w-full bg-white border border-slate-200 p-5 rounded-2xl hover:border-blue-500 hover:shadow-lg transition-all group">
                        <div className="flex items-center gap-5">
                            <div className="bg-blue-50 text-blue-600 p-4 rounded-xl group-hover:bg-blue-500 group-hover:text-white transition-colors">
                                <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <div>
                                <h4 className="text-lg font-bold text-slate-800">Registrar Factura</h4>
                                <p className="text-sm text-slate-500 mt-1">Ingresar documento de proveedor</p>
                            </div>
                        </div>
                    </Link>

                    <Link to="/clientes" className="block w-full bg-white border border-slate-200 p-5 rounded-2xl hover:border-purple-500 hover:shadow-lg transition-all group">
                        <div className="flex items-center gap-5">
                            <div className="bg-purple-50 text-purple-600 p-4 rounded-xl group-hover:bg-purple-500 group-hover:text-white transition-colors">
                                <svg className="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                            </div>
                            <div>
                                <h4 className="text-lg font-bold text-slate-800">Directorio de Clientes</h4>
                                <p className="text-sm text-slate-500 mt-1">Administrar o registrar empresas</p>
                            </div>
                        </div>
                    </Link>
                </div>

                {/* TABLA DE ATENCIÓN REQUERIDA (Con Datos Reales) */}
                <div className="xl:col-span-2">
                    <h3 className="text-xl font-bold text-slate-800 mb-5 flex items-center gap-2">
                        <svg className="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        Atención Requerida (Facturas Pendientes)
                    </h3>
                    
                    <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-[calc(100%-3rem)]">
                        {facturasUrgentes.length === 0 ? (
                            <div className="flex-1 flex flex-col items-center justify-center p-10 text-slate-400">
                                <div className="bg-emerald-50 text-emerald-500 p-4 rounded-full mb-4">
                                    <svg className="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <h3 className="text-xl font-bold text-slate-800">¡Todo al día!</h3>
                                <p className="mt-2 text-center text-slate-500">No tienes facturas de proveedores marcadas como pendientes de pago en este momento.</p>
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto flex-1">
                                    <table className="min-w-full text-left">
                                        <thead className="bg-slate-50 border-b border-slate-100">
                                            <tr>
                                                <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Documento</th>
                                                <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Proveedor</th>
                                                <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Monto Bruto</th>
                                                <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {facturasUrgentes.map(fac => (
                                                <tr key={fac.id} className="hover:bg-slate-50 transition-colors">
                                                    <td className="px-6 py-4 font-mono text-sm font-bold text-slate-600">Fac. {fac.numero_factura}</td>
                                                    <td className="px-6 py-4 text-sm font-bold text-slate-800 truncate max-w-[200px]">{fac.nombre_proveedor}</td>
                                                    <td className="px-6 py-4 text-base font-black text-slate-900 text-right">{formatMoneda(fac.monto_bruto)}</td>
                                                    <td className="px-6 py-4 text-center">
                                                        <Link to="/facturas/historial" className="inline-flex items-center gap-1.5 text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-4 py-2 rounded-lg border border-emerald-200 transition-colors">
                                                            <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                            Pagar
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                                <div className="bg-slate-50 p-4 text-center border-t border-slate-200 mt-auto">
                                    <Link to="/facturas/historial" className="text-sm font-bold text-slate-500 hover:text-blue-600 transition-colors flex items-center justify-center gap-2">
                                        Ir al historial de compras <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                                    </Link>
                                </div>
                            </>
                        )}
                    </div>
                </div>
                
            </div>
        </div>
    );
};

export default Dashboard;