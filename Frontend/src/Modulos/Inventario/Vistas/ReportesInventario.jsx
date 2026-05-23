import React, { useEffect, useMemo, useState } from 'react';
import Swal from 'sweetalert2';
import inventarioApi from '../Servicios/inventarioApi';
import { useInventarioData } from '../Hooks/useInventarioData';
import {
    AlertBox,
    EmptyState,
    EstadoBadge,
    ErrorNotice,
    Field,
    formatCurrency,
    formatDate,
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

const movimientoTipos = [
    { value: 'entrada', label: 'Entrada' },
    { value: 'salida', label: 'Salida' },
    { value: 'traspaso', label: 'Traspaso' },
    { value: 'ajuste_positivo', label: 'Ajuste positivo' },
    { value: 'ajuste_negativo', label: 'Ajuste negativo' },
];

const estadoStockOptions = [
    { value: 'sin_stock', label: 'Sin stock' },
    { value: 'bajo_minimo', label: 'Bajo mínimo' },
    { value: 'ok', label: 'OK' },
];

const estadoLoteOptions = [
    { value: 'vencido', label: 'Vencido' },
    { value: 'por_vencer', label: 'Por vencer' },
    { value: 'vigente', label: 'Vigente' },
    { value: 'inactivo', label: 'Inactivo' },
    { value: 'sin_vencimiento', label: 'Sin vencimiento' },
];

const estadoReservaOptions = [
    { value: 'ACTIVA', label: 'Activa' },
    { value: 'CONSUMIDA', label: 'Consumida' },
    { value: 'LIBERADA', label: 'Liberada' },
    { value: 'PARCIALMENTE_LIBERADA', label: 'Parcialmente liberada' },
    { value: 'CANCELADA', label: 'Cancelada' },
    { value: 'VENCIDA', label: 'Vencida' },
];

const estadoTomaOptions = [
    { value: 'BORRADOR', label: 'Borrador' },
    { value: 'EN_CONTEO', label: 'En conteo' },
    { value: 'CERRADA', label: 'Cerrada' },
    { value: 'AJUSTADA', label: 'Ajustada' },
    { value: 'CANCELADA', label: 'Cancelada' },
];

const limitOptions = [50, 100, 200, 500];

const reportes = [
    {
        key: 'stock',
        apiMethod: 'stock',
        exportKey: 'stock',
        label: 'Stock',
        icon: 'fas fa-boxes-stacked',
        description: 'Stock actual, comprometido, disponible y valorizado por producto y bodega.',
        exportable: true,
        filters: ['producto_id', 'bodega_id', 'estado_stock', 'limit'],
        columns: [
            { key: 'producto_sku', label: 'SKU' },
            { key: 'producto_nombre', label: 'Producto' },
            { key: 'bodega_nombre', label: 'Bodega' },
            { key: 'stock_actual', label: 'Stock', type: 'number', align: 'right', decimals: 2 },
            { key: 'stock_comprometido', label: 'Comprometido', type: 'number', align: 'right', decimals: 2 },
            { key: 'stock_disponible', label: 'Disponible', type: 'number', align: 'right', decimals: 2 },
            { key: 'valor_total', label: 'Valor', type: 'currency', align: 'right' },
            { key: 'estado_stock', label: 'Estado', type: 'badge' },
        ],
    },
    {
        key: 'movimientos',
        apiMethod: 'movimientos',
        exportKey: 'movimientos',
        label: 'Movimientos',
        icon: 'fas fa-arrows-rotate',
        description: 'Entradas, salidas, traspasos y ajustes registrados en el Kardex.',
        exportable: true,
        filters: ['producto_id', 'bodega_id', 'tipo', 'desde', 'hasta', 'limit'],
        columns: [
            { key: 'fecha_movimiento', label: 'Fecha', type: 'date' },
            { key: 'tipo', label: 'Tipo', type: 'badge' },
            { key: 'producto_nombre', label: 'Producto' },
            { key: 'bodega_origen_nombre', label: 'Origen' },
            { key: 'bodega_destino_nombre', label: 'Destino' },
            { key: 'cantidad', label: 'Cantidad', type: 'number', align: 'right', decimals: 2 },
            { key: 'costo_total', label: 'Costo', type: 'currency', align: 'right' },
            { key: 'referencia', label: 'Referencia' },
        ],
    },
    {
        key: 'valorizacion',
        apiMethod: 'valorizacion',
        exportKey: 'valorizacion',
        label: 'Valorización',
        icon: 'fas fa-dollar-sign',
        description: 'Valorización por producto y por bodega, con detección de valores inconsistentes.',
        exportable: true,
        filters: ['producto_id', 'bodega_id', 'limit'],
        dataPath: 'por_producto',
        secondaryTables: [
            {
                path: 'por_bodega',
                title: 'Valorización por bodega',
                subtitle: 'Distribución monetaria consolidada por ubicación.',
                columns: [
                    { key: 'bodega_codigo', label: 'Código' },
                    { key: 'bodega_nombre', label: 'Bodega' },
                    { key: 'stock_total', label: 'Stock', type: 'number', align: 'right', decimals: 2 },
                    { key: 'valor_total', label: 'Valor', type: 'currency', align: 'right' },
                ],
            },
        ],
        columns: [
            { key: 'producto_sku', label: 'SKU' },
            { key: 'producto_nombre', label: 'Producto' },
            { key: 'stock_total', label: 'Stock', type: 'number', align: 'right', decimals: 2 },
            { key: 'costo_promedio_ponderado', label: 'PMP', type: 'currency', align: 'right' },
            { key: 'valor_total', label: 'Valor total', type: 'currency', align: 'right' },
            { key: 'estado_valorizacion', label: 'Estado', type: 'badge' },
        ],
    },
    {
        key: 'lotes',
        apiMethod: 'lotes',
        exportKey: 'lotes',
        label: 'Lotes',
        icon: 'fas fa-boxes-packing',
        description: 'Stock por lote, fechas de vencimiento y estado operativo del lote.',
        exportable: true,
        filters: ['producto_id', 'bodega_id', 'lote_id', 'estado_lote', 'dias_vencimiento', 'limit'],
        columns: [
            { key: 'codigo_lote', label: 'Lote' },
            { key: 'producto_nombre', label: 'Producto' },
            { key: 'bodega_nombre', label: 'Bodega' },
            { key: 'fecha_vencimiento', label: 'Vencimiento', type: 'date' },
            { key: 'dias_para_vencer', label: 'Días', type: 'number', align: 'right' },
            { key: 'stock_actual', label: 'Stock', type: 'number', align: 'right', decimals: 2 },
            { key: 'estado_lote', label: 'Estado', type: 'badge' },
        ],
    },
    {
        key: 'reservas',
        apiMethod: 'reservas',
        exportKey: 'reservas',
        label: 'Reservas',
        icon: 'fas fa-lock',
        description: 'Reservas activas, liberadas, consumidas y cantidades pendientes.',
        exportable: true,
        filters: ['estado', 'producto_id', 'bodega_id', 'desde', 'hasta', 'limit'],
        columns: [
            { key: 'codigo_reserva', label: 'Código' },
            { key: 'estado', label: 'Estado', type: 'badge' },
            { key: 'referencia', label: 'Referencia' },
            { key: 'fecha_reserva', label: 'Fecha', type: 'date' },
            { key: 'cantidad_reservada', label: 'Reservada', type: 'number', align: 'right', decimals: 2 },
            { key: 'cantidad_pendiente', label: 'Pendiente', type: 'number', align: 'right', decimals: 2 },
            { key: 'reservado_por', label: 'Reservado por' },
        ],
    },
    {
        key: 'tomas-fisicas',
        apiMethod: 'tomasFisicas',
        exportKey: 'tomas-fisicas',
        label: 'Tomas físicas',
        icon: 'fas fa-clipboard-check',
        description: 'Exactitud, diferencias, estados y trazabilidad de inventario físico.',
        exportable: true,
        filters: ['estado', 'bodega_id', 'desde', 'hasta', 'limit'],
        columns: [
            { key: 'codigo_toma', label: 'Código' },
            { key: 'estado', label: 'Estado', type: 'badge' },
            { key: 'tipo', label: 'Tipo' },
            { key: 'bodega_nombre', label: 'Bodega' },
            { key: 'detalles', label: 'Detalles', type: 'number', align: 'right' },
            { key: 'detalles_con_diferencia', label: 'Diferencias', type: 'number', align: 'right' },
            { key: 'exactitud_porcentaje', label: 'Exactitud', type: 'percent', align: 'right' },
            { key: 'fecha_inicio', label: 'Inicio', type: 'date' },
        ],
    },
    {
        key: 'ajustes',
        apiMethod: 'ajustes',
        exportKey: 'ajustes',
        label: 'Ajustes',
        icon: 'fas fa-screwdriver-wrench',
        description: 'Historial de ajustes críticos, costos asociados y responsable operativo.',
        exportable: true,
        filters: ['producto_id', 'bodega_id', 'lote_id', 'desde', 'hasta', 'limit'],
        columns: [
            { key: 'fecha', label: 'Fecha', type: 'date' },
            { key: 'producto_nombre', label: 'Producto' },
            { key: 'bodega_nombre', label: 'Bodega' },
            { key: 'lote_codigo', label: 'Lote' },
            { key: 'tipo_nombre', label: 'Tipo' },
            { key: 'cantidad', label: 'Cantidad', type: 'number', align: 'right', decimals: 2 },
            { key: 'costo_total', label: 'Costo', type: 'currency', align: 'right' },
            { key: 'referencia', label: 'Referencia' },
        ],
    },
    {
        key: 'reposicion-alertas',
        apiMethod: 'reposicionAlertas',
        exportKey: null,
        label: 'Reposición y alertas',
        icon: 'fas fa-bell',
        description: 'Alertas operativas y sugerencias calculadas por reglas de reposición.',
        exportable: false,
        filters: ['producto_id', 'bodega_id', 'limit'],
        dataPath: 'alertas',
        secondaryTables: [
            {
                path: 'sugerencias_reposicion',
                title: 'Sugerencias de reposición',
                subtitle: 'Cantidades sugeridas para recuperar stock objetivo.',
                columns: [
                    { key: 'producto_nombre', label: 'Producto' },
                    { key: 'bodega_nombre', label: 'Bodega' },
                    { key: 'stock_actual', label: 'Actual', type: 'number', align: 'right', decimals: 2 },
                    { key: 'stock_minimo', label: 'Mínimo', type: 'number', align: 'right', decimals: 2 },
                    { key: 'stock_objetivo', label: 'Objetivo', type: 'number', align: 'right', decimals: 2 },
                    { key: 'cantidad_sugerida', label: 'Sugerida', type: 'number', align: 'right', decimals: 2 },
                ],
            },
        ],
        columns: [
            { key: 'tipo', label: 'Tipo', type: 'badge' },
            { key: 'severidad', label: 'Severidad', type: 'badge' },
            { key: 'titulo', label: 'Título' },
            { key: 'producto_nombre', label: 'Producto' },
            { key: 'bodega_nombre', label: 'Bodega' },
            { key: 'cantidad_actual', label: 'Actual', type: 'number', align: 'right', decimals: 2 },
            { key: 'cantidad_sugerida', label: 'Sugerida', type: 'number', align: 'right', decimals: 2 },
            { key: 'referencia', label: 'Referencia' },
        ],
    },
];

const initialFilters = {
    producto_id: '',
    bodega_id: '',
    lote_id: '',
    estado: '',
    estado_stock: '',
    estado_lote: '',
    tipo: '',
    desde: '',
    hasta: '',
    dias_vencimiento: 30,
    limit: 100,
};

const buildParamsFor = (reporte, filterState) => {
    return reporte.filters.reduce((acc, key) => {
        const value = filterState[key];
        if (value !== undefined && value !== null && value !== '') {
            acc[key] = value;
        }
        return acc;
    }, {});
};

const labelFromKey = (key) => String(key || '')
    .replaceAll('_', ' ')
    .replaceAll('-', ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());

const isCurrencyKey = (key) => ['valor', 'costo', 'monto', 'total'].some((token) => String(key).includes(token));
const isPercentKey = (key) => String(key).includes('porcentaje') || String(key).includes('exactitud');

const getNestedData = (data, path) => {
    if (!path) return data;
    return path.split('.').reduce((current, segment) => current?.[segment], data);
};

const getRows = (data, path = null) => {
    const selected = getNestedData(data, path);

    if (Array.isArray(selected)) {
        return selected;
    }

    if (Array.isArray(selected?.data)) {
        return selected.data;
    }

    return [];
};

const getCellValue = (row, key) => {
    if (key === 'producto_nombre') return getProductoNombre(row);
    if (key === 'bodega_nombre') return getBodegaNombre(row);
    return row?.[key];
};

const renderValue = (row, column) => {
    const value = getCellValue(row, column.key);

    if (column.type === 'badge') {
        return <EstadoBadge value={value} />;
    }

    if (column.type === 'currency') {
        return <span className="font-black text-emerald-700">{formatCurrency(value)}</span>;
    }

    if (column.type === 'number') {
        return <span className="font-black text-slate-700">{formatNumber(value, column.decimals ?? 0)}</span>;
    }

    if (column.type === 'percent') {
        return <span className="font-black text-slate-700">{formatNumber(value, 2)}%</span>;
    }

    if (column.type === 'date') {
        return formatDate(value);
    }

    if (typeof value === 'boolean') {
        return value ? 'Sí' : 'No';
    }

    return value || '-';
};

const TablaReporte = ({ rows, columns }) => {
    if (!rows.length) {
        return (
            <EmptyState
                title="Sin datos para mostrar"
                description="El reporte no devolvió filas con los filtros actuales."
                icon="fas fa-table"
            />
        );
    }

    return (
        <TableShell>
            <thead className="bg-slate-50">
                <tr>
                    {columns.map((column) => (
                        <Th key={column.key} align={column.align}>{column.label}</Th>
                    ))}
                </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
                {rows.map((row, index) => (
                    <tr key={row.id || row.codigo_lote || row.codigo_reserva || row.codigo_toma || `${row.producto_id}-${row.bodega_id}-${index}`} className="hover:bg-slate-50/70 transition-colors">
                        {columns.map((column) => (
                            <Td key={column.key} align={column.align} className={column.className || ''}>
                                {renderValue(row, column)}
                            </Td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </TableShell>
    );
};

const ResumenReporte = ({ resumen }) => {
    const entries = Object.entries(resumen || {})
        .filter(([, value]) => value !== null && value !== undefined && typeof value !== 'object')
        .slice(0, 8);

    if (!entries.length) {
        return null;
    }

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            {entries.map(([key, value]) => {
                const formatted = isCurrencyKey(key)
                    ? formatCurrency(value)
                    : isPercentKey(key)
                        ? `${formatNumber(value, 2)}%`
                        : typeof value === 'number'
                            ? formatNumber(value, String(key).includes('stock') || String(key).includes('cantidad') ? 2 : 0)
                            : value;

                return (
                    <div key={key} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p className="text-[11px] uppercase tracking-widest text-slate-400 font-black">{labelFromKey(key)}</p>
                        <p className="text-2xl font-black text-slate-800 mt-1">{formatted}</p>
                    </div>
                );
            })}
        </div>
    );
};

const ReportesInventario = () => {
    const [tipoReporte, setTipoReporte] = useState('stock');
    const [filters, setFilters] = useState(initialFilters);
    const [resultado, setResultado] = useState(null);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [error, setError] = useState(null);

    const {
        productos,
        bodegas,
        lotes,
        cargarProductosCache,
        cargarBodegasCache,
        cargarLotesCache,
    } = useInventarioData();

    const reporte = useMemo(() => reportes.find((item) => item.key === tipoReporte) || reportes[0], [tipoReporte]);

    const params = useMemo(() => buildParamsFor(reporte, filters), [filters, reporte]);

    const cargarReporte = async (paramsOverride = params) => {
        try {
            setLoading(true);
            setError(null);

            const response = await inventarioApi.reportes[reporte.apiMethod](paramsOverride);
            setResultado({
                data: response?.data ?? [],
                resumen: response?.resumen ?? {},
                metadata: response?.metadata ?? null,
                message: response?.message ?? null,
            });
        } catch (err) {
            setError(err?.response?.data || err);
            setResultado(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        Promise.allSettled([
            cargarProductosCache(),
            cargarBodegasCache(),
            cargarLotesCache(),
        ]);
    }, [cargarBodegasCache, cargarLotesCache, cargarProductosCache]);

    useEffect(() => {
        cargarReporte(buildParamsFor(reporte, filters));
        // Se recarga al cambiar de reporte. Los filtros se aplican explícitamente con el botón para evitar llamadas en cada cambio de input.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tipoReporte]);

    const rows = useMemo(() => getRows(resultado?.data, reporte.dataPath), [resultado, reporte]);

    const handleFilterChange = (event) => {
        const { name, value } = event.target;
        setFilters((current) => ({
            ...current,
            [name]: value,
        }));
    };

    const limpiarFiltros = () => {
        const cleanFilters = { ...initialFilters };
        setFilters(cleanFilters);
        cargarReporte(buildParamsFor(reporte, cleanFilters));
    };

    const aplicarFiltros = (event) => {
        event.preventDefault();
        cargarReporte();
    };

    const exportarCsv = async () => {
        if (!reporte.exportable || !reporte.exportKey) {
            await Swal.fire({
                icon: 'info',
                title: 'Exportación no disponible',
                text: 'Este reporte consolidado no tiene exportación CSV habilitada en backend todavía.',
                confirmButtonColor: '#10b981',
            });
            return;
        }

        try {
            setExporting(true);
            await inventarioApi.reportes.exportarCsv(reporte.exportKey, params);
        } catch (err) {
            setError(err?.response?.data || err);
        } finally {
            setExporting(false);
        }
    };

    const renderSelectOptions = (items, getValue, getLabel) => (
        items.map((item) => (
            <option key={getValue(item)} value={getValue(item)}>
                {getLabel(item)}
            </option>
        ))
    );

    const renderFiltro = (key) => {
        if (key === 'producto_id') {
            return (
                <Field key={key} label="Producto">
                    <select name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white">
                        <option value="">Todos</option>
                        {renderSelectOptions(productos, (item) => item.id, (item) => `${item.sku ? `${item.sku} · ` : ''}${item.nombre || `Producto #${item.id}`}`)}
                    </select>
                </Field>
            );
        }

        if (key === 'bodega_id') {
            return (
                <Field key={key} label="Bodega">
                    <select name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white">
                        <option value="">Todas</option>
                        {renderSelectOptions(bodegas, (item) => item.id, (item) => `${item.codigo ? `${item.codigo} · ` : ''}${item.nombre || `Bodega #${item.id}`}`)}
                    </select>
                </Field>
            );
        }

        if (key === 'lote_id') {
            return (
                <Field key={key} label="Lote">
                    <select name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white">
                        <option value="">Todos</option>
                        {renderSelectOptions(lotes, (item) => item.id, (item) => item.codigo_lote || `Lote #${item.id}`)}
                    </select>
                </Field>
            );
        }

        if (key === 'tipo') {
            return (
                <Field key={key} label="Tipo">
                    <select name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white">
                        <option value="">Todos</option>
                        {movimientoTipos.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                    </select>
                </Field>
            );
        }

        if (key === 'estado') {
            const options = reporte.key === 'reservas' ? estadoReservaOptions : estadoTomaOptions;
            return (
                <Field key={key} label="Estado">
                    <select name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white">
                        <option value="">Todos</option>
                        {options.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                    </select>
                </Field>
            );
        }

        if (key === 'estado_stock') {
            return (
                <Field key={key} label="Estado stock">
                    <select name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white">
                        <option value="">Todos</option>
                        {estadoStockOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                    </select>
                </Field>
            );
        }

        if (key === 'estado_lote') {
            return (
                <Field key={key} label="Estado lote">
                    <select name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white">
                        <option value="">Todos</option>
                        {estadoLoteOptions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                    </select>
                </Field>
            );
        }

        if (key === 'desde' || key === 'hasta') {
            return (
                <Field key={key} label={key === 'desde' ? 'Desde' : 'Hasta'}>
                    <input type="date" name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white" />
                </Field>
            );
        }

        if (key === 'limit') {
            return (
                <Field key={key} label="Filas">
                    <select name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white">
                        {limitOptions.map((item) => <option key={item} value={item}>{item}</option>)}
                    </select>
                </Field>
            );
        }

        if (key === 'dias_vencimiento') {
            return (
                <Field key={key} label="Días vencimiento">
                    <input type="number" min="0" name={key} value={filters[key]} onChange={handleFilterChange} className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm font-semibold text-slate-700 bg-white" />
                </Field>
            );
        }

        return null;
    };

    return (
        <div className="space-y-8">
            <PageHeader
                title="Reportes de Inventario"
                description="Consulta gerencial de stock, movimientos, valorización, lotes, reservas, tomas físicas, ajustes críticos, alertas y reposición."
                actions={(
                    <>
                        <SecondaryButton type="button" onClick={cargarReporte} disabled={loading}>
                            <i className="fas fa-rotate-right"></i>
                            Recargar
                        </SecondaryButton>
                        <PrimaryButton type="button" onClick={exportarCsv} disabled={exporting || loading}>
                            <i className="fas fa-file-csv"></i>
                            {exporting ? 'Exportando...' : 'Exportar CSV'}
                        </PrimaryButton>
                    </>
                )}
            />

            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                {reportes.map((item) => (
                    <button
                        key={item.key}
                        type="button"
                        onClick={() => setTipoReporte(item.key)}
                        className={`text-left rounded-2xl border p-4 transition-all ${tipoReporte === item.key
                            ? 'bg-emerald-50 border-emerald-300 shadow-sm text-emerald-800'
                            : 'bg-white border-slate-200 hover:bg-slate-50 text-slate-700'}`}
                    >
                        <div className="flex items-center gap-3">
                            <span className="w-10 h-10 rounded-xl bg-white border border-current/10 flex items-center justify-center">
                                <i className={`${item.icon}`}></i>
                            </span>
                            <div>
                                <p className="font-black text-sm">{item.label}</p>
                                <p className="text-[11px] font-bold opacity-70 mt-0.5">{item.exportable ? 'CSV disponible' : 'Consolidado sin CSV'}</p>
                            </div>
                        </div>
                    </button>
                ))}
            </div>

            <Panel
                title={reporte.label}
                subtitle={reporte.description}
            >
                <form onSubmit={aplicarFiltros} className="space-y-5">
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                        {reporte.filters.map(renderFiltro)}
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <PrimaryButton type="submit" disabled={loading}>
                            <i className="fas fa-filter"></i>
                            Aplicar filtros
                        </PrimaryButton>
                        <SecondaryButton type="button" onClick={limpiarFiltros} disabled={loading}>
                            <i className="fas fa-eraser"></i>
                            Limpiar filtros
                        </SecondaryButton>
                    </div>
                </form>
            </Panel>

            <ErrorNotice error={error} />

            {!reporte.exportable && (
                <AlertBox tone="slate">
                    Este reporte consolida alertas y sugerencias desde dos servicios. El backend actual no expone CSV para este tipo; los reportes exportables son stock, movimientos, valorización, lotes, reservas, tomas físicas y ajustes.
                </AlertBox>
            )}

            {resultado?.metadata?.generado_en && (
                <AlertBox tone="blue">
                    Reporte generado el {formatDate(resultado.metadata.generado_en)}. Límite aplicado: {resultado.metadata?.limit || filters.limit} filas.
                </AlertBox>
            )}

            {loading ? (
                <LoadingState text={`Cargando reporte de ${reporte.label.toLowerCase()}...`} />
            ) : (
                <>
                    <ResumenReporte resumen={resultado?.resumen} />

                    <Panel
                        title={`Detalle — ${reporte.label}`}
                        subtitle={`${formatNumber(rows.length)} fila(s) cargadas con los filtros actuales.`}
                    >
                        <TablaReporte rows={rows} columns={reporte.columns} />
                    </Panel>

                    {(reporte.secondaryTables || []).map((secondary) => {
                        const secondaryRows = getRows(resultado?.data, secondary.path);
                        return (
                            <Panel key={secondary.path} title={secondary.title} subtitle={secondary.subtitle}>
                                <TablaReporte rows={secondaryRows} columns={secondary.columns} />
                            </Panel>
                        );
                    })}
                </>
            )}
        </div>
    );
};

export default ReportesInventario;
