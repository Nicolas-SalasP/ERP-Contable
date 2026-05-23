import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { usePermisos } from '../../../Contextos/Permisos';
import { useInventarioData } from '../Hooks/useInventarioData';
import {
    EmptyState,
    ErrorNotice,
    Field,
    formatDate,
    formatNumber,
    LoadingState,
    PageHeader,
    Panel,
    PrimaryButton,
    SecondaryButton,
    StatCard,
    TableShell,
    Td,
    Th,
} from '../Componentes/InventarioUI';

const initialForm = {
    id: null,
    producto_id: '',
    bodega_id: '',
    stock_minimo: '',
    stock_objetivo: '',
    punto_reorden: '',
    dias_alerta_vencimiento: 30,
    activo: true,
};

const tipoLabels = {
    STOCK_BAJO: 'Stock bajo',
    REPOSICION_SUGERIDA: 'Reposición sugerida',
    LOTE_POR_VENCER: 'Lote por vencer',
    LOTE_VENCIDO: 'Lote vencido',
    RESERVA_CRITICA: 'Reserva crítica',
    TOMA_FISICA_PENDIENTE: 'Toma física pendiente',
    AJUSTE_CRITICO_RECIENTE: 'Ajuste crítico reciente',
};

const severidadTones = {
    baja: 'bg-slate-100 text-slate-700 border-slate-200',
    media: 'bg-amber-50 text-amber-700 border-amber-200',
    alta: 'bg-orange-50 text-orange-700 border-orange-200',
    critica: 'bg-rose-50 text-rose-700 border-rose-200',
};

