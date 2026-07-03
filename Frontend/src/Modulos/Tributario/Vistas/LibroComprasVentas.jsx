import React, { useState, useCallback } from 'react';
import { usePermisos } from '../../../Contextos/Permisos';
import { lcv } from '../Servicios/tributarioApi';
import { formatearMoneda } from '../../../Utilidades/formato';

const hoy = new Date();
const MES_ACTUAL  = hoy.getMonth() + 1;
const ANIO_ACTUAL = hoy.getFullYear();

const MESES = [
    { value: 1,  label: 'Enero' },
    { value: 2,  label: 'Febrero' },
    { value: 3,  label: 'Marzo' },
    { value: 4,  label: 'Abril' },
    { value: 5,  label: 'Mayo' },
    { value: 6,  label: 'Junio' },
    { value: 7,  label: 'Julio' },
    { value: 8,  label: 'Agosto' },
    { value: 9,  label: 'Septiembre' },
    { value: 10, label: 'Octubre' },
    { value: 11, label: 'Noviembre' },
    { value: 12, label: 'Diciembre' },
];

const ANIOS = Array.from({ length: ANIO_ACTUAL - 2019 }, (_, i) => ANIO_ACTUAL - i);

const selectCls =
    'px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-slate-200 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const formatMoneda = formatearMoneda;

const descargarBlob = (blob, nombre) => {
    const url = URL.createObjectURL(blob);
    const a   = document.createElement('a');
    a.href     = url;
    a.download = nombre;
    a.click();
    URL.revokeObjectURL(url);
};

