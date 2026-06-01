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
    formatDate,
    formatNumber,
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
    referencia: '',
    motivo: 'reserva_comercial',
    observacion: '',
    fecha_expiracion: '',
    producto_id: '',
    bodega_id: '',
    lote_id: '',
    cantidad: '',
};

const getCodigoReserva = (reserva) => reserva.codigo_reserva || `RES-${reserva.id}`;

const ReservasInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

    const {
        productos,
        bodegas,
        lotes,
        cargarProductosCache,
        cargarBodegasCache,
        cargarLotesCache,
        invalidarProductos,
        invalidarLotes,
    } = useInventarioData();

    const [reservas, setReservas] = useState([]);
    const [disponibilidad, setDisponibilidad] = useState([]);

    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [form, setForm] = useState(initialForm);
    const [estadoFiltro, setEstadoFiltro] = useState('');
    const [busqueda, setBusqueda] = useState('');

    const cargarDatos = async (force = false) => {
        try {
            setLoading(true);
            setError(null);

            const [
                reservasResponse,
                disponibilidadResponse,
            ] = await Promise.allSettled([
                inventarioApi.reservas.listar(),
                inventarioApi.disponibilidad.listar(),
                cargarProductosCache({ force }),
                cargarBodegasCache({ force }),
                cargarLotesCache({ force }),
            ]);

            if (reservasResponse.status === 'fulfilled') {
                setReservas(reservasResponse.value.data || []);
            }

            if (disponibilidadResponse.status === 'fulfilled') {
                setDisponibilidad(disponibilidadResponse.value.data || []);
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

    const lotesDelProducto = useMemo(() => {
        if (!form.producto_id) {
            return lotes;
        }

        return lotes.filter((lote) => Number(lote.producto_id) === Number(form.producto_id));
    }, [lotes, form.producto_id]);

    const reservasFiltradas = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return reservas.filter((reserva) => {
            const coincideEstado = estadoFiltro ? reserva.estado === estadoFiltro : true;

            const coincideBusqueda = !term || [
                getCodigoReserva(reserva),
                reserva.estado,
                reserva.referencia,
                reserva.motivo,
                reserva.observacion,
            ].some((value) => String(value || '').toLowerCase().includes(term));

            return coincideEstado && coincideBusqueda;
        });
    }, [reservas, estadoFiltro, busqueda]);

    const reservasActivas = useMemo(() => {
        return reservas.filter((reserva) => ['ACTIVA', 'ACTIVA_RESERVA', 'PARCIAL'].includes(reserva.estado)).length;
    }, [reservas]);

    const handleChange = (event) => {
        const { name, value } = event.target;

        setForm((current) => ({
            ...current,
            [name]: value,
            ...(name === 'producto_id' ? { lote_id: '' } : {}),
        }));
    };

    const limpiarFormulario = () => {
        setForm(initialForm);
        setError(null);
    };

    const crearReserva = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            const detalle = {
                producto_id: Number(form.producto_id),
                bodega_id: Number(form.bodega_id),
                cantidad: Number(form.cantidad),
            };

            if (form.lote_id) {
                detalle.lote_id = Number(form.lote_id);
            }

            const payload = {
                referencia: form.referencia || null,
                motivo: form.motivo || 'reserva_comercial',
                observacion: form.observacion || null,
                fecha_expiracion: form.fecha_expiracion || null,
                detalles: [detalle],
            };

            await inventarioApi.reservas.crear(payload);

            await Swal.fire({
                icon: 'success',
                title: 'Reserva creada',
                text: 'La reserva fue registrada correctamente. El stock físico no fue descontado.',
                confirmButtonColor: '#10b981',
            });

            invalidarProductos();
            invalidarLotes();
            limpiarFormulario();
            setMostrarFormulario(false);
            await cargarDatos(true);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    const ejecutarAccion = async (reserva, accion) => {
        const textos = {
            cancelar: {
                title: 'Cancelar reserva',
                text: 'La reserva será cancelada y dejará de comprometer disponibilidad.',
                success: 'La reserva fue cancelada correctamente.',
            },
            liberar: {
                title: 'Liberar reserva',
                text: 'Se liberará la disponibilidad comprometida de esta reserva.',
                success: 'La reserva fue liberada correctamente.',
            },
            consumir: {
                title: 'Consumir reserva',
                text: 'Esta acción generará una salida real de inventario mediante el flujo backend.',
                success: 'La reserva fue consumida correctamente y se generó el movimiento real.',
            },
        };

        const config = textos[accion];

        const confirm = await Swal.fire({
            icon: accion === 'consumir' ? 'warning' : 'question',
            title: config.title,
            text: config.text,
            showCancelButton: true,
            confirmButtonText: 'Confirmar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: accion === 'cancelar' ? '#ef4444' : '#10b981',
        });

        if (!confirm.isConfirmed) {
            return;
        }

        try {
            if (accion === 'cancelar') {
                await inventarioApi.reservas.cancelar(reserva.id, {
                    observacion: 'Cancelación desde frontend de inventario',
                });
            }

            if (accion === 'liberar') {
                await inventarioApi.reservas.liberar(reserva.id, {
                    observacion: 'Liberación desde frontend de inventario',
                });
            }

            if (accion === 'consumir') {
                await inventarioApi.reservas.consumir(reserva.id, {
                    referencia: `CONS-${getCodigoReserva(reserva)}`,
                    motivo: 'consumo_reserva',
                    observacion: 'Consumo generado desde frontend de inventario',
                });
            }

            await Swal.fire({
                icon: 'success',
                title: 'Operación realizada',
                text: config.success,
                confirmButtonColor: '#10b981',
            });

            invalidarProductos();
            invalidarLotes();
            await cargarDatos(true);
        } catch (err) {
            await Swal.fire({
                icon: 'error',
                title: 'No se pudo completar la acción',
                text: err?.response?.data?.message || err?.message || 'Revise permisos, estado de la reserva o disponibilidad.',
                confirmButtonColor: '#ef4444',
            });
        }
    };

    if (loading) {
        return <LoadingState text="Cargando reservas y disponibilidad..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Reservas y Disponibilidad"
                description="Control de stock físico separado de stock comprometido. Crear una reserva no descuenta stock real; solo compromete disponibilidad."
                actions={(
                    <>
                        <SecondaryButton onClick={() => cargarDatos(true)}>
                            <i className="fas fa-rotate-right"></i>
                            Actualizar
                        </SecondaryButton>

                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)}>
                            <i className={mostrarFormulario ? 'fas fa-xmark' : 'fas fa-plus'}></i>
                            {mostrarFormulario ? 'Cerrar formulario' : 'Nueva reserva'}
                        </PrimaryButton>
                    </>
                )}
            />

            <AlertBox tone="blue">
                Regla clave: stock disponible = stock físico - stock reservado activo. Las reservas no modifican stock físico hasta consumirse.
            </AlertBox>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <p className="text-xs font-black text-slate-400 uppercase tracking-widest">Reservas totales</p>
                    <h3 className="text-3xl font-black text-slate-800 mt-2">{formatNumber(reservas.length)}</h3>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <p className="text-xs font-black text-slate-400 uppercase tracking-widest">Reservas activas</p>
                    <h3 className="text-3xl font-black text-emerald-600 mt-2">{formatNumber(reservasActivas)}</h3>
                </div>

                <div className="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                    <p className="text-xs font-black text-slate-400 uppercase tracking-widest">Registros disponibilidad</p>
                    <h3 className="text-3xl font-black text-blue-600 mt-2">{formatNumber(disponibilidad.length)}</h3>
                </div>
            </div>

            {mostrarFormulario && (
                <Panel title="Crear reserva" subtitle="Reserva una cantidad sin descontar stock físico. El consumo genera la salida real.">
                    <ErrorNotice error={error} />

                    <form onSubmit={crearReserva} className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                        <Field label="Producto">
                            <select
                                name="producto_id"
                                value={form.producto_id}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                required
                            >
                                <option value="">Seleccionar producto</option>
                                {productos.map((producto) => (
                                    <option key={producto.id} value={producto.id}>
                                        {producto.sku ? `${producto.sku} - ${producto.nombre}` : producto.nombre}
                                    </option>
                                ))}
                            </select>
                        </Field>

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

                        <Field label="Lote opcional">
                            <select
                                name="lote_id"
                                value={form.lote_id}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                            >
                                <option value="">Sin lote</option>
                                {lotesDelProducto.map((lote) => (
                                    <option key={lote.id} value={lote.id}>
                                        {lote.codigo_lote || lote.codigo || `Lote #${lote.id}`}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Cantidad">
                            <input
                                type="number"
                                name="cantidad"
                                value={form.cantidad}
                                onChange={handleChange}
                                min="1"
                                step="1"
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                required
                            />
                        </Field>

                        <Field label="Referencia">
                            <input
                                type="text"
                                name="referencia"
                                value={form.referencia}
                                onChange={handleChange}
                                className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                                placeholder="PED-DEMO-001"
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

                        <Field label="Expiración">
                            <input
                                type="datetime-local"
                                name="fecha_expiracion"
                                value={form.fecha_expiracion}
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
                                    placeholder="Detalle comercial u operacional de la reserva"
                                />
                            </Field>
                        </div>

                        <div className="md:col-span-2 xl:col-span-4 flex flex-wrap justify-end gap-3">
                            <SecondaryButton type="button" onClick={limpiarFormulario}>
                                Limpiar
                            </SecondaryButton>

                            <PrimaryButton type="submit" disabled={saving}>
                                <i className="fas fa-save"></i>
                                {saving ? 'Guardando...' : 'Crear reserva'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel
                title="Listado de reservas"
                subtitle="Reservas activas, consumidas, liberadas o canceladas."
                actions={(
                    <div className="flex flex-col md:flex-row gap-3 w-full md:w-auto">
                        <select
                            value={estadoFiltro}
                            onChange={(event) => setEstadoFiltro(event.target.value)}
                            className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                        >
                            <option value="">Todos los estados</option>
                            <option value="ACTIVA">Activa</option>
                            <option value="ACTIVA_RESERVA">Activa reserva</option>
                            <option value="CONSUMIDA">Consumida</option>
                            <option value="LIBERADA">Liberada</option>
                            <option value="CANCELADA">Cancelada</option>
                        </select>

                        <input
                            type="text"
                            value={busqueda}
                            onChange={(event) => setBusqueda(event.target.value)}
                            className="w-full md:w-80 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none"
                            placeholder="Buscar reserva, referencia o motivo..."
                        />
                    </div>
                )}
            >
                {reservasFiltradas.length === 0 ? (
                    <EmptyState
                        title="Sin reservas"
                        description="Crea una reserva para comprometer disponibilidad sin modificar stock físico."
                        icon="fas fa-lock"
                    />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código</Th>
                                <Th>Estado</Th>
                                <Th>Referencia</Th>
                                <Th>Motivo</Th>
                                <Th>Fecha</Th>
                                <Th>Expiración</Th>
                                <Th align="center">Acciones</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {reservasFiltradas.map((reserva) => {
                                const puedeOperar = ['ACTIVA', 'ACTIVA_RESERVA', 'PARCIAL'].includes(reserva.estado);

                                return (
                                    <tr key={reserva.id} className="hover:bg-slate-50/70 transition-colors">
                                        <Td className="font-black text-slate-800">
                                            {getCodigoReserva(reserva)}
                                        </Td>

                                        <Td>
                                            <EstadoBadge value={reserva.estado} />
                                        </Td>

                                        <Td className="text-slate-500 font-semibold">
                                            {reserva.referencia || '-'}
                                        </Td>

                                        <Td className="text-slate-500">
                                            {reserva.motivo || '-'}
                                        </Td>

                                        <Td className="text-slate-500">
                                            {formatDate(reserva.fecha_reserva || reserva.created_at)}
                                        </Td>

                                        <Td className="text-slate-500">
                                            {formatDate(reserva.fecha_expiracion)}
                                        </Td>

                                        <Td align="center">
                                            <div className="flex flex-wrap justify-center gap-2">
                                                <button
                                                    type="button"
                                                    disabled={!puedeOperar}
                                                    onClick={() => ejecutarAccion(reserva, 'liberar')}
                                                    className="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-slate-700 text-xs font-black"
                                                >
                                                    Liberar
                                                </button>

                                                <button
                                                    type="button"
                                                    disabled={!puedeOperar}
                                                    onClick={() => ejecutarAccion(reserva, 'consumir')}
                                                    className="px-3 py-1.5 rounded-lg bg-emerald-100 hover:bg-emerald-200 disabled:opacity-40 text-emerald-700 text-xs font-black"
                                                >
                                                    Consumir
                                                </button>

                                                <button
                                                    type="button"
                                                    disabled={!puedeOperar}
                                                    onClick={() => ejecutarAccion(reserva, 'cancelar')}
                                                    className="px-3 py-1.5 rounded-lg bg-rose-100 hover:bg-rose-200 disabled:opacity-40 text-rose-700 text-xs font-black"
                                                >
                                                    Cancelar
                                                </button>
                                            </div>
                                        </Td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </TableShell>
                )}
            </Panel>
        </div>
    );
};

export default ReservasInventario;