const SeveridadBadge = ({ value }) => {
    const severidad = value || 'media';
    const tone = severidadTones[severidad] || severidadTones.media;

    return (
        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-black uppercase tracking-wide border ${tone}`}>
            {severidad}
        </span>
    );
};

const TipoBadge = ({ value }) => (
    <span className="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-black uppercase tracking-wide border bg-indigo-50 text-indigo-700 border-indigo-200">
        {tipoLabels[value] || String(value || '-').replaceAll('_', ' ')}
    </span>
);

const AlertasInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const [alertas, setAlertas] = useState([]);
    const [resumenAlertas, setResumenAlertas] = useState(null);
    const [sugerencias, setSugerencias] = useState([]);
    const [reglas, setReglas] = useState([]);

    const [form, setForm] = useState(initialForm);
    const [mostrarFormulario, setMostrarFormulario] = useState(false);

    const [tipoFiltro, setTipoFiltro] = useState('');
    const [severidadFiltro, setSeveridadFiltro] = useState('');
    const [productoFiltro, setProductoFiltro] = useState('');
    const [bodegaFiltro, setBodegaFiltro] = useState('');
    const [busqueda, setBusqueda] = useState('');

    const {
        productos,
        bodegas,
        cargarProductosCache,
        cargarBodegasCache,
    } = useInventarioData();

    const { tienePermiso } = usePermisos();
    const puedeVerReglas = tienePermiso('inventario.reglas_reposicion.ver');
    const puedeCrearReglas = tienePermiso('inventario.reglas_reposicion.crear');
    const puedeEditarReglas = tienePermiso('inventario.reglas_reposicion.editar');
    const puedeEliminarReglas = tienePermiso('inventario.reglas_reposicion.eliminar');
    const puedeGestionarReglas = puedeEditarReglas || puedeEliminarReglas;

    const cargarDatos = async (force = false) => {
        try {
            setLoading(true);
            setError(null);

            const params = {
                tipo: tipoFiltro || undefined,
                severidad: severidadFiltro || undefined,
                producto_id: productoFiltro || undefined,
                bodega_id: bodegaFiltro || undefined,
                limit: 150,
            };

            const reglasPromise = puedeVerReglas
                ? inventarioApi.reglasReposicion.listar({ per_page: 100 })
                : Promise.resolve({ data: [] });

            const [alertasResponse, sugerenciasResponse, reglasResponse] = await Promise.allSettled([
                inventarioApi.alertas.listar(params),
                inventarioApi.reposicion.sugerencias({
                    producto_id: productoFiltro || undefined,
                    bodega_id: bodegaFiltro || undefined,
                }),
                reglasPromise,
                cargarProductosCache({ force }),
                cargarBodegasCache({ force }),
            ]);

            if (alertasResponse.status === 'fulfilled') {
                setAlertas(alertasResponse.value.data || []);
                setResumenAlertas(alertasResponse.value.resumen || null);
            }

            if (sugerenciasResponse.status === 'fulfilled') {
                setSugerencias(sugerenciasResponse.value.data || []);
            }

            if (reglasResponse.status === 'fulfilled') {
                setReglas(reglasResponse.value.data || []);
            }
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const alertasFiltradas = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        if (!term) {
            return alertas;
        }

        return alertas.filter((alerta) => [
            alerta.tipo,
            tipoLabels[alerta.tipo],
            alerta.severidad,
            alerta.titulo,
            alerta.descripcion,
            alerta.producto_nombre,
            alerta.bodega_nombre,
            alerta.lote_codigo,
            alerta.referencia,
        ].some((value) => String(value || '').toLowerCase().includes(term)));
    }, [alertas, busqueda]);

    const resumenLocal = useMemo(() => {
        const total = alertasFiltradas.length;
        const criticas = alertasFiltradas.filter((alerta) => alerta.severidad === 'critica').length;
        const altas = alertasFiltradas.filter((alerta) => alerta.severidad === 'alta').length;
        const reposicionTotal = sugerencias.reduce((totalSugerido, sugerencia) => totalSugerido + Number(sugerencia.cantidad_sugerida || 0), 0);

        return {
            total: resumenAlertas?.total ?? total,
            criticas,
            altas,
            reglas: reglas.length,
            reposicionTotal,
        };
    }, [alertasFiltradas, reglas.length, resumenAlertas, sugerencias]);

    const limpiarFormulario = () => {
        setForm(initialForm);
        setMostrarFormulario(false);
        setError(null);
    };

    const handleChange = (event) => {
        const { name, value, type, checked } = event.target;

        setForm((current) => ({
            ...current,
            [name]: type === 'checkbox' ? checked : value,
        }));
    };

    const editarRegla = (regla) => {
        if (!puedeEditarReglas) {
            return;
        }

        setForm({
            id: regla.id,
            producto_id: regla.producto_id || '',
            bodega_id: regla.bodega_id || '',
            stock_minimo: regla.stock_minimo ?? '',
            stock_objetivo: regla.stock_objetivo ?? '',
            punto_reorden: regla.punto_reorden ?? '',
            dias_alerta_vencimiento: regla.dias_alerta_vencimiento ?? 30,
            activo: Boolean(regla.activo),
        });
        setMostrarFormulario(true);
    };

    const guardarRegla = async (event) => {
        event.preventDefault();

        const tienePermisoOperacion = form.id ? puedeEditarReglas : puedeCrearReglas;

        if (!tienePermisoOperacion) {
            const permisoRequerido = form.id
                ? 'inventario.reglas_reposicion.editar'
                : 'inventario.reglas_reposicion.crear';

            setError({
                message: `No tienes permiso para ${form.id ? 'editar' : 'crear'} reglas de reposición.`,
                permiso_requerido: permisoRequerido,
            });

            Swal.fire({
                icon: 'warning',
                title: 'Acción no permitida',
                text: 'Tu rol no tiene permiso para realizar esta operación.',
                confirmButtonColor: '#f59e0b',
            });

            return;
        }

        try {
            setSaving(true);
            setError(null);

            const payload = {
                producto_id: Number(form.producto_id),
                bodega_id: form.bodega_id ? Number(form.bodega_id) : null,
                stock_minimo: Number(form.stock_minimo || 0),
                stock_objetivo: Number(form.stock_objetivo || 0),
                punto_reorden: form.punto_reorden !== '' ? Number(form.punto_reorden) : null,
                dias_alerta_vencimiento: Number(form.dias_alerta_vencimiento || 30),
                activo: Boolean(form.activo),
            };

            if (form.id) {
                await inventarioApi.reglasReposicion.actualizar(form.id, payload);
            } else {
                await inventarioApi.reglasReposicion.crear(payload);
            }

            await Swal.fire({
                icon: 'success',
                title: form.id ? 'Regla actualizada' : 'Regla creada',
                text: 'La configuración de reposición quedó registrada correctamente.',
                confirmButtonColor: '#10b981',
            });

            limpiarFormulario();
            await cargarDatos(true);
        } catch (err) {
            setError(err?.response?.data || err);
            Swal.fire({
                icon: 'error',
                title: 'No se pudo guardar la regla',
                text: err?.message || 'Revisa los datos ingresados.',
                confirmButtonColor: '#ef4444',
            });
        } finally {
            setSaving(false);
        }
    };

    const eliminarRegla = async (regla) => {
        if (!puedeEliminarReglas) {
            return;
        }

        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Eliminar regla de reposición',
            text: 'La regla se eliminará del cálculo de alertas y sugerencias.',
            showCancelButton: true,
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        });

        if (!confirm.isConfirmed) {
            return;
        }

        try {
            await inventarioApi.reglasReposicion.eliminar(regla.id);
            await cargarDatos(true);
        } catch (err) {
            setError(err?.response?.data || err);
            Swal.fire({
                icon: 'error',
                title: 'No se pudo eliminar',
                text: err?.message || 'Intenta nuevamente.',
                confirmButtonColor: '#ef4444',
            });
        }
    };

    const aplicarFiltros = () => cargarDatos(false);

    if (loading) {
        return <LoadingState text="Cargando alertas y reglas de reposición..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Alertas y Reposición"
                description="Control proactivo de stock bajo, reposición sugerida, lotes por vencer, reservas críticas, tomas físicas pendientes y ajustes relevantes. No ejecuta compras ni movimientos automáticos."
                helpModuloId="inventario"
                actions={(
                    <>
                        <SecondaryButton onClick={() => cargarDatos(true)}>
                            <i className="fas fa-rotate-right"></i>
                            Actualizar
                        </SecondaryButton>
                        {puedeCrearReglas && (
                            <PrimaryButton onClick={() => setMostrarFormulario(true)}>
                                <i className="fas fa-plus"></i>
                                Nueva regla
                            </PrimaryButton>
                        )}
                    </>
                )}
            />

            {error && <ErrorNotice error={error} />}

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-5">
                <StatCard title="Alertas" value={formatNumber(resumenLocal.total)} helper="Registros detectados" icon="fas fa-bell" tone="blue" />
                <StatCard title="Críticas" value={formatNumber(resumenLocal.criticas)} helper="Requieren revisión inmediata" icon="fas fa-triangle-exclamation" tone="rose" />
                <StatCard title="Altas" value={formatNumber(resumenLocal.altas)} helper="Riesgo operativo" icon="fas fa-arrow-trend-down" tone="amber" />
                {puedeVerReglas && (
                    <StatCard title="Reglas" value={formatNumber(resumenLocal.reglas)} helper="Configuraciones activas/inactivas" icon="fas fa-sliders" tone="indigo" />
                )}
                <StatCard title="Reposición sugerida" value={formatNumber(resumenLocal.reposicionTotal, 2)} helper="Unidades acumuladas" icon="fas fa-boxes-packing" tone="emerald" />
            </div>

            <Panel title="Filtros operativos" subtitle="Los datos base de productos y bodegas se reutilizan desde cache del módulo.">
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
                    <Field label="Tipo de alerta">
                        <select value={tipoFiltro} onChange={(e) => setTipoFiltro(e.target.value)} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                            <option value="">Todas</option>
                            {Object.entries(tipoLabels).map(([key, label]) => (
                                <option key={key} value={key}>{label}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Severidad">
                        <select value={severidadFiltro} onChange={(e) => setSeveridadFiltro(e.target.value)} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                            <option value="">Todas</option>
                            <option value="critica">Crítica</option>
                            <option value="alta">Alta</option>
                            <option value="media">Media</option>
                            <option value="baja">Baja</option>
                        </select>
                    </Field>

                    <Field label="Producto">
                        <select value={productoFiltro} onChange={(e) => setProductoFiltro(e.target.value)} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                            <option value="">Todos</option>
                            {productos.map((producto) => (
                                <option key={producto.id} value={producto.id}>{producto.sku} — {producto.nombre}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Bodega">
                        <select value={bodegaFiltro} onChange={(e) => setBodegaFiltro(e.target.value)} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                            <option value="">Todas</option>
                            {bodegas.map((bodega) => (
                                <option key={bodega.id} value={bodega.id}>{bodega.nombre}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Buscar en resultados">
                        <input value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="Producto, bodega, lote..." className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700" />
                    </Field>
                </div>

                <div className="mt-5 flex flex-wrap gap-3">
                    <PrimaryButton onClick={aplicarFiltros}>
                        <i className="fas fa-filter"></i>
                        Aplicar filtros
                    </PrimaryButton>
                    <SecondaryButton onClick={() => {
                        setTipoFiltro('');
                        setSeveridadFiltro('');
                        setProductoFiltro('');
                        setBodegaFiltro('');
                        setBusqueda('');
                        setTimeout(() => cargarDatos(false), 0);
                    }}>
                        Limpiar
                    </SecondaryButton>
                </div>
            </Panel>

            {mostrarFormulario && (puedeCrearReglas || puedeEditarReglas) && (
                <Panel title={form.id ? 'Editar regla de reposición' : 'Nueva regla de reposición'} subtitle="Define mínimos, objetivos y días de alerta por vencimiento. Puede ser global por producto o específica por bodega.">
                    <form onSubmit={guardarRegla} className="space-y-5">
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                            <Field label="Producto">
                                <select name="producto_id" value={form.producto_id} onChange={handleChange} required className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                                    <option value="">Seleccionar</option>
                                    {productos.map((producto) => (
                                        <option key={producto.id} value={producto.id}>{producto.sku} — {producto.nombre}</option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Bodega opcional">
                                <select name="bodega_id" value={form.bodega_id} onChange={handleChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700">
                                    <option value="">Global por producto</option>
                                    {bodegas.map((bodega) => (
                                        <option key={bodega.id} value={bodega.id}>{bodega.nombre}</option>
                                    ))}
                                </select>
                            </Field>

                            <Field label="Stock mínimo">
                                <input name="stock_minimo" type="number" min="0" step="0.0001" value={form.stock_minimo} onChange={handleChange} required className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700" />
                            </Field>

                            <Field label="Stock objetivo">
                                <input name="stock_objetivo" type="number" min="0" step="0.0001" value={form.stock_objetivo} onChange={handleChange} required className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700" />
                            </Field>

                            <Field label="Punto de reorden">
                                <input name="punto_reorden" type="number" min="0" step="0.0001" value={form.punto_reorden} onChange={handleChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700" />
                            </Field>

                            <Field label="Días alerta vencimiento">
                                <input name="dias_alerta_vencimiento" type="number" min="0" max="3650" value={form.dias_alerta_vencimiento} onChange={handleChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700" />
                            </Field>

                            <Field label="Estado">
                                <label className="inline-flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-bold text-slate-700 w-full">
                                    <input name="activo" type="checkbox" checked={form.activo} onChange={handleChange} />
                                    Regla activa
                                </label>
                            </Field>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <PrimaryButton type="submit" disabled={saving}>
                                <i className="fas fa-save"></i>
                                {saving ? 'Guardando...' : 'Guardar regla'}
                            </PrimaryButton>
                            <SecondaryButton type="button" onClick={limpiarFormulario}>
                                Cancelar
                            </SecondaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel title="Alertas operativas" subtitle="Alertas calculadas dinámicamente; no modifican stock ni generan compras.">
                {alertasFiltradas.length === 0 ? (
                    <EmptyState title="Sin alertas" description="No hay alertas para los filtros actuales." icon="fas fa-bell-slash" />
                ) : (
                    <TableShell>
                        <thead>
                            <tr>
                                <Th>Severidad</Th>
                                <Th>Tipo</Th>
                                <Th>Detalle</Th>
                                <Th>Producto / Bodega</Th>
                                <Th>Cantidad</Th>
                                <Th>Fecha</Th>
                            </tr>
                        </thead>
                        <tbody>
                            {alertasFiltradas.map((alerta, index) => (
                                <tr key={`${alerta.tipo}-${alerta.referencia}-${index}`}>
                                    <Td><SeveridadBadge value={alerta.severidad} /></Td>
                                    <Td><TipoBadge value={alerta.tipo} /></Td>
                                    <Td>
                                        <div className="font-black text-slate-800">{alerta.titulo}</div>
                                        <div className="text-xs text-slate-500 font-semibold mt-1 max-w-xl">{alerta.descripcion}</div>
                                        {alerta.lote_codigo && <div className="text-xs text-indigo-600 font-black mt-1">Lote: {alerta.lote_codigo}</div>}
                                    </Td>
                                    <Td>
                                        <div className="font-black text-slate-800">{alerta.producto_nombre || '-'}</div>
                                        <div className="text-xs text-slate-500 font-semibold">{alerta.bodega_nombre || 'Regla global / sin bodega'}</div>
                                    </Td>
                                    <Td>
                                        <div className="text-sm font-black text-slate-800">Actual: {formatNumber(alerta.cantidad_actual, 2)}</div>
                                        {alerta.cantidad_sugerida !== null && alerta.cantidad_sugerida !== undefined && (
                                            <div className="text-xs text-emerald-600 font-black">Sugerida: {formatNumber(alerta.cantidad_sugerida, 2)}</div>
                                        )}
                                    </Td>
                                    <Td>{formatDate(alerta.fecha_referencia)}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </TableShell>
                )}
            </Panel>

            {puedeVerReglas && (
                <Panel title="Reglas de reposición" subtitle="Configuraciones usadas por el motor de alertas y sugerencias.">
                {reglas.length === 0 ? (
                    <EmptyState title="Sin reglas" description="Crea una regla para activar alertas de stock y reposición sugerida." icon="fas fa-sliders" />
                ) : (
                    <TableShell>
                        <thead>
                            <tr>
                                <Th>Producto</Th>
                                <Th>Bodega</Th>
                                <Th align="right">Mínimo</Th>
                                <Th align="right">Objetivo</Th>
                                <Th align="right">Reorden</Th>
                                <Th align="right">Días venc.</Th>
                                <Th>Estado</Th>
                                {puedeGestionarReglas && <Th align="right">Acciones</Th>}
                            </tr>
                        </thead>
                        <tbody>
                            {reglas.map((regla) => (
                                <tr key={regla.id}>
                                    <Td>
                                        <div className="font-black text-slate-800">{regla.producto?.nombre || `Producto #${regla.producto_id}`}</div>
                                        <div className="text-xs text-slate-500 font-semibold">{regla.producto?.sku || '-'}</div>
                                    </Td>
                                    <Td>{regla.bodega?.nombre || 'Global por producto'}</Td>
                                    <Td align="right">{formatNumber(regla.stock_minimo, 2)}</Td>
                                    <Td align="right">{formatNumber(regla.stock_objetivo, 2)}</Td>
                                    <Td align="right">{regla.punto_reorden !== null ? formatNumber(regla.punto_reorden, 2) : '-'}</Td>
                                    <Td align="right">{formatNumber(regla.dias_alerta_vencimiento)}</Td>
                                    <Td>
                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-black uppercase tracking-wide border ${regla.activo ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`}>
                                            {regla.activo ? 'Activa' : 'Inactiva'}
                                        </span>
                                    </Td>
                                    {puedeGestionarReglas && (
                                        <Td align="right">
                                            <div className="flex justify-end gap-2">
                                                {puedeEditarReglas && (
                                                    <SecondaryButton type="button" onClick={() => editarRegla(regla)} className="px-3 py-2">
                                                        Editar
                                                    </SecondaryButton>
                                                )}
                                                {puedeEliminarReglas && (
                                                    <button type="button" onClick={() => eliminarRegla(regla)} className="inline-flex items-center justify-center gap-2 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-black py-2 px-3 rounded-xl transition-all text-xs">
                                                        Eliminar
                                                    </button>
                                                )}
                                            </div>
                                        </Td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </TableShell>
                )}
                </Panel>
            )}
        </div>
    );
};

export default AlertasInventario;
