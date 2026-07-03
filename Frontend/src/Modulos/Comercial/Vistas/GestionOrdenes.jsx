import React, { useState, useEffect, useCallback } from 'react';
import { ordenesCompra } from '../Servicios/comercialApi';
import { TablaSkeleton } from '../../../Componentes/Skeleton';
import { EstadoVacio } from '../../../Componentes/EstadoVacio';
import { formatearMoneda } from '../../../Utilidades/formato';

const clpFmt = { format: formatearMoneda };

const ESTADOS = ['BORRADOR', 'ENVIADA', 'RECIBIDA_PARCIAL', 'RECIBIDA_TOTAL', 'ANULADA'];

const COLORES_ESTADO = {
    BORRADOR: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
    ENVIADA: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    RECIBIDA_PARCIAL: 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300',
    RECIBIDA_TOTAL: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
    ANULADA: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
};

const ETIQUETAS_ESTADO = {
    BORRADOR: 'Borrador',
    ENVIADA: 'Enviada',
    RECIBIDA_PARCIAL: 'Rec. Parcial',
    RECIBIDA_TOTAL: 'Rec. Total',
    ANULADA: 'Anulada',
};

const inputCls =
    'w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none';

const DETALLE_VACIO = () => ({
    producto_descripcion: '',
    codigo_producto: '',
    cantidad: '',
    unidad: 'UN',
    precio_unitario: '',
    subtotal: '',
});

