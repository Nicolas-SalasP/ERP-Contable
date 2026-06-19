import React, { useCallback, useEffect, useState } from 'react';
import EstadoCarga from '../../Componentes/EstadoCarga';
import { usePermisos } from '../../Contextos/Permisos';
import cumplimientoApi from './cumplimientoApi';

// ── Constantes ────────────────────────────────────────────────────────────────

const OPERACIONES = ['CREAR', 'ACTUALIZAR', 'ELIMINAR', 'LECTURA'];
const TIPOS_AUDITORIA = [
    { value: '', label: 'Todos los tipos' },
    { value: 'empleado', label: 'Empleado' },
    { value: 'contrato', label: 'Contrato' },
    { value: 'liquidacion', label: 'Liquidación' },
    { value: 'carga_familiar', label: 'Carga familiar' },
    { value: 'cuenta_bancaria_empresa', label: 'Cuenta bancaria empresa' },
    { value: 'cuenta_bancaria_proveedor', label: 'Cuenta bancaria proveedor' },
];
const SEVERIDADES = ['BAJA', 'MEDIA', 'ALTA', 'CRITICA'];

const SEVERIDAD_ESTILOS = {
    BAJA:    'bg-slate-100 text-slate-700',
    MEDIA:   'bg-amber-100 text-amber-800',
    ALTA:    'bg-orange-100 text-orange-800',
    CRITICA: 'bg-rose-100 text-rose-800 font-bold',
};

const ESTADO_ESTILOS = {
    ABIERTO:   'bg-rose-100 text-rose-700',
    CONTENIDO: 'bg-amber-100 text-amber-700',
    CERRADO:   'bg-emerald-100 text-emerald-700',
};

const OPERACION_ESTILOS = {
    CREAR:      'bg-emerald-100 text-emerald-700',
    ACTUALIZAR: 'bg-blue-100 text-blue-700',
    ELIMINAR:   'bg-rose-100 text-rose-700',
    LECTURA:    'bg-slate-100 text-slate-600',
};

const fmtFecha = (iso) => {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' });
    } catch {
        return iso;
    }
};

const tipoCorto = (fqcn) => (fqcn ? String(fqcn).split('\\').pop() : '—');

// ── Hito de la línea de tiempo legal ─────────────────────────────────────────

const HitoTimeline = ({ label, plazo, valor, onRegistrar }) => {
    const cumplido = Boolean(valor);
    return (
        <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-slate-100 dark:border-slate-700 last:border-0">
            <span
                className={`w-5 h-5 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold ${
                    cumplido ? 'bg-emerald-500 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-400'
                }`}
                aria-hidden="true"
            >
                {cumplido ? '✓' : '○'}
            </span>
            <div className="flex-1 min-w-0">
                <p className="text-xs font-semibold text-slate-700 dark:text-slate-300">{label}</p>
                <p className="text-xs text-slate-400">{plazo}</p>
            </div>
            <div className="text-xs text-slate-600 dark:text-slate-400 shrink-0">
                {cumplido ? (
                    <span className="text-emerald-700 font-medium">{fmtFecha(valor)}</span>
                ) : (
                    <button
                        type="button"
                        onClick={onRegistrar}
                        className="text-emerald-600 hover:text-emerald-800 underline text-xs"
                    >
                        Registrar ahora
                    </button>
                )}
            </div>
        </div>
    );
};

// ── Tarjeta de incidente ──────────────────────────────────────────────────────

