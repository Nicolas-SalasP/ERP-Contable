import React, { useState, useCallback } from 'react';
import { usePermisos } from '../../../Contextos/Permisos';
import AyudaModulo from '../../../Componentes/AyudaModulo.jsx';
import rrhhApi from '../Servicios/rrhhApi';
import { MESES, nombreMes, formatFecha } from '../Utilidades/formato';

const anioActual = new Date().getFullYear();
const ANIOS = Array.from({ length: 6 }, (_, i) => anioActual - i);

const inputCls =
    'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const BADGE_ESTADO = {
    GENERADO:          'bg-slate-100 text-slate-700',
    VALIDADO:          'bg-emerald-100 text-emerald-700',
    CONFIRMADO_DT:     'bg-blue-100 text-blue-700',
    ERROR_VALIDACION:  'bg-red-100 text-red-700',
};

const BadgeEstado = ({ estado }) => (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${BADGE_ESTADO[estado] ?? 'bg-slate-100 text-slate-700'}`}>
        {estado?.replace('_', ' ')}
    </span>
);

const LreRrhh = () => {
    const { tienePermiso } = usePermisos();
    const puedeVer       = tienePermiso('rrhh.remuneraciones.ver');
    const puedeProcesar  = tienePermiso('rrhh.remuneraciones.procesar');

    const [periodo, setPeriodo]             = useState({ anio: anioActual, mes: new Date().getMonth() + 1 });
    const [lreActual, setLreActual]         = useState(null);
    const [historial, setHistorial]         = useState([]);
    const [erroresValidacion, setErrores]   = useState([]);
    const [numConfirmacion, setNumConf]     = useState('');
    const [cargando, setCargando]           = useState(false);
    const [validando, setValidando]         = useState(false);
    const [confirmando, setConfirmando]     = useState(false);
    const [descargandoId, setDescargandoId] = useState(null);
    const [mensaje, setMensaje]             = useState(null); // { tipo: 'exito'|'error', texto }
    const [historialCargado, setHistCargado] = useState(false);

    const mostrarMensaje = (tipo, texto) => {
        setMensaje({ tipo, texto });
        setTimeout(() => setMensaje(null), 5000);
    };

    const cargarHistorial = useCallback(async () => {
        if (! puedeVer) return;
        try {
            const resp = await rrhhApi.lre.listar();
            const items = Array.isArray(resp) ? resp : (resp?.data ?? []);
            setHistorial(items);
            setHistCargado(true);

            // Buscar el LRE del período seleccionado
            const actual = items.find(
                (l) => l.anio === periodo.anio && l.mes === periodo.mes
            );
            setLreActual(actual ?? null);
        } catch (_) {
            // el interceptor global muestra el toast
        }
    }, [puedeVer, periodo.anio, periodo.mes]);

    // Carga historial al montar el componente
    React.useEffect(() => {
        cargarHistorial();
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Actualiza el LRE actual cuando cambia el período
    React.useEffect(() => {
        const actual = historial.find(
            (l) => l.anio === periodo.anio && l.mes === periodo.mes
        );
        setLreActual(actual ?? null);
        setErrores([]);
    }, [periodo, historial]);

    const handleGenerar = async () => {
        if (! puedeProcesar) return;
        setCargando(true);
        setErrores([]);
        try {
            const resp = await rrhhApi.lre.generar(periodo.anio, periodo.mes);
            const lre = resp?.data ?? resp;
            setLreActual(lre);
            // Actualizar historial
            setHistorial((prev) => {
                const sinActual = prev.filter((l) => !(l.anio === lre.anio && l.mes === lre.mes));
                return [lre, ...sinActual].sort((a, b) => b.anio - a.anio || b.mes - a.mes);
            });
            mostrarMensaje('exito', `LRE ${nombreMes(periodo.mes)} ${periodo.anio} generado con ${lre.cantidad_trabajadores} trabajador(es).`);
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al generar el LRE.';
            mostrarMensaje('error', msg);
        } finally {
            setCargando(false);
        }
    };

    const handleValidar = async () => {
        if (! lreActual || ! puedeProcesar) return;
        setValidando(true);
        setErrores([]);
        try {
            const resp = await rrhhApi.lre.validar(lreActual.id);
            const data = resp?.data ?? resp;
            setLreActual(data?.lre ?? data);
            setHistorial((prev) =>
                prev.map((l) => (l.id === lreActual.id ? (data?.lre ?? data) : l))
            );
            mostrarMensaje('exito', 'LRE validado correctamente. Sin errores.');
        } catch (err) {
            const data = err?.response?.data;
            if (data?.errores && Array.isArray(data.errores)) {
                setErrores(data.errores);
                setLreActual(data?.lre ?? lreActual);
                setHistorial((prev) =>
                    prev.map((l) => (l.id === lreActual.id ? (data?.lre ?? l) : l))
                );
                mostrarMensaje('error', `Validación fallida: ${data.errores.length} error(es) encontrado(s).`);
            } else {
                const msg = data?.mensaje ?? data?.message ?? 'Error al validar el LRE.';
                mostrarMensaje('error', msg);
            }
        } finally {
            setValidando(false);
        }
    };

    const handleConfirmarDt = async () => {
        if (! lreActual || ! puedeProcesar) return;
        setConfirmando(true);
        try {
            const resp = await rrhhApi.lre.confirmarDt(lreActual.id, numConfirmacion || null);
            const lre = resp?.data ?? resp;
            setLreActual(lre);
            setHistorial((prev) => prev.map((l) => (l.id === lre.id ? lre : l)));
            setNumConf('');
            mostrarMensaje('exito', 'Envío a Mi DT confirmado correctamente.');
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al confirmar el envío.';
            mostrarMensaje('error', msg);
        } finally {
            setConfirmando(false);
        }
    };

    const handleDescargar = async (lre) => {
        setDescargandoId(lre.id);
        try {
            await rrhhApi.lre.descargar(lre.id, lre.anio, lre.mes);
        } catch (_) {
            // toast global
        } finally {
            setDescargandoId(null);
        }
    };

    return (
        <div className="max-w-5xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                    <i className="fas fa-book text-emerald-600" />
                    Libro de Remuneraciones Electrónico (LRE)
                    <AyudaModulo moduloId="lre" />
                </h1>
                <p className="text-sm text-slate-500 mt-1">
                    Genera, valida y descarga el LRE mensual para subir al portal Mi DT del Ministerio del Trabajo.
                </p>
            </header>

            {/* Mensaje de estado */}
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

            {/* Selector de período y acción generar */}
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
                <h2 className="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">Período</h2>
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Año</label>
                        <select
                            value={periodo.anio}
                            onChange={(e) => setPeriodo((p) => ({ ...p, anio: Number(e.target.value) }))}
                            className={inputCls}
                        >
                            {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Mes</label>
                        <select
                            value={periodo.mes}
                            onChange={(e) => setPeriodo((p) => ({ ...p, mes: Number(e.target.value) }))}
                            className={inputCls}
                        >
                            {MESES.map((m) => <option key={m.valor} value={m.valor}>{m.label}</option>)}
                        </select>
                    </div>
                    {puedeProcesar && (
                        <button
                            onClick={handleGenerar}
                            disabled={cargando}
                            className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50"
                        >
                            {cargando
                                ? <><i className="fas fa-spinner fa-spin" /> Generando...</>
                                : <><i className="fas fa-cogs" /> Generar LRE</>
                            }
                        </button>
                    )}
                </div>
            </div>

            {/* Panel del LRE del período actual */}
            {lreActual ? (
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="font-bold text-slate-900">
                            LRE — {nombreMes(lreActual.mes)} {lreActual.anio}
                        </h2>
                        <BadgeEstado estado={lreActual.estado} />
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-sm">
                        <div className="bg-slate-50 rounded-lg p-3">
                            <div className="text-xs text-slate-500 mb-1">Trabajadores</div>
                            <div className="font-bold text-slate-900">{lreActual.cantidad_trabajadores ?? '—'}</div>
                        </div>
                        <div className="bg-slate-50 rounded-lg p-3">
                            <div className="text-xs text-slate-500 mb-1">Estado</div>
                            <div className="font-bold text-slate-900">{lreActual.estado}</div>
                        </div>
                        <div className="bg-slate-50 rounded-lg p-3">
                            <div className="text-xs text-slate-500 mb-1">Confirmación DT</div>
                            <div className="font-bold text-slate-900">{lreActual.numero_confirmacion_dt ?? '—'}</div>
                        </div>
                        <div className="bg-slate-50 rounded-lg p-3">
                            <div className="text-xs text-slate-500 mb-1">Fecha confirmación</div>
                            <div className="font-bold text-slate-900">{formatFecha(lreActual.confirmado_at)}</div>
                        </div>
                    </div>

                    {/* Acciones */}
                    <div className="flex flex-wrap gap-2">
                        {puedeProcesar && lreActual.estado !== 'CONFIRMADO_DT' && (
                            <button
                                onClick={handleValidar}
                                disabled={validando}
                                className="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-emerald-600 text-emerald-700 hover:bg-emerald-50 font-bold text-sm disabled:opacity-50"
                            >
                                {validando
                                    ? <><i className="fas fa-spinner fa-spin" /> Validando...</>
                                    : <><i className="fas fa-check-double" /> Validar</>
                                }
                            </button>
                        )}

                        <button
                            onClick={() => handleDescargar(lreActual)}
                            disabled={descargandoId === lreActual.id}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold text-sm disabled:opacity-50"
                        >
                            {descargandoId === lreActual.id
                                ? <><i className="fas fa-spinner fa-spin" /> Descargando...</>
                                : <><i className="fas fa-download" /> Descargar archivo</>
                            }
                        </button>
                    </div>

                    {/* Errores de validación */}
                    {erroresValidacion.length > 0 && (
                        <div className="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
                            <h3 className="text-sm font-bold text-red-800 mb-2 flex items-center gap-2">
                                <i className="fas fa-times-circle" />
                                Errores de validación ({erroresValidacion.length})
                            </h3>
                            <ul className="space-y-1">
                                {erroresValidacion.map((err, i) => (
                                    <li key={i} className="text-sm text-red-700 flex gap-2">
                                        <span className="text-red-400 mt-0.5">•</span>
                                        {err}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Formulario confirmar envío a DT */}
                    {puedeProcesar && lreActual.estado === 'VALIDADO' && (
                        <div className="mt-4 pt-4 border-t border-slate-200">
                            <h3 className="text-sm font-bold text-slate-700 mb-3">
                                Confirmar envío a Mi DT
                            </h3>
                            <p className="text-xs text-slate-500 mb-3">
                                Una vez subido el archivo al portal Mi DT del Ministerio del Trabajo, ingresa el número de confirmación que entrega el portal (opcional).
                            </p>
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="flex-1 min-w-[200px]">
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Número de confirmación (opcional)
                                    </label>
                                    <input
                                        type="text"
                                        value={numConfirmacion}
                                        onChange={(e) => setNumConf(e.target.value)}
                                        maxLength={100}
                                        placeholder="Ej: DT-2024-001234"
                                        className={inputCls}
                                    />
                                </div>
                                <button
                                    onClick={handleConfirmarDt}
                                    disabled={confirmando}
                                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm disabled:opacity-50"
                                >
                                    {confirmando
                                        ? <><i className="fas fa-spinner fa-spin" /> Confirmando...</>
                                        : <><i className="fas fa-stamp" /> Confirmar</>
                                    }
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                <div className="bg-slate-50 border border-dashed border-slate-300 rounded-2xl py-10 text-center text-slate-400 mb-6">
                    <i className="fas fa-book text-3xl mb-3 block" />
                    No hay LRE generado para {nombreMes(periodo.mes)} {periodo.anio}.
                    {puedeProcesar && <span className="block text-sm mt-1">Usa el botón "Generar LRE" para crear uno.</span>}
                </div>
            )}

            {/* Historial */}
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div className="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 className="font-bold text-slate-900">Historial de LRE</h2>
                    <button
                        onClick={cargarHistorial}
                        className="text-xs text-emerald-600 hover:text-emerald-800 font-semibold flex items-center gap-1"
                    >
                        <i className="fas fa-sync-alt" /> Actualizar
                    </button>
                </div>

                {historial.length === 0 ? (
                    <div className="py-10 text-center text-slate-400 text-sm">
                        {historialCargado ? 'Sin registros LRE aún.' : 'Cargando...'}
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-slate-600 text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">Período</th>
                                    <th className="px-4 py-3 text-left font-semibold">Estado</th>
                                    <th className="px-4 py-3 text-right font-semibold">Trabajadores</th>
                                    <th className="px-4 py-3 text-left font-semibold">N° Confirmación DT</th>
                                    <th className="px-4 py-3 text-left font-semibold">Confirmado</th>
                                    <th className="px-4 py-3 text-center font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {historial.map((lre) => (
                                    <tr key={lre.id} className="hover:bg-slate-50">
                                        <td className="px-4 py-3 font-medium text-slate-900">
                                            {nombreMes(lre.mes)} {lre.anio}
                                        </td>
                                        <td className="px-4 py-3">
                                            <BadgeEstado estado={lre.estado} />
                                        </td>
                                        <td className="px-4 py-3 text-right text-slate-700">
                                            {lre.cantidad_trabajadores ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700 font-mono text-xs">
                                            {lre.numero_confirmacion_dt ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {formatFecha(lre.confirmado_at)}
                                        </td>
                                        <td className="px-4 py-3 text-center">
                                            <button
                                                onClick={() => handleDescargar(lre)}
                                                disabled={descargandoId === lre.id}
                                                title="Descargar archivo"
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-600 hover:bg-slate-100 disabled:opacity-50"
                                            >
                                                {descargandoId === lre.id
                                                    ? <i className="fas fa-spinner fa-spin text-xs" />
                                                    : <i className="fas fa-download text-xs" />
                                                }
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
};

export default LreRrhh;
