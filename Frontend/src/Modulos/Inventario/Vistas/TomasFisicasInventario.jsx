import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';
import {
    AlertBox,
    EmptyState,
    ErrorNotice,
    EstadoBadge,
    Field,
    formatNumber,
    getBodegaNombre,
    getProductoNombre,
    LoadingState,
    PageHeader,
    Panel,
    PrimaryButton,
    SecondaryButton,
    TableShell,
    Td,
    Th,
} from '../Componentes/InventarioUI';

const initialForm = {
    tipo: 'BODEGA',
    bodega_id: '',
    referencia: '',
    motivo: 'inventario_ciclico',
    observacion: '',
};

const getCodigoToma = (toma) => toma?.codigo_toma || `TF-${toma?.id ?? '-'}`;

const getDiferenciaClass = (diferencia) => {
    const value = Number(diferencia || 0);

    if (value > 0) {
        return 'text-emerald-600';
    }

    if (value < 0) {
        return 'text-rose-600';
    }

    return 'text-slate-500';
};

const TomasFisicasInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const {
        bodegas,
        cargarBodegasCache,
        invalidarProductos,
        invalidarLotes,
    } = useInventarioData();

    const [tomas, setTomas] = useState([]);
    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [form, setForm] = useState(initialForm);

    const [estadoFiltro, setEstadoFiltro] = useState('');
    const [tipoFiltro, setTipoFiltro] = useState('');
    const [busqueda, setBusqueda] = useState('');

    const [tomaSeleccionada, setTomaSeleccionada] = useState(null);
    const [conteos, setConteos] = useState({});

    const cargarDatos = async (force = false) => {
        try {
            setLoading(true);
            setError(null);

            const [tomasResponse] = await Promise.all([
                inventarioApi.tomasFisicas.listar(),
                cargarBodegasCache({ force }),
            ]);

            setTomas(tomasResponse.data || []);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarDatos();
    }, []);

    const tomasFiltradas = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return tomas.filter((toma) => {
            const coincideEstado = estadoFiltro ? toma.estado === estadoFiltro : true;
            const coincideTipo = tipoFiltro ? toma.tipo === tipoFiltro : true;

            const coincideBusqueda = !term || [
                getCodigoToma(toma),
                toma.referencia,
                toma.motivo,
                toma.observacion,
                toma.estado,
                toma.tipo,
                getBodegaNombre(toma),
            ].some((value) => String(value || '').toLowerCase().includes(term));

            return coincideEstado && coincideTipo && coincideBusqueda;
        });
    }, [tomas, estadoFiltro, tipoFiltro, busqueda]);

    const handleChange = (event) => {
        const { name, value } = event.target;

        setForm((current) => ({
            ...current,
            [name]: value,
            ...(name === 'tipo' && value !== 'BODEGA' ? { bodega_id: '' } : {}),
        }));
    };

    const limpiarFormulario = () => {
        setForm(initialForm);
        setError(null);
    };

    const crearToma = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            const payload = {
                tipo: form.tipo,
                referencia: form.referencia || null,
                motivo: form.motivo || 'inventario_ciclico',
                observacion: form.observacion || null,
            };

            if (form.tipo === 'BODEGA') {
                payload.bodega_id = Number(form.bodega_id);
            }

            await inventarioApi.tomasFisicas.crear(payload);

            await Swal.fire({
                icon: 'success',
                title: 'Toma física creada',
                text: 'La toma física fue creada en BORRADOR. Aún no modifica stock.',
                confirmButtonColor: '#10b981',
            });

            limpiarFormulario();
            setMostrarFormulario(false);
            await cargarDatos(true);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    const cargarDetalleToma = async (tomaId) => {
        try {
            const response = await inventarioApi.tomasFisicas.obtener(tomaId);
            const toma = response.data || response;

            setTomaSeleccionada(toma);

            const nextConteos = {};

            (toma.detalles || []).forEach((detalle) => {
                nextConteos[detalle.id] = detalle.stock_contado ?? '';
            });

            setConteos(nextConteos);
        } catch (err) {
            await Swal.fire({
                icon: 'error',
                title: 'No se pudo cargar la toma física',
                text: err?.response?.data?.message || err?.message || 'Revise permisos o estado del registro.',
                confirmButtonColor: '#ef4444',
            });
        }
    };

    const ejecutarAccionSimple = async (toma, accion) => {
        const textos = {
            iniciar: {
                title: 'Iniciar toma física',
                text: 'La toma pasará a EN_CONTEO. Esto no modifica stock.',
                success: 'La toma física fue iniciada correctamente.',
                call: () => inventarioApi.tomasFisicas.iniciar(toma.id),
            },
            cerrar: {
                title: 'Cerrar toma física',
                text: 'La toma quedará cerrada para revisión de diferencias. Esto no modifica stock.',
                success: 'La toma física fue cerrada correctamente.',
                call: () => inventarioApi.tomasFisicas.cerrar(toma.id, {
                    observacion: 'Cierre desde frontend de inventario',
                }),
            },
            cancelar: {
                title: 'Cancelar toma física',
                text: 'La toma será cancelada. No se generarán ajustes.',
                success: 'La toma física fue cancelada correctamente.',
                call: () => inventarioApi.tomasFisicas.cancelar(toma.id, {
                    observacion: 'Cancelación desde frontend de inventario',
                }),
            },
        };

        const config = textos[accion];

        const confirm = await Swal.fire({
            icon: accion === 'cancelar' ? 'warning' : 'question',
            title: config.title,
            text: config.text,
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Volver',
            confirmButtonColor: accion === 'cancelar' ? '#ef4444' : '#10b981',
        });

        if (!confirm.isConfirmed) {
            return;
        }

        try {
            await config.call();

            await Swal.fire({
                icon: 'success',
                title: 'Operación realizada',
                text: config.success,
                confirmButtonColor: '#10b981',
            });

            await cargarDatos(true);

            if (tomaSeleccionada?.id === toma.id) {
                await cargarDetalleToma(toma.id);
            }
        } catch (err) {
            await Swal.fire({
                icon: 'error',
                title: 'No se pudo completar la acción',
                text: err?.response?.data?.message || err?.message || 'Revise permisos o estado de la toma física.',
                confirmButtonColor: '#ef4444',
            });
        }
    };

    const registrarConteos = async () => {
        if (!tomaSeleccionada) {
            return;
        }

        const detalles = Object.entries(conteos)
            .filter(([, value]) => value !== '' && value !== null && value !== undefined)
            .map(([detalleId, value]) => ({
                detalle_id: Number(detalleId),
                stock_contado: Number(value),
                observacion: 'Conteo registrado desde frontend de inventario',
            }));

        if (!detalles.length) {
            await Swal.fire({
                icon: 'info',
                title: 'Sin conteos',
                text: 'Ingresa al menos un stock contado para registrar.',
                confirmButtonColor: '#10b981',
            });

            return;
        }

        try {
            setSaving(true);

            await inventarioApi.tomasFisicas.registrarConteos(tomaSeleccionada.id, {
                detalles,
            });

            await Swal.fire({
                icon: 'success',
                title: 'Conteo registrado',
                text: 'El conteo fue registrado correctamente. El stock físico aún no ha sido modificado.',
                confirmButtonColor: '#10b981',
            });

            await cargarDatos(true);
            await cargarDetalleToma(tomaSeleccionada.id);
        } catch (err) {
            await Swal.fire({
                icon: 'error',
                title: 'No se pudo registrar el conteo',
                text: err?.response?.data?.message || err?.message || 'Revise permisos, estado de la toma o datos ingresados.',
                confirmButtonColor: '#ef4444',
            });
        } finally {
            setSaving(false);
        }
    };

    const ajustarToma = async (toma) => {
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Aplicar ajuste real',
            html: `
                <p style="font-size:14px; margin-bottom:12px;">
                    Esta acción generará movimientos reales en Kardex y no podrá repetirse.
                </p>
                <p style="font-size:13px; color:#64748b;">
                    Si existen diferencias positivas, el costo unitario será usado para valorizar el ajuste.
                </p>
            `,
            input: 'number',
            inputLabel: 'Costo unitario para ajustes positivos',
            inputPlaceholder: 'Ej: 2500',
            inputValue: 0,
            inputAttributes: {
                min: 0,
                step: '1',
            },
            showCancelButton: true,
            confirmButtonText: 'Sí, ajustar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            inputValidator: (value) => {
                if (value === '' || Number(value) < 0) {
                    return 'Ingresa un costo unitario válido. Puede ser 0 si no hay diferencia positiva.';
                }

                return null;
            },
        });

        if (!confirm.isConfirmed) {
            return;
        }

        const referenciaDefault = `AJ-${getCodigoToma(toma)}`;

        try {
            await inventarioApi.tomasFisicas.ajustar(toma.id, {
                referencia: referenciaDefault,
                motivo: 'correccion_stock_toma_fisica',
                observacion: 'Ajuste generado desde frontend de inventario',
                costo_unitario: Number(confirm.value || 0),
            });

            await Swal.fire({
                icon: 'success',
                title: 'Toma ajustada',
                text: 'La toma física fue ajustada correctamente. Se generaron los movimientos reales correspondientes.',
                confirmButtonColor: '#10b981',
            });

            invalidarProductos();
            invalidarLotes();
            await cargarDatos(true);

            if (tomaSeleccionada?.id === toma.id) {
                await cargarDetalleToma(toma.id);
            }
        } catch (err) {
            await Swal.fire({
                icon: 'error',
                title: 'No se pudo ajustar',
                text: err?.response?.data?.message || err?.message || 'Solo una toma CERRADA puede ajustarse. Revise permisos, diferencias o costo unitario.',
                confirmButtonColor: '#ef4444',
            });
        }
    };

    if (loading) {
        return <LoadingState text="Cargando tomas físicas..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Tomas Físicas"
                description="Conteo físico, comparación contra stock sistema y generación de ajustes auditables en Kardex."
                helpModuloId="inventario"
                actions={(
                    <>
                        <SecondaryButton onClick={() => cargarDatos(true)}>
                            <i className="fas fa-rotate-right"></i>
                            Actualizar
                        </SecondaryButton>

                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)}>
                            <i className={mostrarFormulario ? 'fas fa-xmark' : 'fas fa-plus'}></i>
                            {mostrarFormulario ? 'Cerrar formulario' : 'Nueva toma física'}
                        </PrimaryButton>
                    </>
                )}
            />

            <AlertBox tone="amber">
                Regla crítica: la toma física compara contra stock físico, no contra stock disponible. Las reservas activas no alteran el stock_sistema capturado.
            </AlertBox>

            {mostrarFormulario && (
                <Panel
                    title="Crear toma física"
                    subtitle="Crear, iniciar, contar y cerrar no modifica stock. Solo ajustar cambia stock real."
                >
                    <ErrorNotice error={error} />

                    <form onSubmit={crearToma} className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                        <Field label="Tipo">
                            <select
                                name="tipo"
                                value={form.tipo}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                required
                            >
                                <option value="GENERAL">GENERAL</option>
                                <option value="BODEGA">BODEGA</option>
                                <option value="CICLICA">CICLICA</option>
                            </select>
                        </Field>

                        {form.tipo === 'BODEGA' && (
                            <Field label="Bodega">
                                <select
                                    name="bodega_id"
                                    value={form.bodega_id}
                                    onChange={handleChange}
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                    required
                                >
                                    <option value="">Seleccionar bodega</option>
                                    {bodegas.map((bodega) => (
                                        <option key={bodega.id} value={bodega.id}>
                                            {bodega.codigo ? `${bodega.codigo} - ${bodega.nombre}` : bodega.nombre}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}

                        <Field label="Referencia">
                            <input
                                type="text"
                                name="referencia"
                                value={form.referencia}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="TF-DEMO-001"
                            />
                        </Field>

                        <Field label="Motivo">
                            <input
                                type="text"
                                name="motivo"
                                value={form.motivo}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                            />
                        </Field>

                        <div className="md:col-span-2 xl:col-span-4">
                            <Field label="Observación">
                                <textarea
                                    name="observacion"
                                    value={form.observacion}
                                    onChange={handleChange}
                                    rows="3"
                                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none resize-none"
                                    placeholder="Toma física demo por bodega"
                                />
                            </Field>
                        </div>

                        <div className="md:col-span-2 xl:col-span-4 flex flex-wrap justify-end gap-3">
                            <SecondaryButton type="button" onClick={limpiarFormulario}>
                                Limpiar
                            </SecondaryButton>

                            <PrimaryButton type="submit" disabled={saving}>
                                <i className="fas fa-save"></i>
                                {saving ? 'Guardando...' : 'Crear toma'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Listado de tomas físicas"
                subtitle="Flujo recomendado: crear → iniciar → registrar conteos → cerrar → ajustar."
                actions={(
                    <div className="flex flex-col xl:flex-row gap-3 w-full xl:w-auto">
                        <select
                            value={estadoFiltro}
                            onChange={(event) => setEstadoFiltro(event.target.value)}
                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        >
                            <option value="">Todos los estados</option>
                            <option value="BORRADOR">BORRADOR</option>
                            <option value="EN_CONTEO">EN_CONTEO</option>
                            <option value="CERRADA">CERRADA</option>
                            <option value="AJUSTADA">AJUSTADA</option>
                            <option value="CANCELADA">CANCELADA</option>
                        </select>

                        <select
                            value={tipoFiltro}
                            onChange={(event) => setTipoFiltro(event.target.value)}
                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        >
                            <option value="">Todos los tipos</option>
                            <option value="GENERAL">GENERAL</option>
                            <option value="BODEGA">BODEGA</option>
                            <option value="CICLICA">CICLICA</option>
                        </select>

                        <input
                            type="text"
                            value={busqueda}
                            onChange={(event) => setBusqueda(event.target.value)}
                            className="w-full xl:w-80 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                            placeholder="Buscar toma, referencia o bodega..."
                        />
                    </div>
                )}
            >
                {tomasFiltradas.length === 0 ? (
                    <EmptyState
                        title="Sin tomas físicas"
                        description="Crea una toma para iniciar el flujo de conteo físico."
                        icon="fas fa-clipboard-list"
                    />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código</Th>
                                <Th>Tipo</Th>
                                <Th>Estado</Th>
                                <Th>Bodega</Th>
                                <Th>Referencia</Th>
                                <Th align="center">Acciones</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {tomasFiltradas.map((toma) => (
                                <tr key={toma.id} className="hover:bg-slate-50/70 transition-colors">
                                    <Td className="font-black text-slate-800">
                                        {getCodigoToma(toma)}
                                    </Td>

                                    <Td className="font-black text-slate-600">
                                        {toma.tipo}
                                    </Td>

                                    <Td>
                                        <EstadoBadge value={toma.estado} />
                                    </Td>

                                    <Td className="text-slate-500 font-semibold">
                                        {getBodegaNombre(toma)}
                                    </Td>

                                    <Td className="text-slate-500">
                                        {toma.referencia || '-'}
                                    </Td>

                                    <Td align="center">
                                        <div className="flex flex-wrap justify-center gap-2">
                                            <button
                                                type="button"
                                                onClick={() => cargarDetalleToma(toma.id)}
                                                className="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-black"
                                            >
                                                Ver
                                            </button>

                                            <button
                                                type="button"
                                                disabled={toma.estado !== 'BORRADOR'}
                                                onClick={() => ejecutarAccionSimple(toma, 'iniciar')}
                                                className="px-3 py-1.5 rounded-lg bg-blue-100 hover:bg-blue-200 disabled:opacity-40 text-blue-700 text-xs font-black"
                                            >
                                                Iniciar
                                            </button>

                                            <button
                                                type="button"
                                                disabled={toma.estado !== 'EN_CONTEO'}
                                                onClick={() => ejecutarAccionSimple(toma, 'cerrar')}
                                                className="px-3 py-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 disabled:opacity-40 text-amber-700 text-xs font-black"
                                            >
                                                Cerrar
                                            </button>

                                            <button
                                                type="button"
                                                disabled={toma.estado !== 'CERRADA'}
                                                onClick={() => ajustarToma(toma)}
                                                className="px-3 py-1.5 rounded-lg bg-emerald-100 hover:bg-emerald-200 disabled:opacity-40 text-emerald-700 text-xs font-black"
                                            >
                                                Ajustar
                                            </button>

                                            <button
                                                type="button"
                                                disabled={['AJUSTADA', 'CANCELADA'].includes(toma.estado)}
                                                onClick={() => ejecutarAccionSimple(toma, 'cancelar')}
                                                className="px-3 py-1.5 rounded-lg bg-rose-100 hover:bg-rose-200 disabled:opacity-40 text-rose-700 text-xs font-black"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                    </Td>
                                </tr>
                            ))}
                        </tbody>
                    </TableShell>
                )}
            </Panel>

            {tomaSeleccionada && (
                <Panel
                    title={`Detalle toma física ${getCodigoToma(tomaSeleccionada)}`}
                    subtitle="Registra conteos, revisa diferencias y valida el movimiento de ajuste generado."
                    actions={(
                        <>
                            <SecondaryButton onClick={() => cargarDetalleToma(tomaSeleccionada.id)}>
                                <i className="fas fa-rotate-right"></i>
                                Recargar detalle
                            </SecondaryButton>

                            <PrimaryButton
                                onClick={registrarConteos}
                                disabled={saving || tomaSeleccionada.estado !== 'EN_CONTEO'}
                            >
                                <i className="fas fa-clipboard-check"></i>
                                Registrar conteos
                            </PrimaryButton>
                        </>
                    )}
                >
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div className="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                            <p className="text-xs font-black text-slate-400 uppercase">Estado</p>
                            <div className="mt-2">
                                <EstadoBadge value={tomaSeleccionada.estado} />
                            </div>
                        </div>

                        <div className="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                            <p className="text-xs font-black text-slate-400 uppercase">Tipo</p>
                            <p className="font-black text-slate-800 mt-2">{tomaSeleccionada.tipo}</p>
                        </div>

                        <div className="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                            <p className="text-xs font-black text-slate-400 uppercase">Bodega</p>
                            <p className="font-black text-slate-800 mt-2">{getBodegaNombre(tomaSeleccionada)}</p>
                        </div>

                        <div className="rounded-2xl bg-slate-50 border border-slate-100 p-4">
                            <p className="text-xs font-black text-slate-400 uppercase">Referencia</p>
                            <p className="font-black text-slate-800 mt-2">{tomaSeleccionada.referencia || '-'}</p>
                        </div>
                    </div>

                    {!tomaSeleccionada.detalles?.length ? (
                        <EmptyState
                            title="Sin detalles"
                            description="La toma no tiene líneas de stock para contar."
                            icon="fas fa-list"
                        />
                    ) : (
                        <TableShell>
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Producto</Th>
                                    <Th>Bodega</Th>
                                    <Th>Lote</Th>
                                    <Th align="right">Stock sistema</Th>
                                    <Th align="right">Stock contado</Th>
                                    <Th align="right">Diferencia</Th>
                                    <Th>Movimiento ajuste</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {tomaSeleccionada.detalles.map((detalle) => (
                                    <tr key={detalle.id} className="hover:bg-slate-50/70 transition-colors">
                                        <Td className="font-black text-slate-800">
                                            {getProductoNombre(detalle)}
                                        </Td>

                                        <Td className="font-semibold text-slate-500">
                                            {getBodegaNombre(detalle)}
                                        </Td>

                                        <Td className="text-slate-500">
                                            {detalle.lote?.codigo_lote || detalle.lote?.codigo || (detalle.lote_id ? `Lote #${detalle.lote_id}` : '-')}
                                        </Td>

                                        <Td align="right" className="font-black text-slate-800">
                                            {formatNumber(detalle.stock_sistema, 2)}
                                        </Td>

                                        <Td align="right">
                                            {tomaSeleccionada.estado === 'EN_CONTEO' ? (
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    value={conteos[detalle.id] ?? ''}
                                                    onChange={(event) => setConteos((current) => ({
                                                        ...current,
                                                        [detalle.id]: event.target.value,
                                                    }))}
                                                    className="w-28 rounded-xl border border-slate-200 px-3 py-2 text-right text-sm font-black focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                                />
                                            ) : (
                                                <span className="font-black text-slate-800">
                                                    {detalle.stock_contado === null || detalle.stock_contado === undefined
                                                        ? '-'
                                                        : formatNumber(detalle.stock_contado, 2)}
                                                </span>
                                            )}
                                        </Td>

                                        <Td align="right" className={`font-black ${getDiferenciaClass(detalle.diferencia)}`}>
                                            {formatNumber(detalle.diferencia || 0, 2)}
                                        </Td>

                                        <Td className="text-slate-500">
                                            {detalle.movimiento_ajuste_id
                                                ? `Movimiento #${detalle.movimiento_ajuste_id}`
                                                : '-'}
                                        </Td>
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

export default TomasFisicasInventario;