const TablaVentas = ({ lineas, totales }) => (
    <div className="overflow-x-auto">
        <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                <tr>
                    <th className="px-3 py-3 text-left font-semibold">Tipo DTE</th>
                    <th className="px-3 py-3 text-left font-semibold">Folio</th>
                    <th className="px-3 py-3 text-left font-semibold">RUT Receptor</th>
                    <th className="px-3 py-3 text-left font-semibold">Razón Social</th>
                    <th className="px-3 py-3 text-left font-semibold">Fecha</th>
                    <th className="px-3 py-3 text-right font-semibold">Neto</th>
                    <th className="px-3 py-3 text-right font-semibold">IVA</th>
                    <th className="px-3 py-3 text-right font-semibold">Exento</th>
                    <th className="px-3 py-3 text-right font-semibold">Total</th>
                    <th className="px-3 py-3 text-left font-semibold">Estado</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                {lineas.map((l, i) => (
                    <tr key={i} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td className="px-3 py-2.5 text-slate-700 dark:text-slate-300">
                            <span className="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">{l.tipo_dte}</span>
                            <span className="ml-1.5 text-slate-500 dark:text-slate-400">{l.tipo_dte_glosa}</span>
                        </td>
                        <td className="px-3 py-2.5 font-mono text-xs text-slate-700 dark:text-slate-300">{l.folio}</td>
                        <td className="px-3 py-2.5 font-mono text-xs text-slate-600 dark:text-slate-400">{l.rut_receptor}</td>
                        <td className="px-3 py-2.5 text-slate-700 dark:text-slate-300 max-w-[180px] truncate">{l.razon_social}</td>
                        <td className="px-3 py-2.5 text-slate-600 dark:text-slate-400 whitespace-nowrap">{l.fecha_emision}</td>
                        <td className="px-3 py-2.5 text-right text-slate-700 dark:text-slate-300 tabular-nums">{formatMoneda(l.monto_neto)}</td>
                        <td className="px-3 py-2.5 text-right text-slate-700 dark:text-slate-300 tabular-nums">{formatMoneda(l.iva)}</td>
                        <td className="px-3 py-2.5 text-right text-slate-500 dark:text-slate-400 tabular-nums">{formatMoneda(l.monto_exento)}</td>
                        <td className="px-3 py-2.5 text-right font-semibold text-slate-900 dark:text-slate-100 tabular-nums">{formatMoneda(l.monto_total)}</td>
                        <td className="px-3 py-2.5">
                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold ${
                                l.estado === 'ACEPTADO' ? 'bg-emerald-100 text-emerald-700'
                                : l.estado === 'RECHAZADO' ? 'bg-red-100 text-red-700'
                                : 'bg-slate-100 text-slate-600'
                            }`}>
                                {l.estado}
                            </span>
                        </td>
                    </tr>
                ))}
            </tbody>
            {totales && (
                <tfoot className="border-t-2 border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 font-semibold text-sm">
                    <tr>
                        <td className="px-3 py-3 text-slate-700 dark:text-slate-300" colSpan={5}>
                            TOTAL — {totales.cantidad} documento(s)
                        </td>
                        <td className="px-3 py-3 text-right text-slate-900 dark:text-slate-100 tabular-nums">{formatMoneda(totales.monto_neto)}</td>
                        <td className="px-3 py-3 text-right text-slate-900 dark:text-slate-100 tabular-nums">{formatMoneda(totales.iva)}</td>
                        <td className="px-3 py-3 text-right text-slate-900 dark:text-slate-100 tabular-nums">{formatMoneda(totales.monto_exento)}</td>
                        <td className="px-3 py-3 text-right text-slate-900 dark:text-slate-100 tabular-nums">{formatMoneda(totales.monto_total)}</td>
                        <td className="px-3 py-3"></td>
                    </tr>
                </tfoot>
            )}
        </table>
    </div>
);

const TablaCompras = ({ lineas, totales }) => (
    <div className="overflow-x-auto">
        <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                <tr>
                    <th className="px-3 py-3 text-left font-semibold">Tipo DTE</th>
                    <th className="px-3 py-3 text-left font-semibold">N° Factura</th>
                    <th className="px-3 py-3 text-left font-semibold">RUT Emisor</th>
                    <th className="px-3 py-3 text-left font-semibold">Razón Social</th>
                    <th className="px-3 py-3 text-left font-semibold">Fecha</th>
                    <th className="px-3 py-3 text-right font-semibold">Neto</th>
                    <th className="px-3 py-3 text-right font-semibold">IVA Recuperable</th>
                    <th className="px-3 py-3 text-right font-semibold">No Recuperable</th>
                    <th className="px-3 py-3 text-right font-semibold">Total</th>
                    <th className="px-3 py-3 text-left font-semibold">Uso</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                {lineas.map((l, i) => (
                    <tr key={i} className="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <td className="px-3 py-2.5 text-slate-700 dark:text-slate-300">
                            <span className="font-mono text-xs bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">{l.tipo_dte}</span>
                            <span className="ml-1.5 text-slate-500 dark:text-slate-400">{l.tipo_dte_glosa}</span>
                        </td>
                        <td className="px-3 py-2.5 font-mono text-xs text-slate-700 dark:text-slate-300">{l.numero_factura}</td>
                        <td className="px-3 py-2.5 font-mono text-xs text-slate-600 dark:text-slate-400">{l.rut_emisor}</td>
                        <td className="px-3 py-2.5 text-slate-700 dark:text-slate-300 max-w-[180px] truncate">{l.razon_social}</td>
                        <td className="px-3 py-2.5 text-slate-600 dark:text-slate-400 whitespace-nowrap">{l.fecha_emision}</td>
                        <td className="px-3 py-2.5 text-right text-slate-700 dark:text-slate-300 tabular-nums">{formatMoneda(l.monto_neto)}</td>
                        <td className="px-3 py-2.5 text-right text-emerald-700 dark:text-emerald-400 tabular-nums">{formatMoneda(l.iva_recuperable)}</td>
                        <td className="px-3 py-2.5 text-right text-slate-500 dark:text-slate-400 tabular-nums">{formatMoneda(l.monto_no_recuperable)}</td>
                        <td className="px-3 py-2.5 text-right font-semibold text-slate-900 dark:text-slate-100 tabular-nums">{formatMoneda(l.monto_total)}</td>
                        <td className="px-3 py-2.5">
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                {l.indicador_uso}
                            </span>
                        </td>
                    </tr>
                ))}
            </tbody>
            {totales && (
                <tfoot className="border-t-2 border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 font-semibold text-sm">
                    <tr>
                        <td className="px-3 py-3 text-slate-700 dark:text-slate-300" colSpan={5}>
                            TOTAL — {totales.cantidad} documento(s)
                        </td>
                        <td className="px-3 py-3 text-right text-slate-900 dark:text-slate-100 tabular-nums">{formatMoneda(totales.monto_neto)}</td>
                        <td className="px-3 py-3 text-right text-emerald-700 dark:text-emerald-400 tabular-nums">{formatMoneda(totales.iva)}</td>
                        <td className="px-3 py-3 text-right text-slate-900 dark:text-slate-100 tabular-nums">—</td>
                        <td className="px-3 py-3 text-right text-slate-900 dark:text-slate-100 tabular-nums">{formatMoneda(totales.monto_total)}</td>
                        <td className="px-3 py-3"></td>
                    </tr>
                </tfoot>
            )}
        </table>
    </div>
);

const LibroComprasVentas = () => {
    const { tienePermiso } = usePermisos();
    const puedeVer = tienePermiso('tributario.ver');

    const [mes,  setMes]  = useState(MES_ACTUAL);
    const [anio, setAnio] = useState(ANIO_ACTUAL);
    const [tab,  setTab]  = useState('ventas');

    const [datosVentas,  setDatosVentas]  = useState(null);
    const [datosCompras, setDatosCompras] = useState(null);
    const [cargando,     setCargando]     = useState(false);
    const [descargando,  setDescargando]  = useState(null);
    const [mensaje,      setMensaje]      = useState(null);

    const mostrarMensaje = (tipo, texto) => {
        setMensaje({ tipo, texto });
        setTimeout(() => setMensaje(null), 5000);
    };

    const consultar = useCallback(async () => {
        if (!puedeVer) return;
        setCargando(true);
        try {
            const [respVentas, respCompras] = await Promise.all([
                lcv.ventas(mes, anio),
                lcv.compras(mes, anio),
            ]);
            setDatosVentas(respVentas);
            setDatosCompras(respCompras);
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al consultar el LCV.';
            mostrarMensaje('error', msg);
        } finally {
            setCargando(false);
        }
    }, [puedeVer, mes, anio]);

    const handleDescargar = async (tipo, formato) => {
        const claveDescarga = `${tipo}-${formato}`;
        setDescargando(claveDescarga);
        try {
            const resp = tipo === 'ventas'
                ? await lcv.descargarVentas(mes, anio, formato)
                : await lcv.descargarCompras(mes, anio, formato);

            const ext    = formato === 'excel' ? 'xls' : 'csv';
            const libro  = tipo === 'ventas' ? 'VENTAS' : 'COMPRAS';
            const nombre = `LCV_${libro}_${String(mes).padStart(2, '0')}${anio}.${ext}`;
            descargarBlob(resp.data, nombre);
        } catch (_) {
            // el interceptor global muestra el toast
        } finally {
            setDescargando(null);
        }
    };

    const datosTab = tab === 'ventas' ? datosVentas : datosCompras;

    return (
        <div className="max-w-7xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                    <i className="fas fa-book-open text-emerald-600" />
                    Libro de Compras y Ventas
                </h1>
                <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Res. Ex. SII N°45/2003 — registro electrónico obligatorio para contribuyentes de IVA
                </p>
            </header>

            {mensaje && (
                <div className={`mb-4 px-4 py-3 rounded-lg text-sm font-medium flex items-center gap-2 ${
                    mensaje.tipo === 'exito'
                        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                        : 'bg-red-50 text-red-800 border border-red-200'
                }`}>
                    <i className={mensaje.tipo === 'exito' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'} />
                    {mensaje.texto}
                </div>
            )}

            {/* Selector de período */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-5 mb-6">
                <h2 className="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">
                    Período a consultar
                </h2>
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Mes</label>
                        <select
                            value={mes}
                            onChange={(e) => setMes(Number(e.target.value))}
                            className={selectCls}
                        >
                            {MESES.map((m) => (
                                <option key={m.value} value={m.value}>{m.label}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Año</label>
                        <select
                            value={anio}
                            onChange={(e) => setAnio(Number(e.target.value))}
                            className={selectCls}
                        >
                            {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                        </select>
                    </div>
                    <button
                        onClick={consultar}
                        disabled={cargando || !puedeVer}
                        className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50"
                    >
                        {cargando
                            ? <><i className="fas fa-spinner fa-spin" /> Consultando...</>
                            : <><i className="fas fa-search" /> Consultar</>
                        }
                    </button>
                </div>
            </div>

            {/* Tabs y contenido */}
            {datosTab && (
                <>
                    {/* Tabs */}
                    <div className="flex gap-1 mb-4 p-1 bg-slate-100 dark:bg-slate-800 rounded-xl w-fit">
                        <button
                            onClick={() => setTab('ventas')}
                            className={`px-5 py-2 rounded-lg text-sm font-semibold transition-all ${
                                tab === 'ventas'
                                    ? 'bg-white dark:bg-slate-700 text-emerald-700 dark:text-emerald-400 shadow-sm'
                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'
                            }`}
                        >
                            <i className="fas fa-file-invoice mr-2" />
                            Libro de Ventas
                            {datosVentas && (
                                <span className="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">
                                    {datosVentas.totales?.cantidad ?? 0}
                                </span>
                            )}
                        </button>
                        <button
                            onClick={() => setTab('compras')}
                            className={`px-5 py-2 rounded-lg text-sm font-semibold transition-all ${
                                tab === 'compras'
                                    ? 'bg-white dark:bg-slate-700 text-emerald-700 dark:text-emerald-400 shadow-sm'
                                    : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'
                            }`}
                        >
                            <i className="fas fa-shopping-cart mr-2" />
                            Libro de Compras
                            {datosCompras && (
                                <span className="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">
                                    {datosCompras.totales?.cantidad ?? 0}
                                </span>
                            )}
                        </button>
                    </div>

                    {/* Tabla */}
                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                        {/* Cabecera con exportaciones */}
                        <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <span className="font-bold text-slate-900 dark:text-slate-100">
                                    {tab === 'ventas' ? 'Libro de Ventas' : 'Libro de Compras'}
                                </span>
                                <span className="ml-2 text-sm text-slate-500 dark:text-slate-400">
                                    — {datosTab.periodo}
                                </span>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => handleDescargar(tab, 'csv')}
                                    disabled={!!descargando}
                                    title="Descargar CSV (separado por punto y coma, formato SII)"
                                    className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm font-semibold disabled:opacity-50"
                                >
                                    {descargando === `${tab}-csv`
                                        ? <i className="fas fa-spinner fa-spin" />
                                        : <i className="fas fa-download" />
                                    }
                                    CSV (formato SII)
                                </button>
                                <button
                                    onClick={() => handleDescargar(tab, 'excel')}
                                    disabled={!!descargando}
                                    title="Descargar Excel"
                                    className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 text-sm font-semibold disabled:opacity-50"
                                >
                                    {descargando === `${tab}-excel`
                                        ? <i className="fas fa-spinner fa-spin" />
                                        : <i className="fas fa-file-excel" />
                                    }
                                    Excel
                                </button>
                            </div>
                        </div>

                        {datosTab.lineas?.length === 0 ? (
                            <div className="py-16 text-center">
                                <i className="fas fa-inbox text-4xl text-slate-300 dark:text-slate-600 mb-3 block" />
                                <p className="text-slate-500 dark:text-slate-400 text-sm font-medium">
                                    Sin documentos en {MESES.find(m => m.value === mes)?.label} {anio}
                                </p>
                            </div>
                        ) : tab === 'ventas' ? (
                            <TablaVentas
                                lineas={datosVentas?.lineas ?? []}
                                totales={datosVentas?.totales}
                            />
                        ) : (
                            <TablaCompras
                                lineas={datosCompras?.lineas ?? []}
                                totales={datosCompras?.totales}
                            />
                        )}
                    </div>

                    {/* Nota informativa */}
                    <div className="mt-4 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800 flex gap-2">
                        <i className="fas fa-info-circle mt-0.5 shrink-0" />
                        <span>
                            El Libro de Ventas se genera desde los DTEs emitidos (excluye borradores y rechazados).
                            El Libro de Compras incluye facturas nacionales; los documentos del exterior se declaran
                            en Formulario 29 según Art. 59 LIR.
                        </span>
                    </div>
                </>
            )}

            {!datosTab && !cargando && (
                <div className="bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-300 dark:border-slate-600 rounded-2xl py-16 text-center text-slate-400">
                    <i className="fas fa-book-open text-4xl mb-3 block" />
                    <p className="font-medium">Selecciona un período y presiona Consultar</p>
                </div>
            )}
        </div>
    );
};

export default LibroComprasVentas;