const GestionOrdenes = () => {
    const [lista, setLista] = useState([]);
    const [cargando, setCargando] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [mensaje, setMensaje] = useState(null);

    // Filtros
    const [filtroEstado, setFiltroEstado] = useState('');
    const [filtroProveedor, setFiltroProveedor] = useState('');

    // Modal creación
    const [modalCrear, setModalCrear] = useState(false);
    const [form, setForm] = useState({
        fecha_emision: new Date().toISOString().split('T')[0],
        fecha_entrega_esperada: '',
        proveedor_id: '',
        notas: '',
        detalles: [DETALLE_VACIO()],
    });

    // Modal detalle/recepción
    const [ocSeleccionada, setOcSeleccionada] = useState(null);
    const [modalDetalle, setModalDetalle] = useState(false);
    const [cargandoDetalle, setCargandoDetalle] = useState(false);
    const [recepcionesPendientes, setRecepcionesPendientes] = useState({});

    const mostrarMensaje = (tipo, texto) => {
        setMensaje({ tipo, texto });
        setTimeout(() => setMensaje(null), 5000);
    };

    const cargarLista = useCallback(async () => {
        setCargando(true);
        try {
            const params = {};
            if (filtroEstado) params.estado = filtroEstado;
            if (filtroProveedor) params.proveedor = filtroProveedor;
            const resp = await ordenesCompra.listar(params);
            setLista(resp?.data ?? []);
        } catch (_) {
            // el interceptor global muestra el toast
        } finally {
            setCargando(false);
        }
    }, [filtroEstado, filtroProveedor]);

    useEffect(() => {
        cargarLista();
    }, [cargarLista]);

    const actualizarDetalle = (idx, campo, valor) => {
        setForm((prev) => {
            const detalles = [...prev.detalles];
            detalles[idx] = { ...detalles[idx], [campo]: valor };
            // Recalcular subtotal al cambiar cantidad o precio
            if (campo === 'cantidad' || campo === 'precio_unitario') {
                const cant = parseFloat(campo === 'cantidad' ? valor : detalles[idx].cantidad) || 0;
                const precio = parseFloat(campo === 'precio_unitario' ? valor : detalles[idx].precio_unitario) || 0;
                detalles[idx].subtotal = (cant * precio).toFixed(2);
            }
            return { ...prev, detalles };
        });
    };

    const agregarDetalle = () => {
        setForm((prev) => ({ ...prev, detalles: [...prev.detalles, DETALLE_VACIO()] }));
    };

    const eliminarDetalle = (idx) => {
        setForm((prev) => ({
            ...prev,
            detalles: prev.detalles.filter((_, i) => i !== idx),
        }));
    };

    const handleCrear = async (e) => {
        e.preventDefault();
        if (form.detalles.length === 0) {
            mostrarMensaje('error', 'Agrega al menos un ítem a la OC.');
            return;
        }
        setEnviando(true);
        try {
            const payload = {
                ...form,
                proveedor_id: form.proveedor_id ? parseInt(form.proveedor_id, 10) : null,
                fecha_entrega_esperada: form.fecha_entrega_esperada || null,
                detalles: form.detalles.map((d) => ({
                    ...d,
                    cantidad: parseFloat(d.cantidad),
                    precio_unitario: parseFloat(d.precio_unitario),
                    subtotal: parseFloat(d.subtotal),
                    codigo_producto: d.codigo_producto || null,
                })),
            };
            await ordenesCompra.crear(payload);
            mostrarMensaje('exito', 'Orden de compra creada correctamente.');
            setModalCrear(false);
            setForm({
                fecha_emision: new Date().toISOString().split('T')[0],
                fecha_entrega_esperada: '',
                proveedor_id: '',
                notas: '',
                detalles: [DETALLE_VACIO()],
            });
            await cargarLista();
        } catch (err) {
            const msg = err?.response?.data?.message ?? err?.response?.data?.mensaje ?? 'Error al crear la OC.';
            mostrarMensaje('error', msg);
        } finally {
            setEnviando(false);
        }
    };

    const abrirDetalle = async (oc) => {
        setCargandoDetalle(true);
        setModalDetalle(true);
        try {
            const resp = await ordenesCompra.obtener(oc.id);
            setOcSeleccionada(resp?.data ?? oc);
            const iniciales = {};
            (resp?.data?.detalles ?? []).forEach((d) => {
                iniciales[d.id] = '';
            });
            setRecepcionesPendientes(iniciales);
        } catch (_) {
            setOcSeleccionada(oc);
        } finally {
            setCargandoDetalle(false);
        }
    };

    const handleRecibir = async () => {
        if (!ocSeleccionada) return;
        const recepciones = Object.entries(recepcionesPendientes)
            .filter(([, v]) => v !== '' && parseFloat(v) > 0)
            .map(([id, cant]) => ({ detalle_id: parseInt(id, 10), cantidad_recibida: parseFloat(cant) }));

        if (recepciones.length === 0) {
            mostrarMensaje('error', 'Ingresa al menos una cantidad recibida.');
            return;
        }
        setEnviando(true);
        try {
            const resp = await ordenesCompra.recibirMercaderia(ocSeleccionada.id, { recepciones });
            setOcSeleccionada(resp?.data ?? ocSeleccionada);
            mostrarMensaje('exito', 'Recepción registrada.');
            await cargarLista();
        } catch (err) {
            const msg = err?.response?.data?.message ?? err?.response?.data?.mensaje ?? 'Error al registrar recepción.';
            mostrarMensaje('error', msg);
        } finally {
            setEnviando(false);
        }
    };

    const handleAnular = async (id) => {
        if (!window.confirm('¿Confirmas anular esta OC?')) return;
        try {
            await ordenesCompra.anular(id);
            mostrarMensaje('exito', 'OC anulada.');
            setModalDetalle(false);
            await cargarLista();
        } catch (err) {
            const msg = err?.response?.data?.message ?? err?.response?.data?.mensaje ?? 'Error al anular.';
            mostrarMensaje('error', msg);
        }
    };

    const subtotalForm = form.detalles.reduce((acc, d) => acc + (parseFloat(d.subtotal) || 0), 0);
    const impuestoForm = subtotalForm * 0.19;
    const totalForm = subtotalForm + impuestoForm;

    return (
        <div className="max-w-7xl mx-auto p-6 md:p-8">
            <header className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl md:text-3xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-3">
                        <i className="fas fa-file-invoice text-emerald-600" />
                        Órdenes de Compra
                    </h1>
                    <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Gestión de órdenes de compra a proveedores
                    </p>
                </div>
                <button
                    onClick={() => setModalCrear(true)}
                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm"
                >
                    <i className="fas fa-plus" />
                    Nueva OC
                </button>
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

            {/* Filtros */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 p-4 mb-6">
                <div className="flex flex-wrap gap-3">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Estado</label>
                        <select
                            value={filtroEstado}
                            onChange={(e) => setFiltroEstado(e.target.value)}
                            className={inputCls + ' w-auto min-w-[140px]'}
                        >
                            <option value="">Todos</option>
                            {ESTADOS.map((e) => (
                                <option key={e} value={e}>{ETIQUETAS_ESTADO[e]}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Proveedor</label>
                        <input
                            type="text"
                            value={filtroProveedor}
                            onChange={(e) => setFiltroProveedor(e.target.value)}
                            placeholder="Buscar proveedor..."
                            className={inputCls + ' w-auto min-w-[200px]'}
                        />
                    </div>
                    <div className="flex items-end">
                        <button
                            onClick={cargarLista}
                            className="px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 text-sm"
                        >
                            <i className="fas fa-search mr-1" /> Buscar
                        </button>
                    </div>
                </div>
            </div>

            {/* Tabla OCs */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h2 className="font-bold text-slate-900 dark:text-slate-100">Órdenes de compra</h2>
                    <button
                        onClick={cargarLista}
                        className="text-xs text-emerald-600 hover:text-emerald-800 font-semibold flex items-center gap-1"
                    >
                        <i className="fas fa-sync-alt" /> Actualizar
                    </button>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 z-10 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold">N° OC</th>
                                <th className="px-4 py-3 text-left font-semibold">Proveedor</th>
                                <th className="px-4 py-3 text-left font-semibold">F. Emisión</th>
                                <th className="px-4 py-3 text-left font-semibold">F. Entrega</th>
                                <th className="px-4 py-3 text-right font-semibold">Total</th>
                                <th className="px-4 py-3 text-center font-semibold">Estado</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                            {cargando ? (
                                <TablaSkeleton filas={6} columnas={6} />
                            ) : lista.length === 0 ? (
                                <EstadoVacio mensaje="Sin órdenes de compra registradas." />
                            ) : lista.map((oc) => (
                                <tr
                                    key={oc.id}
                                    onClick={() => abrirDetalle(oc)}
                                    className="hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer"
                                >
                                    <td className="px-4 py-3 font-mono font-semibold text-slate-900 dark:text-slate-100">
                                        {oc.numero_oc}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700 dark:text-slate-300">
                                        {oc.proveedor?.razon_social ?? <span className="text-slate-400 italic">Sin proveedor</span>}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600 dark:text-slate-400">
                                        {oc.fecha_emision ? new Date(oc.fecha_emision).toLocaleDateString('es-CL') : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600 dark:text-slate-400">
                                        {oc.fecha_entrega_esperada ? new Date(oc.fecha_entrega_esperada).toLocaleDateString('es-CL') : '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-slate-900 dark:text-slate-100">
                                        {clpFmt.format(parseFloat(oc.total ?? 0))}
                                    </td>
                                    <td className="px-4 py-3 text-center">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${COLORES_ESTADO[oc.estado] ?? ''}`}>
                                            {ETIQUETAS_ESTADO[oc.estado] ?? oc.estado}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Modal Crear OC */}
            {modalCrear && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                        <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                            <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100">Nueva Orden de Compra</h2>
                            <button
                                onClick={() => setModalCrear(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <i className="fas fa-times text-xl" />
                            </button>
                        </div>
                        <form onSubmit={handleCrear} className="p-6 space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                        Fecha emisión <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        value={form.fecha_emision}
                                        onChange={(e) => setForm((p) => ({ ...p, fecha_emision: e.target.value }))}
                                        className={inputCls}
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                        Fecha entrega esperada
                                    </label>
                                    <input
                                        type="date"
                                        value={form.fecha_entrega_esperada}
                                        onChange={(e) => setForm((p) => ({ ...p, fecha_entrega_esperada: e.target.value }))}
                                        className={inputCls}
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                        ID Proveedor (opcional)
                                    </label>
                                    <input
                                        type="number"
                                        value={form.proveedor_id}
                                        onChange={(e) => setForm((p) => ({ ...p, proveedor_id: e.target.value }))}
                                        placeholder="ID del proveedor"
                                        className={inputCls}
                                        min="1"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Notas</label>
                                    <input
                                        type="text"
                                        value={form.notas}
                                        onChange={(e) => setForm((p) => ({ ...p, notas: e.target.value }))}
                                        placeholder="Observaciones..."
                                        className={inputCls}
                                    />
                                </div>
                            </div>

                            {/* Detalles */}
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <h3 className="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide">Ítems</h3>
                                    <button
                                        type="button"
                                        onClick={agregarDetalle}
                                        className="text-xs text-emerald-600 hover:text-emerald-800 font-semibold flex items-center gap-1"
                                    >
                                        <i className="fas fa-plus" /> Agregar ítem
                                    </button>
                                </div>
                                <div className="space-y-3">
                                    {form.detalles.map((d, idx) => (
                                        <div key={idx} className="grid grid-cols-12 gap-2 items-end p-3 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700">
                                            <div className="col-span-4">
                                                <label className="block text-xs font-semibold text-slate-500 mb-0.5">Descripción *</label>
                                                <input
                                                    type="text"
                                                    value={d.producto_descripcion}
                                                    onChange={(e) => actualizarDetalle(idx, 'producto_descripcion', e.target.value)}
                                                    className={inputCls}
                                                    required
                                                />
                                            </div>
                                            <div className="col-span-2">
                                                <label className="block text-xs font-semibold text-slate-500 mb-0.5">Código</label>
                                                <input
                                                    type="text"
                                                    value={d.codigo_producto}
                                                    onChange={(e) => actualizarDetalle(idx, 'codigo_producto', e.target.value)}
                                                    className={inputCls}
                                                />
                                            </div>
                                            <div className="col-span-1">
                                                <label className="block text-xs font-semibold text-slate-500 mb-0.5">Cant. *</label>
                                                <input
                                                    type="number"
                                                    value={d.cantidad}
                                                    onChange={(e) => actualizarDetalle(idx, 'cantidad', e.target.value)}
                                                    className={inputCls}
                                                    min="0.001"
                                                    step="0.001"
                                                    required
                                                />
                                            </div>
                                            <div className="col-span-1">
                                                <label className="block text-xs font-semibold text-slate-500 mb-0.5">Unidad</label>
                                                <input
                                                    type="text"
                                                    value={d.unidad}
                                                    onChange={(e) => actualizarDetalle(idx, 'unidad', e.target.value)}
                                                    className={inputCls}
                                                />
                                            </div>
                                            <div className="col-span-2">
                                                <label className="block text-xs font-semibold text-slate-500 mb-0.5">P. Unitario *</label>
                                                <input
                                                    type="number"
                                                    value={d.precio_unitario}
                                                    onChange={(e) => actualizarDetalle(idx, 'precio_unitario', e.target.value)}
                                                    className={inputCls}
                                                    min="0"
                                                    required
                                                />
                                            </div>
                                            <div className="col-span-1">
                                                <label className="block text-xs font-semibold text-slate-500 mb-0.5">Subtotal</label>
                                                <div className="px-3 py-2 text-sm font-mono text-slate-700 dark:text-slate-300">
                                                    {d.subtotal ? clpFmt.format(parseFloat(d.subtotal)) : '—'}
                                                </div>
                                            </div>
                                            <div className="col-span-1 flex justify-end">
                                                {form.detalles.length > 1 && (
                                                    <button
                                                        type="button"
                                                        onClick={() => eliminarDetalle(idx)}
                                                        className="text-red-500 hover:text-red-700 p-1"
                                                        title="Eliminar ítem"
                                                    >
                                                        <i className="fas fa-trash-alt" />
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Totales */}
                                <div className="mt-4 p-4 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 text-sm space-y-1">
                                    <div className="flex justify-between text-slate-700 dark:text-slate-300">
                                        <span>Subtotal neto</span>
                                        <span className="font-mono">{clpFmt.format(subtotalForm)}</span>
                                    </div>
                                    <div className="flex justify-between text-slate-700 dark:text-slate-300">
                                        <span>IVA (19%)</span>
                                        <span className="font-mono">{clpFmt.format(impuestoForm)}</span>
                                    </div>
                                    <div className="flex justify-between font-bold text-slate-900 dark:text-slate-100 border-t border-slate-200 dark:border-slate-700 pt-1 mt-1">
                                        <span>Total</span>
                                        <span className="font-mono">{clpFmt.format(totalForm)}</span>
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setModalCrear(false)}
                                    className="px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={enviando}
                                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50"
                                >
                                    {enviando
                                        ? <><i className="fas fa-spinner fa-spin" /> Creando...</>
                                        : <><i className="fas fa-save" /> Crear OC</>
                                    }
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* Modal Detalle / Recepción */}
            {modalDetalle && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                        <div className="px-6 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-bold text-slate-900 dark:text-slate-100">
                                    {ocSeleccionada?.numero_oc ?? 'OC'}
                                </h2>
                                {ocSeleccionada && (
                                    <span className={`mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${COLORES_ESTADO[ocSeleccionada.estado] ?? ''}`}>
                                        {ETIQUETAS_ESTADO[ocSeleccionada.estado] ?? ocSeleccionada.estado}
                                    </span>
                                )}
                            </div>
                            <button
                                onClick={() => setModalDetalle(false)}
                                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                <i className="fas fa-times text-xl" />
                            </button>
                        </div>

                        {cargandoDetalle ? (
                            <div className="p-8 text-center text-slate-500">Cargando...</div>
                        ) : ocSeleccionada ? (
                            <div className="p-6 space-y-5">
                                {/* Info cabecera */}
                                <div className="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p className="text-xs text-slate-500 font-semibold">Proveedor</p>
                                        <p className="text-slate-900 dark:text-slate-100">
                                            {ocSeleccionada.proveedor?.razon_social ?? <em className="text-slate-400">Sin proveedor</em>}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-slate-500 font-semibold">Fecha emisión</p>
                                        <p className="text-slate-900 dark:text-slate-100">
                                            {ocSeleccionada.fecha_emision ? new Date(ocSeleccionada.fecha_emision).toLocaleDateString('es-CL') : '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-slate-500 font-semibold">Entrega esperada</p>
                                        <p className="text-slate-900 dark:text-slate-100">
                                            {ocSeleccionada.fecha_entrega_esperada ? new Date(ocSeleccionada.fecha_entrega_esperada).toLocaleDateString('es-CL') : '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-slate-500 font-semibold">Total</p>
                                        <p className="font-mono font-bold text-slate-900 dark:text-slate-100">
                                            {clpFmt.format(parseFloat(ocSeleccionada.total ?? 0))}
                                        </p>
                                    </div>
                                    {ocSeleccionada.notas && (
                                        <div className="col-span-2">
                                            <p className="text-xs text-slate-500 font-semibold">Notas</p>
                                            <p className="text-slate-700 dark:text-slate-300">{ocSeleccionada.notas}</p>
                                        </div>
                                    )}
                                </div>

                                {/* Detalles */}
                                <div>
                                    <h3 className="text-sm font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-2">Ítems</h3>
                                    <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
                                        <table className="w-full text-sm">
                                            <thead className="bg-slate-50 dark:bg-slate-900 text-xs uppercase text-slate-500">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">Descripción</th>
                                                    <th className="px-3 py-2 text-right">Cant.</th>
                                                    <th className="px-3 py-2 text-right">Recibido</th>
                                                    <th className="px-3 py-2 text-right">P. Unit.</th>
                                                    <th className="px-3 py-2 text-right">Subtotal</th>
                                                    {!['RECIBIDA_TOTAL', 'ANULADA'].includes(ocSeleccionada.estado) && (
                                                        <th className="px-3 py-2 text-center">Recibir ahora</th>
                                                    )}
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
                                                {(ocSeleccionada.detalles ?? []).map((d) => (
                                                    <tr key={d.id} className="hover:bg-slate-50 dark:hover:bg-slate-700">
                                                        <td className="px-3 py-2 text-slate-900 dark:text-slate-100">
                                                            {d.producto_descripcion}
                                                            {d.codigo_producto && <span className="ml-1 text-xs text-slate-400">({d.codigo_producto})</span>}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-slate-700 dark:text-slate-300">
                                                            {d.cantidad} {d.unidad}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-emerald-700">
                                                            {d.cantidad_recibida ?? 0} {d.unidad}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-slate-700 dark:text-slate-300">
                                                            {clpFmt.format(parseFloat(d.precio_unitario ?? 0))}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-mono text-slate-900 dark:text-slate-100">
                                                            {clpFmt.format(parseFloat(d.subtotal ?? 0))}
                                                        </td>
                                                        {!['RECIBIDA_TOTAL', 'ANULADA'].includes(ocSeleccionada.estado) && (
                                                            <td className="px-3 py-2 text-center">
                                                                <input
                                                                    type="number"
                                                                    value={recepcionesPendientes[d.id] ?? ''}
                                                                    onChange={(e) => setRecepcionesPendientes((p) => ({ ...p, [d.id]: e.target.value }))}
                                                                    placeholder="0"
                                                                    min="0"
                                                                    step="0.001"
                                                                    className="w-20 px-2 py-1 text-sm rounded border border-slate-300 dark:border-slate-600 dark:bg-slate-700 text-center"
                                                                />
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Acciones */}
                                <div className="flex justify-between gap-3 pt-2">
                                    <div>
                                        {ocSeleccionada.estado !== 'ANULADA' && (
                                            <button
                                                onClick={() => handleAnular(ocSeleccionada.id)}
                                                className="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-sm font-semibold"
                                            >
                                                <i className="fas fa-ban" /> Anular OC
                                            </button>
                                        )}
                                    </div>
                                    <div className="flex gap-3">
                                        <button
                                            onClick={() => setModalDetalle(false)}
                                            className="px-4 py-2.5 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold"
                                        >
                                            Cerrar
                                        </button>
                                        {!['RECIBIDA_TOTAL', 'ANULADA'].includes(ocSeleccionada.estado) && (
                                            <button
                                                onClick={handleRecibir}
                                                disabled={enviando}
                                                className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-sm disabled:opacity-50"
                                            >
                                                {enviando
                                                    ? <><i className="fas fa-spinner fa-spin" /> Guardando...</>
                                                    : <><i className="fas fa-truck" /> Registrar recepción</>
                                                }
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ) : null}
                    </div>
                </div>
            )}
        </div>
    );
};

export default GestionOrdenes;
