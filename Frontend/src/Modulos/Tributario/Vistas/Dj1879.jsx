import React, { useState, useCallback } from 'react';
import { usePermisos } from '../../../Contextos/Permisos';
import { dj1879 } from '../Servicios/tributarioApi';

const anioActual = new Date().getFullYear();
const ANIOS = Array.from({ length: anioActual - 2019 }, (_, i) => anioActual - i);

const inputCls =
    'w-full px-3 py-2 rounded-lg border border-slate-300 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const BADGE_ESTADO = {
    GENERADO:    'bg-slate-100 text-slate-700',
    VALIDADO:    'bg-emerald-100 text-emerald-700',
    CON_ERRORES: 'bg-red-100 text-red-700',
    DESCARGADO:  'bg-blue-100 text-blue-700',
    PRESENTADO:  'bg-emerald-200 text-emerald-900',
};

const BadgeEstado = ({ estado }) => (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${BADGE_ESTADO[estado] ?? 'bg-slate-100 text-slate-700'}`}>
        {estado?.replace(/_/g, ' ')}
    </span>
);

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-CL');
};

const Dj1879 = () => {
    const { tienePermiso } = usePermisos();
    const puedeVer      = tienePermiso('tributario.ver');
    const puedeProcesar = tienePermiso('tributario.ver');

    const [anio, setAnio]                     = useState(anioActual - 1);
    const [envios, setEnvios]                 = useState([]);
    const [cargando, setCargando]             = useState(false);
    const [folioConfirmacion, setFolioConf]   = useState('');
    const [validando, setValidando]           = useState(false);
    const [confirmando, setConfirmando]       = useState(false);
    const [descargandoId, setDescargandoId]   = useState(null);
    const [mensaje, setMensaje]               = useState(null);
    const [historialCargado, setHistCargado]  = useState(false);
    const [envioSeleccionado, setSeleccionado] = useState(null);

    const mostrarMensaje = (tipo, texto) => {
        setMensaje({ tipo, texto });
        setTimeout(() => setMensaje(null), 5000);
    };

    const cargarHistorial = useCallback(async () => {
        if (!puedeVer) return;
        try {
            const resp = await dj1879.listar();
            const items = Array.isArray(resp) ? resp : (resp?.data ?? []);
            setEnvios(items);
            setHistCargado(true);
            if (!envioSeleccionado && items.length > 0) {
                setSeleccionado(items[0]);
            }
        } catch (_) {
            // el interceptor global muestra el toast
        }
    }, [puedeVer, envioSeleccionado]);

    React.useEffect(() => {
        cargarHistorial();
    }, []);

    const envioActivo = envioSeleccionado ?? (envios.length > 0 ? envios[0] : null);

    const handleGenerar = async () => {
        if (!puedeProcesar) return;
        setCargando(true);
        try {
            const resp = await dj1879.generar(anio);
            const nuevo = resp?.data ?? resp;
            setEnvios((prev) => {
                const sinDup = prev.filter((e) => e.id !== nuevo.id);
                return [nuevo, ...sinDup].sort((a, b) => b.anio - a.anio);
            });
            setSeleccionado(nuevo);
            mostrarMensaje('exito', `DJ 1879 año ${anio} generada con ${nuevo.cantidad_registros ?? 0} registro(s).`);
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al generar la DJ 1879.';
            mostrarMensaje('error', msg);
        } finally {
            setCargando(false);
        }
    };

    const handleValidar = async () => {
        if (!envioActivo || !puedeProcesar) return;
        setValidando(true);
        try {
            const resp = await dj1879.validar(envioActivo.id);
            const data = resp?.data ?? resp;
            const actualizado = data?.envio ?? data;
            setEnvios((prev) => prev.map((e) => (e.id === envioActivo.id ? actualizado : e)));
            setSeleccionado(actualizado);
            mostrarMensaje('exito', 'DJ 1879 validada correctamente. Sin errores.');
        } catch (err) {
            const data = err?.response?.data;
            const msg = data?.mensaje ?? data?.message ?? 'Error al validar la DJ 1879.';
            mostrarMensaje('error', msg);
        } finally {
            setValidando(false);
        }
    };

    const handleDescargar = async (envioId, anioEnvio) => {
        setDescargandoId(envioId);
        try {
            const blob = await dj1879.descargar(envioId);
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `DJ1879_${anioEnvio}.txt`;
            a.click();
            URL.revokeObjectURL(url);
            await cargarHistorial();
        } catch (_) {
            // toast global
        } finally {
            setDescargandoId(null);
        }
    };

    const handleConfirmarPresentacion = async () => {
        if (!envioActivo || !puedeProcesar) return;
        setConfirmando(true);
        try {
            const resp = await dj1879.confirmarPresentacion(envioActivo.id, folioConfirmacion || null);
            const actualizado = resp?.data ?? resp;
            setEnvios((prev) => prev.map((e) => (e.id === envioActivo.id ? actualizado : e)));
            setSeleccionado(actualizado);
            setFolioConf('');
            mostrarMensaje('exito', 'Presentación ante el SII confirmada correctamente.');
        } catch (err) {
            const msg = err?.response?.data?.mensaje ?? err?.response?.data?.message ?? 'Error al confirmar la presentación.';
            mostrarMensaje('error', msg);
        } finally {
            setConfirmando(false);
        }
    };

    return (
        <div className="max-w-5xl mx-auto p-6 md:p-8">
            <header className="mb-6">
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                    <i className="fas fa-file-alt text-emerald-600" />
                    DJ 1879 — Retenciones de Honorarios
                </h1>
                <p className="text-sm text-slate-500 mt-1">
                    Declaración Jurada anual de retenciones segunda categoría (art. 74 N°2 LIR)
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

            {/* Formulario de generación */}
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
                <h2 className="text-sm font-bold text-slate-700 mb-3 uppercase tracking-wide">Generar DJ 1879</h2>
                <div className="flex flex-wrap items-end gap-3">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 mb-1">Año tributario</label>
                        <select
                            value={anio}
                            onChange={(e) => setAnio(Number(e.target.value))}
                            className={inputCls}
                        >
                            {ANIOS.map((a) => <option key={a} value={a}>{a}</option>)}
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
                                : <><i className="fas fa-cogs" /> Generar DJ 1879</>
                            }
                        </button>
                    )}
                </div>
            </div>

            {/* Nota informativa */}
            <div className="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-6 text-sm text-amber-800 flex gap-2">
                <i className="fas fa-info-circle mt-0.5 shrink-0" />
                <span>
                    Declara las retenciones de honorarios enteradas durante el año.
                    El archivo debe subirse al portal de DJ Renta del SII con Clave Tributaria.
                </span>
            </div>

            {/* Panel de acciones del envío activo */}
            {envioActivo ? (
                <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="font-bold text-slate-900">
                            DJ 1879 — Año {envioActivo.anio}
                        </h2>
                        <BadgeEstado estado={envioActivo.estado} />
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4 text-sm">
                        <div className="bg-slate-50 rounded-lg p-3">
                            <div className="text-xs text-slate-500 mb-1">Registros</div>
                            <div className="font-bold text-slate-900">{envioActivo.cantidad_registros ?? '—'}</div>
                        </div>
                        <div className="bg-slate-50 rounded-lg p-3">
                            <div className="text-xs text-slate-500 mb-1">Folio SII</div>
                            <div className="font-bold text-slate-900 font-mono text-xs">{envioActivo.folio_presentacion ?? '—'}</div>
                        </div>
                        <div className="bg-slate-50 rounded-lg p-3">
                            <div className="text-xs text-slate-500 mb-1">Presentado el</div>
                            <div className="font-bold text-slate-900">{formatFecha(envioActivo.presentado_at)}</div>
                        </div>
                    </div>

                    {/* Acciones */}
                    <div className="flex flex-wrap gap-2 mb-4">
                        {puedeProcesar && envioActivo.estado !== 'PRESENTADO' && (
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
                            onClick={() => handleDescargar(envioActivo.id, envioActivo.anio)}
                            disabled={descargandoId === envioActivo.id}
                            className="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 font-bold text-sm disabled:opacity-50"
                        >
                            {descargandoId === envioActivo.id
                                ? <><i className="fas fa-spinner fa-spin" /> Descargando...</>
                                : <><i className="fas fa-download" /> Descargar .txt</>
                            }
                        </button>
                    </div>

                    {/* Confirmar presentación ante SII */}
                    {puedeProcesar && envioActivo.estado !== 'PRESENTADO' && (
                        <div className="pt-4 border-t border-slate-200">
                            <h3 className="text-sm font-bold text-slate-700 mb-2">
                                Confirmar presentación ante el SII
                            </h3>
                            <p className="text-xs text-slate-500 mb-3">
                                Una vez subido el archivo al portal de Declaraciones Juradas del SII, ingresa el folio de presentación asignado (opcional).
                            </p>
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="flex-1 min-w-[200px]">
                                    <label className="block text-xs font-semibold text-slate-600 mb-1">
                                        Folio de presentación SII (opcional)
                                    </label>
                                    <input
                                        type="text"
                                        value={folioConfirmacion}
                                        onChange={(e) => setFolioConf(e.target.value)}
                                        maxLength={100}
                                        placeholder="Ej: 2024-DJ1879-000123"
                                        className={inputCls}
                                    />
                                </div>
                                <button
                                    onClick={handleConfirmarPresentacion}
                                    disabled={confirmando}
                                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-sm disabled:opacity-50"
                                >
                                    {confirmando
                                        ? <><i className="fas fa-spinner fa-spin" /> Confirmando...</>
                                        : <><i className="fas fa-stamp" /> Confirmar presentada</>
                                    }
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            ) : (
                <div className="bg-slate-50 border border-dashed border-slate-300 rounded-2xl py-10 text-center text-slate-400 mb-6">
                    <i className="fas fa-file-alt text-3xl mb-3 block" />
                    No hay declaraciones DJ 1879 generadas.
                    {puedeProcesar && <span className="block text-sm mt-1">Usa el formulario de arriba para generar una.</span>}
                </div>
            )}

            {/* Tabla historial */}
            <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div className="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h2 className="font-bold text-slate-900">Historial de declaraciones</h2>
                    <button
                        onClick={cargarHistorial}
                        className="text-xs text-emerald-600 hover:text-emerald-800 font-semibold flex items-center gap-1"
                    >
                        <i className="fas fa-sync-alt" /> Actualizar
                    </button>
                </div>

                {envios.length === 0 ? (
                    <div className="py-10 text-center text-slate-400 text-sm">
                        {historialCargado ? 'Sin declaraciones DJ 1879 aún.' : 'Cargando...'}
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 text-slate-600 text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold">Año</th>
                                    <th className="px-4 py-3 text-left font-semibold">Estado</th>
                                    <th className="px-4 py-3 text-right font-semibold">Registros</th>
                                    <th className="px-4 py-3 text-left font-semibold">Folio</th>
                                    <th className="px-4 py-3 text-left font-semibold">Presentado el</th>
                                    <th className="px-4 py-3 text-center font-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {envios.map((envio) => (
                                    <tr
                                        key={envio.id}
                                        className={`hover:bg-slate-50 cursor-pointer ${envioActivo?.id === envio.id ? 'bg-emerald-50' : ''}`}
                                        onClick={() => setSeleccionado(envio)}
                                    >
                                        <td className="px-4 py-3 font-medium text-slate-900">{envio.anio}</td>
                                        <td className="px-4 py-3">
                                            <BadgeEstado estado={envio.estado} />
                                        </td>
                                        <td className="px-4 py-3 text-right text-slate-700">
                                            {envio.cantidad_registros ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700 font-mono text-xs">
                                            {envio.folio_presentacion ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">
                                            {formatFecha(envio.presentado_at)}
                                        </td>
                                        <td className="px-4 py-3 text-center" onClick={(e) => e.stopPropagation()}>
                                            <button
                                                onClick={() => handleDescargar(envio.id, envio.anio)}
                                                disabled={descargandoId === envio.id}
                                                title="Descargar archivo .txt"
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-600 hover:bg-slate-100 disabled:opacity-50"
                                            >
                                                {descargandoId === envio.id
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

export default Dj1879;
