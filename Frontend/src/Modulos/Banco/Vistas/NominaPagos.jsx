import React, { useState, useEffect } from 'react';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';
import * as XLSX from "@e965/xlsx";

const formatCurrency = (amount) => new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP' }).format(amount);

const NominaPagos = () => {
    const [facturas, setFacturas] = useState([]);
    const [cuentasProveedores, setCuentasProveedores] = useState({});
    const [cuentasOrigen, setCuentasOrigen] = useState([]); // Cuentas de la Empresa
    const [cuentaOrigenId, setCuentaOrigenId] = useState(''); // Cuenta seleccionada para pagar
    
    const [loading, setLoading] = useState(true);
    const [seleccionadas, setSeleccionadas] = useState([]);
    const [paso, setPaso] = useState(1);

    useEffect(() => {
        cargarDatosIniciales();
    }, []);

    const cargarDatosIniciales = async () => {
        setLoading(true);
        try {
            // 1. Cargamos las facturas pendientes
            const resFacturas = await api.get('/facturas/historial?estado=REGISTRADA&limit=100');
            if (resFacturas.success) {
                const facturasConVencimiento = resFacturas.data.map(fac => {
                    const fechaEmision = new Date(fac.fecha_emision);
                    const fechaVencimiento = new Date(fechaEmision.setDate(fechaEmision.getDate() + 30));
                    const diasRestantes = Math.ceil((fechaVencimiento - new Date()) / (1000 * 60 * 60 * 24));
                    return { ...fac, fecha_vencimiento: fechaVencimiento, diasRestantes };
                });
                facturasConVencimiento.sort((a, b) => a.diasRestantes - b.diasRestantes);
                setFacturas(facturasConVencimiento);
            }

            // 2. Cargamos las cuentas bancarias de la EMPRESA (Desde donde saldrá la plata)
            const resCuentas = await api.get('/banco/cuentas');
            if (resCuentas.success) {
                setCuentasOrigen(resCuentas.data);
                // Si solo hay una cuenta, la pre-seleccionamos por comodidad
                if (resCuentas.data.length === 1) {
                    setCuentaOrigenId(resCuentas.data[0].id);
                }
            }

        } catch (error) {
            Swal.fire('Error', 'No se pudieron cargar los datos del módulo.', 'error');
        } finally {
            setLoading(false);
        }
    };

    const cargarCuentasBancariasProveedores = async (proveedoresIds) => {
        let cuentasMap = {};
        for (const id of proveedoresIds) {
            try {
                const res = await api.get(`/cuentas-bancarias/proveedor/${id}`);
                if (res.success && res.data.length > 0) {
                    cuentasMap[id] = res.data[0];
                } else {
                    cuentasMap[id] = null;
                }
            } catch (e) {
                cuentasMap[id] = null;
            }
        }
        setCuentasProveedores(cuentasMap);
    };

    const toggleSeleccion = (id) => {
        if (seleccionadas.includes(id)) {
            setSeleccionadas(seleccionadas.filter(item => item !== id));
        } else {
            setSeleccionadas([...seleccionadas, id]);
        }
    };

    const toggleTodas = () => {
        if (seleccionadas.length === facturas.length) {
            setSeleccionadas([]);
        } else {
            setSeleccionadas(facturas.map(f => f.id));
        }
    };

    const irAResumen = async () => {
        if (seleccionadas.length === 0) {
            return Swal.fire({
                icon: 'warning',
                title: 'Selección vacía',
                text: 'Debes seleccionar al menos una factura para generar la nómina.',
                customClass: { confirmButton: 'bg-slate-900 text-white font-bold py-2 px-6 rounded-lg' },
                buttonsStyling: false
            });
        }

        Swal.fire({
            title: 'Preparando Nómina...',
            text: 'Obteniendo datos bancarios de los proveedores.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const facturasAProcesar = facturas.filter(f => seleccionadas.includes(f.id));
        const proveedoresUnicos = [...new Set(facturasAProcesar.map(f => f.proveedor_id))];
        
        await cargarCuentasBancariasProveedores(proveedoresUnicos);
        Swal.close();
        setPaso(2);
    };

    const nominasAgrupadas = Object.values(facturas.filter(f => seleccionadas.includes(f.id)).reduce((acc, fac) => {
        if (!acc[fac.proveedor_id]) {
            acc[fac.proveedor_id] = {
                proveedor_id: fac.proveedor_id,
                rut: fac.rut_proveedor,
                nombre: fac.nombre_proveedor,
                facturas: [],
                total_pagar: 0
            };
        }
        acc[fac.proveedor_id].facturas.push(fac);
        acc[fac.proveedor_id].total_pagar += parseFloat(fac.monto_bruto);
        return acc;
    }, {}));

    const totalNomina = nominasAgrupadas.reduce((sum, prov) => sum + prov.total_pagar, 0);

    const exportarExcelBanco = () => {
        const dataParaBanco = nominasAgrupadas.map(p => {
            const cuenta = cuentasProveedores[p.proveedor_id];
            return {
                'RUT Beneficiario': p.rut,
                'Nombre Beneficiario': p.nombre,
                'Banco Destino': cuenta ? cuenta.banco : 'FALTA CUENTA',
                'N° Cuenta': cuenta ? cuenta.numero_cuenta : 'FALTA CUENTA',
                'Monto a Pagar': p.total_pagar,
                'Glosa / Detalle': `Pago Facturas: ${p.facturas.map(f => f.numero_factura).join(', ')}`,
                'Correo Aviso': cuenta ? 'proveedor@mail.com' : ''
            };
        });

        const ws = XLSX.utils.json_to_sheet(dataParaBanco);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Nomina_Pagos");
        XLSX.writeFile(wb, `Nomina_Banco_${new Date().toISOString().split('T')[0]}.xlsx`);
    };

    // CONEXIÓN REAL AL BACKEND
    const ejecutarPagoMasivo = () => {
        if (!cuentaOrigenId) {
            return Swal.fire({
                icon: 'warning',
                title: 'Cuenta Origen Requerida',
                text: 'Selecciona desde qué cuenta bancaria se realizará este pago masivo.',
                customClass: { confirmButton: 'bg-amber-500 text-white font-bold py-2 px-6 rounded-lg hover:bg-amber-600' },
                buttonsStyling: false
            });
        }

        Swal.fire({
            title: '¿Contabilizar Pago Masivo?',
            html: `Se registrará la salida de <b>${formatCurrency(totalNomina)}</b> desde tu cuenta bancaria.<br/><br/>Se marcarán <b>${seleccionadas.length}</b> facturas como PAGADAS y se generará el Asiento Contable automáticamente.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, Ejecutar Pago',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            customClass: {
                confirmButton: 'bg-emerald-600 text-white font-bold py-2.5 px-6 rounded-lg hover:bg-emerald-700 ml-3',
                cancelButton: 'bg-white text-slate-700 border border-slate-300 font-bold py-2.5 px-6 rounded-lg hover:bg-slate-50',
                popup: 'rounded-2xl'
            },
            buttonsStyling: false
        }).then(async (result) => {
            if (result.isConfirmed) {
                Swal.fire({ title: 'Procesando...', text: 'Generando asientos y rebajando saldos.', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                
                try {
                    const payload = {
                        facturas_ids: seleccionadas,
                        cuenta_bancaria_id: parseInt(cuentaOrigenId)
                    };
                    
                    const res = await api.post('/banco/nomina/pagar', payload);
                    
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Operación Exitosa!',
                            text: `Se contabilizó el egreso y se cuadraron las cuentas. Asiento N° ${res.asiento_id || 'Generado'}`,
                            customClass: { confirmButton: 'bg-emerald-600 text-white py-2 px-6 rounded-lg font-bold' },
                            buttonsStyling: false
                        }).then(() => {
                            setSeleccionadas([]);
                            setPaso(1);
                            cargarDatosIniciales(); // Recargar todo
                        });
                    } else {
                        Swal.fire('Error', res.mensaje, 'error');
                    }
                } catch (err) {
                    Swal.fire('Error', err.response?.data?.mensaje || 'Error crítico de conexión al contabilizar la nómina.', 'error');
                }
            }
        });
    };

    return (
        <div className="max-w-7xl mx-auto p-4 md:p-6 lg:p-8 font-sans text-slate-800 pb-10 animate-fade-in">
            
            {/* CABECERA */}
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                <div>
                    <div className="flex items-center gap-3 mb-2">
                        <span className="bg-blue-100 text-blue-700 text-[10px] font-black px-2.5 py-1 rounded uppercase tracking-widest border border-blue-200">
                            Tesorería y Finanzas
                        </span>
                    </div>
                    <h1 className="text-3xl md:text-4xl font-black text-slate-900 tracking-tight">Nómina de Pagos</h1>
                    <p className="text-slate-500 font-medium mt-1">
                        Agrupa facturas de proveedores y genera archivos para pago bancario.
                    </p>
                </div>
                
                {paso === 1 && facturas.length > 0 && (
                    <div className="bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex items-center gap-4 w-full md:w-auto">
                        <div className="text-right">
                            <p className="text-xs font-bold text-slate-400 uppercase">Total Seleccionado</p>
                            <p className="text-xl font-black text-emerald-600">
                                {formatCurrency(facturas.filter(f => seleccionadas.includes(f.id)).reduce((acc, f) => acc + parseFloat(f.monto_bruto), 0))}
                            </p>
                        </div>
                        <button 
                            onClick={irAResumen}
                            className="bg-slate-900 hover:bg-slate-800 text-white px-6 py-3 rounded-lg font-bold shadow-md transition-all flex items-center gap-2"
                        >
                            Siguiente <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M9 5l7 7-7 7"></path></svg>
                        </button>
                    </div>
                )}
            </div>

            {/* BARRA DE PROGRESO */}
            <div className="flex items-center gap-4 mb-8 bg-slate-50 p-2 rounded-xl border border-slate-200 w-fit mx-auto md:mx-0">
                <div className={`px-4 py-2 rounded-lg font-bold text-sm flex items-center gap-2 ${paso === 1 ? 'bg-white text-blue-700 shadow-sm border border-slate-200' : 'text-slate-500'}`}>
                    <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs ${paso === 1 ? 'bg-blue-100 text-blue-700' : 'bg-slate-200 text-slate-500'}`}>1</span>
                    Seleccionar Facturas
                </div>
                <svg className="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 5l7 7-7 7"></path></svg>
                <div className={`px-4 py-2 rounded-lg font-bold text-sm flex items-center gap-2 ${paso === 2 ? 'bg-white text-emerald-700 shadow-sm border border-slate-200' : 'text-slate-500'}`}>
                    <span className={`w-6 h-6 rounded-full flex items-center justify-center text-xs ${paso === 2 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'}`}>2</span>
                    Resumen y Banco
                </div>
            </div>

            {loading ? (
                 <div className="flex flex-col items-center justify-center h-64 text-slate-400">
                    <div className="animate-spin rounded-full h-10 w-10 border-b-4 border-blue-500 mb-4"></div>
                    <p className="font-bold">Analizando cuentas por pagar...</p>
                 </div>
            ) : paso === 1 ? (
                /* PASO 1: SELECCIÓN DE FACTURAS */
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div className="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                        <label className="flex items-center gap-3 cursor-pointer group">
                            <input 
                                type="checkbox" 
                                className="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer"
                                checked={seleccionadas.length === facturas.length && facturas.length > 0}
                                onChange={toggleTodas}
                            />
                            <span className="font-bold text-slate-700 group-hover:text-blue-600 transition-colors">Seleccionar Todas ({facturas.length})</span>
                        </label>
                        <span className="text-sm font-bold text-slate-400"><i className="fas fa-sort-amount-down-alt mr-1"></i> Ordenadas por vencimiento</span>
                    </div>

                    <div className="overflow-x-auto custom-scrollbar">
                        <table className="min-w-full text-left">
                            <thead className="bg-white border-b border-slate-100">
                                <tr>
                                    <th className="px-6 py-4 w-10"></th>
                                    <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Vencimiento</th>
                                    <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Proveedor</th>
                                    <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Documento</th>
                                    <th className="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Total a Pagar</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {facturas.map(fac => {
                                    const isSelected = seleccionadas.includes(fac.id);
                                    const isVencida = fac.diasRestantes < 0;
                                    const isUrgente = fac.diasRestantes >= 0 && fac.diasRestantes <= 5;
                                    
                                    return (
                                        <tr key={fac.id} onClick={() => toggleSeleccion(fac.id)} className={`cursor-pointer transition-colors ${isSelected ? 'bg-blue-50/50' : 'hover:bg-slate-50'}`}>
                                            <td className="px-6 py-4">
                                                <input 
                                                    type="checkbox" 
                                                    checked={isSelected}
                                                    onChange={() => {}} // Manejado por el onClick del TR
                                                    className="w-5 h-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer pointer-events-none"
                                                />
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center gap-2">
                                                    {isVencida ? (
                                                        <span className="bg-red-100 text-red-700 px-2.5 py-1 rounded text-xs font-bold border border-red-200">Vencida hace {Math.abs(fac.diasRestantes)} días</span>
                                                    ) : isUrgente ? (
                                                        <span className="bg-amber-100 text-amber-700 px-2.5 py-1 rounded text-xs font-bold border border-amber-200">Vence en {fac.diasRestantes} días</span>
                                                    ) : (
                                                        <span className="bg-slate-100 text-slate-600 px-2.5 py-1 rounded text-xs font-bold border border-slate-200">En {fac.diasRestantes} días</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4">
                                                <p className="font-bold text-slate-800">{fac.nombre_proveedor}</p>
                                                <p className="text-xs font-mono text-slate-400 mt-0.5">{fac.rut_proveedor}</p>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className="font-mono text-slate-700 font-bold bg-white border border-slate-200 px-2 py-1 rounded">Fac. {fac.numero_factura}</span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right">
                                                <p className={`text-base font-black ${isSelected ? 'text-blue-700' : 'text-slate-900'}`}>
                                                    {formatCurrency(fac.monto_bruto)}
                                                </p>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            ) : (
                /* PASO 2: RESUMEN Y EXPORTACIÓN */
                <div className="animate-fade-in-up">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        {/* LISTA AGRUPADA POR PROVEEDOR */}
                        <div className="lg:col-span-2 space-y-4">
                            {nominasAgrupadas.map(prov => {
                                const cuentaInfo = cuentasProveedores[prov.proveedor_id];
                                return (
                                    <div key={prov.proveedor_id} className="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                                        <div className="bg-slate-50 p-4 border-b border-slate-200 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
                                            <div>
                                                <h3 className="font-black text-slate-800 text-lg">{prov.nombre}</h3>
                                                <p className="text-xs text-slate-500 font-mono mt-0.5">RUT: {prov.rut}</p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">Total a Transferir</p>
                                                <p className="text-2xl font-black text-emerald-600">{formatCurrency(prov.total_pagar)}</p>
                                            </div>
                                        </div>
                                        
                                        <div className="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            {/* INFO BANCARIA */}
                                            <div className="bg-blue-50/50 p-4 rounded-xl border border-blue-100 flex items-start gap-3">
                                                <div className="bg-white p-2 rounded-lg shadow-sm text-blue-500">
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                                                </div>
                                                <div>
                                                    <p className="text-[10px] font-bold text-blue-500 uppercase tracking-wider mb-1">Datos Bancarios</p>
                                                    {cuentaInfo ? (
                                                        <>
                                                            <p className="font-bold text-slate-800 text-sm">{cuentaInfo.banco}</p>
                                                            <p className="font-mono text-slate-600 text-xs mt-0.5">{cuentaInfo.tipo_cuenta} • {cuentaInfo.numero_cuenta}</p>
                                                        </>
                                                    ) : (
                                                        <p className="text-xs font-bold text-red-500 mt-1 flex items-center gap-1">
                                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg> Sin cuenta registrada
                                                        </p>
                                                    )}
                                                </div>
                                            </div>

                                            {/* FACTURAS INCLUIDAS */}
                                            <div className="bg-slate-50 p-4 rounded-xl border border-slate-100">
                                                <p className="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Facturas a Pagar ({prov.facturas.length})</p>
                                                <div className="flex flex-wrap gap-2">
                                                    {prov.facturas.map(f => (
                                                        <span key={f.id} className="bg-white border border-slate-200 text-slate-600 font-mono text-xs font-bold px-2 py-1 rounded">
                                                            #{f.numero_factura}
                                                        </span>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )
                            })}
                        </div>

                        {/* PANEL LATERAL DE ACCIÓN FINAL */}
                        <div className="lg:col-span-1">
                            <div className="bg-slate-900 rounded-2xl p-6 shadow-xl text-white sticky top-6">
                                <h3 className="font-bold text-slate-400 uppercase tracking-widest text-xs mb-4">Resumen de Operación</h3>
                                
                                <div className="space-y-3 mb-6 pb-6 border-b border-slate-700">
                                    <div className="flex justify-between items-center text-sm">
                                        <span className="text-slate-300">Total Proveedores:</span>
                                        <span className="font-bold">{nominasAgrupadas.length}</span>
                                    </div>
                                    <div className="flex justify-between items-center text-sm">
                                        <span className="text-slate-300">Documentos (Facturas):</span>
                                        <span className="font-bold">{seleccionadas.length}</span>
                                    </div>
                                    <div className="flex justify-between items-end mt-4 pt-4 border-t border-slate-700">
                                        <span className="text-slate-300 font-medium">Total Desembolso:</span>
                                        <span className="text-3xl font-black text-emerald-400 leading-none">{formatCurrency(totalNomina)}</span>
                                    </div>
                                </div>

                                {/* SELECTOR DE CUENTA ORIGEN */}
                                <div className="mb-6 bg-slate-800 p-4 rounded-xl border border-slate-700">
                                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-2">
                                        <svg className="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                        Pagar Desde Cuenta:
                                    </label>
                                    <select
                                        value={cuentaOrigenId}
                                        onChange={(e) => setCuentaOrigenId(e.target.value)}
                                        className="w-full bg-slate-900 border border-slate-600 text-white rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-blue-500 text-sm font-medium cursor-pointer"
                                    >
                                        <option value="">Seleccione cuenta bancaria...</option>
                                        {cuentasOrigen.map(c => (
                                            <option key={c.id} value={c.id}>{c.banco} - {c.numero_cuenta}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="space-y-3">
                                    <button 
                                        onClick={exportarExcelBanco}
                                        className="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-3.5 px-4 rounded-xl transition-all flex items-center justify-center gap-2 border border-slate-700 shadow-sm"
                                    >
                                        <svg className="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        Generar Excel para el Banco
                                    </button>

                                    <button 
                                        onClick={ejecutarPagoMasivo}
                                        className="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3.5 px-4 rounded-xl transition-all shadow-lg shadow-emerald-900/50 flex items-center justify-center gap-2"
                                    >
                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Contabilizar Pagos
                                    </button>
                                    
                                    <button 
                                        onClick={() => setPaso(1)}
                                        className="w-full text-slate-400 hover:text-white text-sm font-bold py-3 mt-2 transition-colors border border-transparent hover:border-slate-700 rounded-xl"
                                    >
                                        Volver a la selección
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            )}
        </div>
    );
};

export default NominaPagos;