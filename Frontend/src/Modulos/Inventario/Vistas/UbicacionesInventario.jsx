import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';
import {
    EmptyState,
    ErrorNotice,
    EstadoBadge,
    Field,
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

const tiposUbicacion = ['ZONA', 'PASILLO', 'ESTANTE', 'NIVEL', 'POSICION', 'UBICACION'];

const initialForm = {
    bodega_id: '',
    ubicacion_padre_id: '',
    codigo: '',
    nombre: '',
    tipo: 'UBICACION',
    pasillo: '',
    estante: '',
    nivel: '',
    posicion: '',
    capacidad_maxima: '',
    activo: true,
};

const initialMove = {
    producto_id: '',
    bodega_id: '',
    ubicacion_origen_id: '',
    ubicacion_destino_id: '',
    lote_id: '',
    cantidad: '',
    estado_origen: 'DISPONIBLE',
    estado_destino: 'DISPONIBLE',
    motivo: 'putaway',
    referencia: '',
    observacion: '',
};

const inputClass = 'w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-emerald-200 focus:border-emerald-400 outline-none';

const normalizarLista = (response) => {
    const data = response?.data || [];

    if (Array.isArray(data)) {
        return data;
    }

    return data?.data || [];
};

const getBodegaNombre = (bodegas, id) => {
    const bodega = bodegas.find((item) => Number(item.id) === Number(id));
    return bodega?.nombre || `Bodega #${id || '-'}`;
};

const getUbicacionNombre = (ubicaciones, id) => {
    const ubicacion = ubicaciones.find((item) => Number(item.id) === Number(id));
    return ubicacion ? `${ubicacion.codigo} · ${ubicacion.nombre}` : `Ubicación #${id || '-'}`;
};

const UbicacionesInventario = () => {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [moving, setMoving] = useState(false);
    const [error, setError] = useState(null);
    const [mostrarFormulario, setMostrarFormulario] = useState(false);
    const [mostrarMovimiento, setMostrarMovimiento] = useState(false);
    const [form, setForm] = useState(initialForm);
    const [moveForm, setMoveForm] = useState(initialMove);
    const [busqueda, setBusqueda] = useState('');
    const [filtroBodega, setFiltroBodega] = useState('');
    const [stockUbicaciones, setStockUbicaciones] = useState([]);

    const {
        bodegas,
        productos,
        ubicaciones,
        cargarBodegasCache,
        cargarProductosCache,
        cargarUbicacionesCache,
        invalidarUbicaciones,
        invalidarTodoInventario,
    } = useInventarioData();

    const cargarVista = async (force = false) => {
        try {
            setLoading(true);
            setError(null);

            await Promise.all([
                cargarBodegasCache({ force }),
                cargarProductosCache({ force }),
                cargarUbicacionesCache({ force }),
            ]);

            const stockResponse = await inventarioApi.stockUbicaciones.listar({
                bodega_id: filtroBodega || undefined,
                per_page: 100,
            });

            setStockUbicaciones(normalizarLista(stockResponse));
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        cargarVista();
    }, []);

    const ubicacionesFiltradas = useMemo(() => {
        const term = busqueda.trim().toLowerCase();

        return ubicaciones.filter((ubicacion) => {
            if (filtroBodega && Number(ubicacion.bodega_id) !== Number(filtroBodega)) {
                return false;
            }

            if (!term) {
                return true;
            }

            return [
                ubicacion.codigo,
                ubicacion.nombre,
                ubicacion.tipo,
                ubicacion.pasillo,
                ubicacion.estante,
                ubicacion.nivel,
                ubicacion.posicion,
            ].some((value) => String(value || '').toLowerCase().includes(term));
        });
    }, [busqueda, filtroBodega, ubicaciones]);

    const stockFiltrado = useMemo(() => {
        return stockUbicaciones.filter((item) => {
            if (filtroBodega && Number(item.bodega_id) !== Number(filtroBodega)) {
                return false;
            }

            return true;
        });
    }, [filtroBodega, stockUbicaciones]);

    const resumenStock = useMemo(() => {
        return stockFiltrado.reduce((acc, item) => ({
            fisico: acc.fisico + Number(item.stock_actual || 0),
            reservado: acc.reservado + Number(item.stock_reservado || 0),
            cuarentena: acc.cuarentena + Number(item.stock_cuarentena || 0),
            bloqueado: acc.bloqueado + Number(item.stock_bloqueado || 0),
            transito: acc.transito + Number(item.stock_en_transito || 0),
            disponible: acc.disponible + Number(item.stock_disponible || 0),
        }), {
            fisico: 0,
            reservado: 0,
            cuarentena: 0,
            bloqueado: 0,
            transito: 0,
            disponible: 0,
        });
    }, [stockFiltrado]);

    const ubicacionesDeBodega = useMemo(() => {
        const bodegaId = moveForm.bodega_id || form.bodega_id || filtroBodega;

        if (!bodegaId) {
            return ubicaciones;
        }

        return ubicaciones.filter((ubicacion) => Number(ubicacion.bodega_id) === Number(bodegaId));
    }, [filtroBodega, form.bodega_id, moveForm.bodega_id, ubicaciones]);

    const handleFormChange = (event) => {
        const { name, value, type, checked } = event.target;

        setForm((current) => ({
            ...current,
            [name]: type === 'checkbox' ? checked : value,
        }));
    };

    const handleMoveChange = (event) => {
        const { name, value } = event.target;

        setMoveForm((current) => ({
            ...current,
            [name]: value,
            ...(name === 'bodega_id' ? { ubicacion_origen_id: '', ubicacion_destino_id: '' } : {}),
        }));
    };

    const guardarUbicacion = async (event) => {
        event.preventDefault();

        try {
            setSaving(true);
            setError(null);

            await inventarioApi.ubicaciones.crear({
                ...form,
                ubicacion_padre_id: form.ubicacion_padre_id || null,
                pasillo: form.pasillo || null,
                estante: form.estante || null,
                nivel: form.nivel || null,
                posicion: form.posicion || null,
                capacidad_maxima: form.capacidad_maxima || null,
            });

            await Swal.fire({
                icon: 'success',
                title: 'Ubicación creada',
                text: 'La ubicación fue registrada correctamente.',
                confirmButtonColor: '#10b981',
            });

            setForm(initialForm);
            setMostrarFormulario(false);
            invalidarUbicaciones();
            await cargarVista(true);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setSaving(false);
        }
    };

    const moverStock = async (event) => {
        event.preventDefault();

        try {
            setMoving(true);
            setError(null);

            await inventarioApi.stockUbicaciones.mover({
                producto_id: moveForm.producto_id,
                bodega_origen_id: moveForm.bodega_id,
                bodega_destino_id: moveForm.bodega_id,
                ubicacion_origen_id: moveForm.ubicacion_origen_id,
                ubicacion_destino_id: moveForm.ubicacion_destino_id,
                lote_id: moveForm.lote_id || null,
                cantidad: moveForm.cantidad,
                estado_stock_origen: moveForm.estado_origen,
                estado_stock_destino: moveForm.estado_destino,
                referencia: moveForm.referencia || null,
                observacion: moveForm.observacion || null,
            });

            await Swal.fire({
                icon: 'success',
                title: 'Movimiento interno registrado',
                text: 'El stock fue movido entre ubicaciones/estados correctamente.',
                confirmButtonColor: '#10b981',
            });

            setMoveForm(initialMove);
            setMostrarMovimiento(false);
            invalidarTodoInventario();
            await cargarVista(true);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setMoving(false);
        }
    };

    if (loading) {
        return <LoadingState text="Cargando ubicaciones y disponibilidad avanzada..." />;
    }

    return (
        <div className="space-y-8">
            <PageHeader
                title="Ubicaciones y disponibilidad avanzada"
                description="Control logístico por bodega, ubicación física, estado operacional y stock disponible real. Esta vista no emite DTE ni integra facturación."
                actions={(
                    <>
                        <SecondaryButton onClick={() => cargarVista(true)}>
                            <i className="fas fa-rotate-right"></i>
                            Actualizar
                        </SecondaryButton>

                        <SecondaryButton onClick={() => setMostrarMovimiento((value) => !value)}>
                            <i className="fas fa-route"></i>
                            {mostrarMovimiento ? 'Cerrar movimiento' : 'Mover stock'}
                        </SecondaryButton>

                        <PrimaryButton onClick={() => setMostrarFormulario((value) => !value)}>
                            <i className={mostrarFormulario ? 'fas fa-xmark' : 'fas fa-plus'}></i>
                            {mostrarFormulario ? 'Cerrar formulario' : 'Nueva ubicación'}
                        </PrimaryButton>
                    </>
                )}
            />

            <ErrorNotice error={error} />

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-5">
                <StatCard title="Físico" value={formatNumber(resumenStock.fisico, 2)} icon="fas fa-boxes-stacked" tone="blue" />
                <StatCard title="Disponible" value={formatNumber(resumenStock.disponible, 2)} icon="fas fa-check" tone="emerald" />
                <StatCard title="Reservado" value={formatNumber(resumenStock.reservado, 2)} icon="fas fa-lock" tone="amber" />
                <StatCard title="Cuarentena" value={formatNumber(resumenStock.cuarentena, 2)} icon="fas fa-triangle-exclamation" tone="rose" />
                <StatCard title="Bloqueado" value={formatNumber(resumenStock.bloqueado, 2)} icon="fas fa-ban" tone="rose" />
                <StatCard title="Tránsito" value={formatNumber(resumenStock.transito, 2)} icon="fas fa-truck-ramp-box" tone="indigo" />
            </div>

            {mostrarFormulario && (
                <Panel title="Crear ubicación física" subtitle="Define zonas, pasillos, estantes, niveles o posiciones dentro de una bodega.">
                    <form onSubmit={guardarUbicacion} className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                        <Field label="Bodega">
                            <select name="bodega_id" value={form.bodega_id} onChange={handleFormChange} className={inputClass} required>
                                <option value="">Seleccionar...</option>
                                {bodegas.map((bodega) => (
                                    <option key={bodega.id} value={bodega.id}>{bodega.codigo} · {bodega.nombre}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Ubicación padre">
                            <select name="ubicacion_padre_id" value={form.ubicacion_padre_id} onChange={handleFormChange} className={inputClass}>
                                <option value="">Sin padre</option>
                                {ubicacionesDeBodega.map((ubicacion) => (
                                    <option key={ubicacion.id} value={ubicacion.id}>{ubicacion.codigo} · {ubicacion.nombre}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Código">
                            <input name="codigo" value={form.codigo} onChange={handleFormChange} className={inputClass} placeholder="PAS-A-EST-01" required />
                        </Field>

                        <Field label="Nombre">
                            <input name="nombre" value={form.nombre} onChange={handleFormChange} className={inputClass} placeholder="Pasillo A / Estante 01" required />
                        </Field>

                        <Field label="Tipo">
                            <select name="tipo" value={form.tipo} onChange={handleFormChange} className={inputClass}>
                                {tiposUbicacion.map((tipo) => <option key={tipo} value={tipo}>{tipo}</option>)}
                            </select>
                        </Field>

                        <Field label="Pasillo">
                            <input name="pasillo" value={form.pasillo} onChange={handleFormChange} className={inputClass} placeholder="A" />
                        </Field>

                        <Field label="Estante">
                            <input name="estante" value={form.estante} onChange={handleFormChange} className={inputClass} placeholder="01" />
                        </Field>

                        <Field label="Nivel">
                            <input name="nivel" value={form.nivel} onChange={handleFormChange} className={inputClass} placeholder="02" />
                        </Field>

                        <Field label="Posición">
                            <input name="posicion" value={form.posicion} onChange={handleFormChange} className={inputClass} placeholder="03" />
                        </Field>

                        <Field label="Capacidad máxima">
                            <input type="number" min="0" step="0.01" name="capacidad_maxima" value={form.capacidad_maxima} onChange={handleFormChange} className={inputClass} />
                        </Field>

                        <label className="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 cursor-pointer hover:bg-slate-50">
                            <input type="checkbox" name="activo" checked={form.activo} onChange={handleFormChange} className="w-4 h-4 text-emerald-500 rounded" />
                            <span className="text-sm font-black text-slate-700">Ubicación activa</span>
                        </label>

                        <div className="xl:col-span-4 flex justify-end">
                            <PrimaryButton type="submit" disabled={saving}>
                                <i className="fas fa-save"></i>
                                {saving ? 'Guardando...' : 'Guardar ubicación'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            {mostrarMovimiento && (
                <Panel title="Movimiento interno / putaway" subtitle="Mueve stock entre ubicaciones y estados operacionales sin tocar DTE/SII ni contabilidad automática.">
                    <form onSubmit={moverStock} className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                        <Field label="Producto">
                            <select name="producto_id" value={moveForm.producto_id} onChange={handleMoveChange} className={inputClass} required>
                                <option value="">Seleccionar...</option>
                                {productos.map((producto) => (
                                    <option key={producto.id} value={producto.id}>{producto.sku} · {producto.nombre}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Bodega">
                            <select name="bodega_id" value={moveForm.bodega_id} onChange={handleMoveChange} className={inputClass} required>
                                <option value="">Seleccionar...</option>
                                {bodegas.map((bodega) => (
                                    <option key={bodega.id} value={bodega.id}>{bodega.codigo} · {bodega.nombre}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Ubicación origen">
                            <select name="ubicacion_origen_id" value={moveForm.ubicacion_origen_id} onChange={handleMoveChange} className={inputClass} required>
                                <option value="">Seleccionar...</option>
                                {ubicacionesDeBodega.map((ubicacion) => (
                                    <option key={ubicacion.id} value={ubicacion.id}>{ubicacion.codigo} · {ubicacion.nombre}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Ubicación destino">
                            <select name="ubicacion_destino_id" value={moveForm.ubicacion_destino_id} onChange={handleMoveChange} className={inputClass} required>
                                <option value="">Seleccionar...</option>
                                {ubicacionesDeBodega.map((ubicacion) => (
                                    <option key={ubicacion.id} value={ubicacion.id}>{ubicacion.codigo} · {ubicacion.nombre}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Estado origen">
                            <select name="estado_origen" value={moveForm.estado_origen} onChange={handleMoveChange} className={inputClass}>
                                {['DISPONIBLE', 'CUARENTENA', 'BLOQUEADO', 'EN_TRANSITO_INTERNO'].map((estado) => <option key={estado} value={estado}>{estado}</option>)}
                            </select>
                        </Field>

                        <Field label="Estado destino">
                            <select name="estado_destino" value={moveForm.estado_destino} onChange={handleMoveChange} className={inputClass}>
                                {['DISPONIBLE', 'CUARENTENA', 'BLOQUEADO', 'EN_TRANSITO_INTERNO'].map((estado) => <option key={estado} value={estado}>{estado}</option>)}
                            </select>
                        </Field>

                        <Field label="Cantidad">
                            <input type="number" min="0.01" step="0.01" name="cantidad" value={moveForm.cantidad} onChange={handleMoveChange} className={inputClass} required />
                        </Field>

                        <Field label="Referencia">
                            <input name="referencia" value={moveForm.referencia} onChange={handleMoveChange} className={inputClass} placeholder="PUT-001" />
                        </Field>

                        <div className="xl:col-span-4 flex justify-end">
                            <PrimaryButton type="submit" disabled={moving}>
                                <i className="fas fa-right-left"></i>
                                {moving ? 'Moviendo...' : 'Confirmar movimiento'}
                            </PrimaryButton>
                        </div>
                    </form>
                </Panel>
            )}

            <Panel title="Filtros" subtitle="Filtra ubicaciones y disponibilidad por bodega o texto.">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <Field label="Bodega">
                        <select value={filtroBodega} onChange={(event) => setFiltroBodega(event.target.value)} className={inputClass}>
                            <option value="">Todas</option>
                            {bodegas.map((bodega) => (
                                <option key={bodega.id} value={bodega.id}>{bodega.codigo} · {bodega.nombre}</option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Buscar ubicación">
                        <input value={busqueda} onChange={(event) => setBusqueda(event.target.value)} className={inputClass} placeholder="Código, nombre, pasillo, estante..." />
                    </Field>
                </div>
            </Panel>

            <Panel title="Ubicaciones físicas" subtitle="Estructura jerárquica por bodega.">
                {ubicacionesFiltradas.length === 0 ? (
                    <EmptyState title="Sin ubicaciones" description="Aún no hay ubicaciones para los filtros seleccionados." icon="fas fa-location-dot" />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Código</Th>
                                <Th>Nombre</Th>
                                <Th>Bodega</Th>
                                <Th>Tipo</Th>
                                <Th>Padre</Th>
                                <Th>Estado</Th>
                                <Th align="right">Capacidad</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {ubicacionesFiltradas.map((ubicacion) => (
                                <tr key={ubicacion.id} className="hover:bg-slate-50/80 transition-colors">
                                    <Td className="font-black text-slate-800">{ubicacion.codigo}</Td>
                                    <Td>{ubicacion.nombre}</Td>
                                    <Td>{getBodegaNombre(bodegas, ubicacion.bodega_id)}</Td>
                                    <Td><EstadoBadge value={ubicacion.tipo} /></Td>
                                    <Td>{ubicacion.ubicacion_padre_id ? getUbicacionNombre(ubicaciones, ubicacion.ubicacion_padre_id) : '-'}</Td>
                                    <Td><EstadoBadge value={ubicacion.activo ? 'ACTIVA' : 'INACTIVA'} /></Td>
                                    <Td align="right">{ubicacion.capacidad_maxima ? formatNumber(ubicacion.capacidad_maxima, 2) : '-'}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </TableShell>
                )}
            </Panel>

            <Panel title="Stock por ubicación" subtitle="Disponibilidad avanzada por producto, ubicación y estado operacional.">
                {stockFiltrado.length === 0 ? (
                    <EmptyState title="Sin stock por ubicación" description="Registra entradas con ubicación o movimientos internos para poblar esta vista." icon="fas fa-box-open" />
                ) : (
                    <TableShell>
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Producto</Th>
                                <Th>Bodega</Th>
                                <Th>Ubicación</Th>
                                <Th align="right">Físico</Th>
                                <Th align="right">Disponible</Th>
                                <Th align="right">Reservado</Th>
                                <Th align="right">Cuarentena</Th>
                                <Th align="right">Bloqueado</Th>
                                <Th align="right">Tránsito</Th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {stockFiltrado.map((item) => (
                                <tr key={item.id} className="hover:bg-slate-50/80 transition-colors">
                                    <Td className="font-black text-slate-800">{item.producto?.nombre || `Producto #${item.producto_id}`}</Td>
                                    <Td>{item.bodega?.nombre || getBodegaNombre(bodegas, item.bodega_id)}</Td>
                                    <Td>{item.ubicacion?.codigo ? `${item.ubicacion.codigo} · ${item.ubicacion.nombre}` : getUbicacionNombre(ubicaciones, item.ubicacion_id)}</Td>
                                    <Td align="right">{formatNumber(item.stock_actual, 2)}</Td>
                                    <Td align="right" className="font-black text-emerald-700">{formatNumber(item.stock_disponible, 2)}</Td>
                                    <Td align="right">{formatNumber(item.stock_reservado, 2)}</Td>
                                    <Td align="right">{formatNumber(item.stock_cuarentena, 2)}</Td>
                                    <Td align="right">{formatNumber(item.stock_bloqueado, 2)}</Td>
                                    <Td align="right">{formatNumber(item.stock_en_transito, 2)}</Td>
                                </tr>
                            ))}
                        </tbody>
                    </TableShell>
                )}
            </Panel>
        </div>
    );
};

export default UbicacionesInventario;