const TarjetaIncidente = ({ incidente, onActualizar }) => {
    const [expandido, setExpandido] = useState(false);
    const [actualizando, setActualizando] = useState(false);

    const registrarHito = async (campo) => {
        setActualizando(true);
        try {
            await cumplimientoApi.incidentes.actualizar(incidente.id, {
                [campo]: new Date().toISOString(),
            });
            onActualizar?.();
        } catch {
            /* api.js ya mostró el toast */
        } finally {
            setActualizando(false);
        }
    };

    const avanzarEstado = async () => {
        const siguientes = { ABIERTO: 'CONTENIDO', CONTENIDO: 'CERRADO' };
        const siguiente = siguientes[incidente.estado];
        if (!siguiente) return;
        setActualizando(true);
        try {
            await cumplimientoApi.incidentes.actualizar(incidente.id, { estado: siguiente });
            onActualizar?.();
        } catch {
            /* idem */
        } finally {
            setActualizando(false);
        }
    };

    return (
        <div className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm overflow-hidden">
            {/* Cabecera (siempre visible) */}
            <button
                type="button"
                aria-expanded={expandido}
                onClick={() => setExpandido((v) => !v)}
                className="w-full flex flex-col sm:flex-row sm:items-center gap-2 px-4 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
            >
                <div className="flex items-center gap-2 flex-1 min-w-0">
                    <span className={`text-xs px-2 py-0.5 rounded-full shrink-0 ${SEVERIDAD_ESTILOS[incidente.severidad] ?? 'bg-slate-100 text-slate-700'}`}>
                        {incidente.severidad}
                    </span>
                    <span className="font-semibold text-sm text-slate-800 dark:text-slate-200 truncate">{incidente.titulo}</span>
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <span className={`text-xs px-2 py-0.5 rounded-full ${ESTADO_ESTILOS[incidente.estado] ?? 'bg-slate-100 text-slate-700'}`}>
                        {incidente.estado}
                    </span>
                    <span className="text-slate-400 text-xs hidden sm:inline">{fmtFecha(incidente.detectado_at)}</span>
                    <i className={`fas fa-chevron-down text-slate-400 text-xs transition-transform ${expandido ? 'rotate-180' : ''}`} aria-hidden="true" />
                </div>
            </button>

            {/* Detalle expandible */}
            {expandido && (
                <div className="border-t border-slate-100 dark:border-slate-700 px-4 py-4 space-y-4">
                    <p className="text-sm text-slate-700 dark:text-slate-300">{incidente.descripcion}</p>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-slate-600 dark:text-slate-400">
                        {incidente.origen && (
                            <div><span className="font-semibold">Origen:</span> {incidente.origen}</div>
                        )}
                        {incidente.n_afectados_estimado != null && (
                            <div>
                                <span className="font-semibold">Afectados est.:</span>{' '}
                                {Number(incidente.n_afectados_estimado).toLocaleString('es-CL')}
                            </div>
                        )}
                        {incidente.categorias_datos_afectados && (
                            <div><span className="font-semibold">Datos:</span> {incidente.categorias_datos_afectados}</div>
                        )}
                        {incidente.registrado_por && (
                            <div><span className="font-semibold">Registrado por:</span> {incidente.registrado_por}</div>
                        )}
                    </div>

                    {/* Línea de tiempo legal 3h / 72h */}
                    <div className="bg-slate-50 dark:bg-slate-900 rounded-lg p-3">
                        <p className="text-xs font-bold text-slate-700 dark:text-slate-300 mb-2 uppercase tracking-wide">
                            Línea de tiempo legal
                        </p>
                        <HitoTimeline
                            label="Alerta temprana (CSIRT)"
                            plazo="Plazo: 3 h desde detección — Art. 8 Ley 21.663"
                            valor={incidente.alerta_temprana_at}
                            onRegistrar={() => registrarHito('alerta_temprana_at')}
                        />
                        <HitoTimeline
                            label="Reporte completo CSIRT"
                            plazo="Plazo: 72 h desde detección — Art. 9 Ley 21.663"
                            valor={incidente.reporte_csirt_at}
                            onRegistrar={() => registrarHito('reporte_csirt_at')}
                        />
                        <HitoTimeline
                            label="Notificación a la Agencia"
                            plazo="Plazo: 72 h desde conocimiento — Art. 49 Ley 21.719"
                            valor={incidente.notificacion_agencia_at}
                            onRegistrar={() => registrarHito('notificacion_agencia_at')}
                        />
                        <HitoTimeline
                            label="Notificación a titulares afectados"
                            plazo="Lo antes posible — Art. 50 Ley 21.719"
                            valor={incidente.notificacion_afectados_at}
                            onRegistrar={() => registrarHito('notificacion_afectados_at')}
                        />
                    </div>

                    {/* Botón avanzar estado */}
                    {incidente.estado !== 'CERRADO' && (
                        <div className="flex justify-end">
                            <button
                                type="button"
                                disabled={actualizando}
                                onClick={avanzarEstado}
                                className="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-xs font-bold rounded-lg transition-colors"
                            >
                                {actualizando
                                    ? 'Actualizando…'
                                    : incidente.estado === 'ABIERTO'
                                        ? 'Marcar como CONTENIDO'
                                        : 'Marcar como CERRADO'}
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};

// ── Formulario nuevo incidente ────────────────────────────────────────────────

const FORM_VACIO = {
    titulo: '',
    descripcion: '',
    severidad: 'MEDIA',
    detectado_at: '',
    origen: '',
    categorias_datos_afectados: '',
    n_afectados_estimado: '',
};

const FormularioIncidente = ({ onGuardado, onCancelar }) => {
    const [form, setForm] = useState(FORM_VACIO);
    const [guardando, setGuardando] = useState(false);

    const cambiar = (e) => {
        const { name, value } = e.target;
        setForm((prev) => ({ ...prev, [name]: value }));
    };

    const enviar = async (e) => {
        e.preventDefault();
        setGuardando(true);
        try {
            const payload = Object.fromEntries(
                Object.entries(form).filter(([, v]) => v !== '' && v !== null && v !== undefined)
            );
            if (payload.n_afectados_estimado) {
                payload.n_afectados_estimado = parseInt(payload.n_afectados_estimado, 10);
            }
            await cumplimientoApi.incidentes.crear(payload);
            setForm(FORM_VACIO);
            onGuardado?.();
        } catch {
            /* api.js ya notificó */
        } finally {
            setGuardando(false);
        }
    };

    const inputCls = 'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm focus:ring-2 focus:ring-emerald-500 outline-none bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200';

    return (
        <form
            onSubmit={enviar}
            className="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 sm:p-6 space-y-4 shadow-sm"
            aria-label="Registrar incidente de seguridad"
        >
            <h3 className="text-base font-bold text-slate-800 dark:text-slate-200">Registrar nuevo incidente</h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1" htmlFor="inc-titulo">
                        Título <span className="text-rose-500">*</span>
                    </label>
                    <input
                        id="inc-titulo"
                        name="titulo"
                        type="text"
                        required
                        value={form.titulo}
                        onChange={cambiar}
                        placeholder="Ej.: Acceso no autorizado a datos de empleados"
                        className={inputCls}
                        aria-label="Título"
                    />
                </div>

                <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1" htmlFor="inc-desc">
                        Descripción <span className="text-rose-500">*</span>
                    </label>
                    <textarea
                        id="inc-desc"
                        name="descripcion"
                        required
                        rows={3}
                        value={form.descripcion}
                        onChange={cambiar}
                        placeholder="Describe qué ocurrió, cómo se detectó y cuál fue el alcance inicial."
                        className={inputCls}
                        aria-label="Descripción"
                    />
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1" htmlFor="inc-severidad">
                        Severidad <span className="text-rose-500">*</span>
                    </label>
                    <select
                        id="inc-severidad"
                        name="severidad"
                        required
                        value={form.severidad}
                        onChange={cambiar}
                        className={inputCls}
                        aria-label="Severidad"
                    >
                        {SEVERIDADES.map((s) => (
                            <option key={s} value={s}>{s}</option>
                        ))}
                    </select>
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1" htmlFor="inc-detectado">
                        Fecha/hora detección <span className="text-rose-500">*</span>
                    </label>
                    <input
                        id="inc-detectado"
                        name="detectado_at"
                        type="datetime-local"
                        required
                        value={form.detectado_at}
                        onChange={cambiar}
                        className={inputCls}
                        aria-label="Detectado"
                    />
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1" htmlFor="inc-origen">
                        Origen / vector
                    </label>
                    <input
                        id="inc-origen"
                        name="origen"
                        type="text"
                        value={form.origen}
                        onChange={cambiar}
                        placeholder="Ej.: phishing, acceso interno"
                        className={inputCls}
                    />
                </div>

                <div>
                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1" htmlFor="inc-afectados">
                        N.° afectados estimado
                    </label>
                    <input
                        id="inc-afectados"
                        name="n_afectados_estimado"
                        type="number"
                        min="0"
                        value={form.n_afectados_estimado}
                        onChange={cambiar}
                        placeholder="0"
                        className={inputCls}
                        aria-label="Número de afectados"
                    />
                </div>

                <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1" htmlFor="inc-categorias">
                        Categorías de datos afectados
                    </label>
                    <input
                        id="inc-categorias"
                        name="categorias_datos_afectados"
                        type="text"
                        value={form.categorias_datos_afectados}
                        onChange={cambiar}
                        placeholder="Ej.: nombres, RUT, datos bancarios"
                        className={inputCls}
                        aria-label="Categorías de datos"
                    />
                </div>
            </div>

            <div className="flex flex-col sm:flex-row justify-end gap-2 pt-2">
                <button
                    type="button"
                    onClick={onCancelar}
                    className="px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-300 text-sm font-semibold rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 bg-white dark:bg-slate-800 transition-colors"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    disabled={guardando}
                    className="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white text-sm font-bold rounded-lg transition-colors"
                >
                    {guardando ? 'Guardando…' : 'Guardar incidente'}
                </button>
            </div>
        </form>
    );
};

// ── Tab Auditoría ─────────────────────────────────────────────────────────────

const TabAuditoria = () => {
    const [registros, setRegistros] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const [meta, setMeta] = useState(null);
    const [filtros, setFiltros] = useState({
        operacion: '',
        auditable_type: '',
        desde: '',
        hasta: '',
        page: 1,
    });

    const cargar = useCallback(async () => {
        setCargando(true);
        setError(null);
        try {
            const params = Object.fromEntries(
                Object.entries(filtros).filter(([, v]) => v !== '' && v !== null && v !== undefined)
            );
            const res = await cumplimientoApi.auditoria.listar(params);
            setRegistros(res?.data?.data ?? []);
            setMeta(res?.data ?? null);
        } catch (err) {
            setError(err);
        } finally {
            setCargando(false);
        }
    }, [filtros]);

    useEffect(() => { cargar(); }, [cargar]);

    const setF = (k, v) => setFiltros((prev) => ({ ...prev, [k]: v, page: 1 }));
    const inputCls = 'border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200';

    return (
        <div className="space-y-4">
            {/* Filtros */}
            <div className="flex flex-col sm:flex-row gap-2 flex-wrap">
                <select
                    value={filtros.operacion}
                    onChange={(e) => setF('operacion', e.target.value)}
                    className={inputCls}
                    aria-label="Filtrar por operación"
                >
                    <option value="">Toda operación</option>
                    {OPERACIONES.map((op) => (
                        <option key={op} value={op}>{op}</option>
                    ))}
                </select>

                <select
                    value={filtros.auditable_type}
                    onChange={(e) => setF('auditable_type', e.target.value)}
                    className={inputCls}
                    aria-label="Filtrar por tipo de recurso"
                >
                    {TIPOS_AUDITORIA.map((t) => (
                        <option key={t.value} value={t.value}>{t.label}</option>
                    ))}
                </select>

                <input
                    type="date"
                    value={filtros.desde}
                    onChange={(e) => setF('desde', e.target.value)}
                    className={inputCls}
                    aria-label="Desde"
                />
                <input
                    type="date"
                    value={filtros.hasta}
                    onChange={(e) => setF('hasta', e.target.value)}
                    className={inputCls}
                    aria-label="Hasta"
                />

                <button
                    type="button"
                    onClick={cargar}
                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg transition-colors"
                >
                    Filtrar
                </button>
            </div>

            <EstadoCarga
                cargando={cargando}
                error={error}
                vacio={!cargando && !error && registros.length === 0}
                mensajeCargando="Cargando registros de auditoría..."
                tituloVacio="Sin registros"
                mensajeVacio="No se encontraron registros de auditoría para los filtros seleccionados."
                iconoVacio="🔍"
                tamano="compacto"
                onReintentar={cargar}
            >
                <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-xs text-slate-600 dark:text-slate-400 uppercase tracking-wide">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold whitespace-nowrap">Fecha</th>
                                <th className="px-4 py-3 text-left font-semibold whitespace-nowrap">Operación</th>
                                <th className="px-4 py-3 text-left font-semibold whitespace-nowrap">Recurso</th>
                                <th className="px-4 py-3 text-left font-semibold whitespace-nowrap">ID</th>
                                <th className="px-4 py-3 text-left font-semibold whitespace-nowrap">Usuario</th>
                                <th className="px-4 py-3 text-left font-semibold whitespace-nowrap">Campos afectados</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-800">
                            {registros.map((r) => (
                                <tr key={r.id} className="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                    <td className="px-4 py-3 text-slate-600 dark:text-slate-400 whitespace-nowrap text-xs">{fmtFecha(r.created_at)}</td>
                                    <td className="px-4 py-3">
                                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${OPERACION_ESTILOS[r.operacion] ?? 'bg-slate-100 text-slate-600'}`}>
                                            {r.operacion}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-700 dark:text-slate-300 text-xs whitespace-nowrap">{tipoCorto(r.auditable_type)}</td>
                                    <td className="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs">{r.auditable_id ?? '—'}</td>
                                    <td className="px-4 py-3 text-slate-700 dark:text-slate-300 text-xs whitespace-nowrap">{r.nombre_usuario ?? r.usuario ?? '—'}</td>
                                    <td className="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs max-w-xs truncate">
                                        {r.campos_afectados
                                            ? (Array.isArray(r.campos_afectados)
                                                ? r.campos_afectados.join(', ')
                                                : r.campos_afectados)
                                            : r.detalle ?? '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Paginación */}
                {meta && meta.last_page > 1 && (
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pt-2 text-xs text-slate-500">
                        <span className="text-slate-500 dark:text-slate-400">
                            Página {meta.current_page} de {meta.last_page} — {meta.total} registros
                        </span>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                disabled={meta.current_page <= 1}
                                onClick={() => setFiltros((prev) => ({ ...prev, page: prev.page - 1 }))}
                                className="px-3 py-1 border border-slate-300 dark:border-slate-600 rounded-md disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-slate-700 dark:text-slate-300"
                            >
                                ← Anterior
                            </button>
                            <button
                                type="button"
                                disabled={meta.current_page >= meta.last_page}
                                onClick={() => setFiltros((prev) => ({ ...prev, page: prev.page + 1 }))}
                                className="px-3 py-1 border border-slate-300 dark:border-slate-600 rounded-md disabled:opacity-40 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors text-slate-700 dark:text-slate-300"
                            >
                                Siguiente →
                            </button>
                        </div>
                    </div>
                )}
            </EstadoCarga>
        </div>
    );
};

// ── Tab Incidentes ────────────────────────────────────────────────────────────

const TabIncidentes = () => {
    const [incidentes, setIncidentes] = useState([]);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const [mostrarFormulario, setMostrarFormulario] = useState(false);

    const cargar = useCallback(async () => {
        setCargando(true);
        setError(null);
        try {
            const res = await cumplimientoApi.incidentes.listar();
            setIncidentes(res?.data?.data ?? []);
        } catch (err) {
            setError(err);
        } finally {
            setCargando(false);
        }
    }, []);

    useEffect(() => { cargar(); }, [cargar]);

    const onGuardado = () => {
        setMostrarFormulario(false);
        cargar();
    };

    return (
        <div className="space-y-4">
            {/* Aviso legal */}
            <div className="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-xs text-amber-900 space-y-1">
                <p className="font-bold">Plazos legales obligatorios (Leyes 21.663 / 21.719)</p>
                <ul className="list-disc list-inside space-y-0.5">
                    <li><span className="font-semibold">3 h</span> — Alerta temprana al CSIRT del Estado (Art. 8 Ley 21.663)</li>
                    <li><span className="font-semibold">72 h</span> — Reporte completo al CSIRT (Art. 9 Ley 21.663)</li>
                    <li><span className="font-semibold">72 h</span> — Notificación a la Agencia de Protección de Datos (Art. 49 Ley 21.719)</li>
                    <li><span className="font-semibold">Lo antes posible</span> — Notificación a los titulares afectados (Art. 50 Ley 21.719)</li>
                </ul>
            </div>

            {/* Botón nuevo incidente */}
            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={() => setMostrarFormulario((v) => !v)}
                    className="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg transition-colors"
                >
                    {mostrarFormulario ? 'Cancelar' : '+ Nuevo incidente'}
                </button>
            </div>

            {/* Formulario de alta */}
            {mostrarFormulario && (
                <FormularioIncidente
                    onGuardado={onGuardado}
                    onCancelar={() => setMostrarFormulario(false)}
                />
            )}

            {/* Lista de incidentes */}
            <EstadoCarga
                cargando={cargando}
                error={error}
                vacio={!cargando && !error && incidentes.length === 0}
                mensajeCargando="Cargando incidentes..."
                tituloVacio="Sin incidentes registrados"
                mensajeVacio="No hay incidentes de seguridad registrados. Ante cualquier brecha de datos personales, regístrala de inmediato."
                iconoVacio="🛡️"
                tamano="compacto"
                onReintentar={cargar}
            >
                <div className="space-y-3">
                    {incidentes.map((inc) => (
                        <TarjetaIncidente key={inc.id} incidente={inc} onActualizar={cargar} />
                    ))}
                </div>
            </EstadoCarga>
        </div>
    );
};

// ── Panel principal DPO ───────────────────────────────────────────────────────

const PanelDpo = () => {
    const { tienePermiso } = usePermisos();
    const [tab, setTab] = useState('auditoria');

    if (!tienePermiso('usuarios.gestionar')) {
        return (
            <div className="flex items-center justify-center min-h-[60vh]">
                <div className="text-center text-slate-500">
                    <div className="text-5xl mb-3">🔒</div>
                    <p className="font-semibold">Acceso restringido</p>
                    <p className="text-sm mt-1">
                        Este panel requiere el permiso{' '}
                        <code className="font-mono text-xs bg-slate-100 px-1 rounded">usuarios.gestionar</code>.
                    </p>
                </div>
            </div>
        );
    }

    const TABS = [
        { id: 'auditoria', label: 'Auditoría PII' },
        { id: 'incidentes', label: 'Incidentes de seguridad' },
    ];

    return (
        <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
            {/* Cabecera */}
            <div className="bg-gradient-to-r from-slate-800 to-slate-900 text-white rounded-2xl px-6 py-5 shadow">
                <div className="flex flex-col sm:flex-row sm:items-start gap-3">
                    <div className="text-3xl" aria-hidden="true">🛡️</div>
                    <div>
                        <h1 className="text-lg font-black tracking-tight">
                            Panel del Delegado de Protección de Datos (DPO)
                        </h1>
                        <p className="text-slate-300 text-sm mt-1">
                            Cumplimiento bajo{' '}
                            <span className="font-semibold">Ley 21.719</span> (Protección de Datos Personales),{' '}
                            <span className="font-semibold">Ley 21.663</span> (Marco de Ciberseguridad) y{' '}
                            <span className="font-semibold">Ley 19.628</span>.
                            Auditoría de acceso a datos PII e incidentes de seguridad con plazos de
                            notificación obligatoria (3h / 72h al CSIRT y la Agencia).
                        </p>
                    </div>
                </div>
            </div>

            {/* Tabs */}
            <div className="border-b border-slate-200 dark:border-slate-700">
                <nav className="flex gap-1" aria-label="Secciones del panel DPO">
                    {TABS.map((t) => (
                        <button
                            key={t.id}
                            type="button"
                            role="tab"
                            aria-selected={tab === t.id}
                            onClick={() => setTab(t.id)}
                            className={`px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors ${
                                tab === t.id
                                    ? 'border-emerald-500 text-emerald-700'
                                    : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-300 hover:border-slate-300'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Contenido activo */}
            {tab === 'auditoria' && <TabAuditoria />}
            {tab === 'incidentes' && <TabIncidentes />}
        </div>
    );
};

export default PanelDpo;
