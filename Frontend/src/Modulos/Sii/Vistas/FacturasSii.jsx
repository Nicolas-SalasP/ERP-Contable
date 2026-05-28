import React, { useCallback, useEffect, useState } from 'react';
import siiApi from '../Servicios/siiApi';
import EstadoSiiBadge from '../Componentes/EstadoSiiBadge';
import EstadoSiiPanel from '../Componentes/EstadoSiiPanel';

const POR_PAGINA_DEFECTO = 25;

const formatCLP = (n) => {
    if (n === null || n === undefined) return '—';
    try {
        return new Intl.NumberFormat('es-CL', {
            style: 'currency', currency: 'CLP', maximumFractionDigits: 0,
        }).format(Number(n));
    } catch {
        return n;
    }
};

const FacturasSii = () => {
    const [filas, setFilas] = useState([]);
    const [paginacion, setPaginacion] = useState({
        total: 0, por_pagina: POR_PAGINA_DEFECTO, pagina_actual: 1, ultima_pagina: 1,
    });
    const [pagina, setPagina] = useState(1);
    const [cargando, setCargando] = useState(true);
    const [error, setError] = useState(null);
    const [seleccionId, setSeleccionId] = useState(null);

    const cargar = useCallback(async (paginaSolicitada) => {
        setCargando(true);
        setError(null);
        try {
            const resp = await siiApi.facturas.listar({
                por_pagina: POR_PAGINA_DEFECTO,
                pagina: paginaSolicitada,
            });
            setFilas(resp?.data ?? []);
            setPaginacion(resp?.paginacion ?? {
                total: 0, por_pagina: POR_PAGINA_DEFECTO, pagina_actual: 1, ultima_pagina: 1,
            });
        } catch (err) {
            setError(err?.message ?? 'Error al cargar facturas');
        } finally {
            setCargando(false);
        }
    }, []);

    useEffect(() => {
        cargar(pagina);
    }, [pagina, cargar]);

    const irAnterior = () => {
        if (pagina > 1) setPagina((p) => p - 1);
    };
    const irSiguiente = () => {
        if (pagina < paginacion.ultima_pagina) setPagina((p) => p + 1);
    };
    const toggleSeleccion = (facturaId) => {
        setSeleccionId((curr) => (curr === facturaId ? null : facturaId));
    };

    return (
        <div className="max-w-6xl mx-auto p-6 md:p-8 space-y-6">
            <header>
                <h1 className="text-2xl md:text-3xl font-black text-slate-900 flex items-center gap-3">
                    <i className="fas fa-file-invoice text-blue-600" />
                    Facturas SII
                </h1>
                <p className="text-sm text-slate-500 mt-1">
                    Estado de emision al SII de tus documentos tributarios electronicos.
                </p>
            </header>

            {error && (
                <div
                    className="p-4 border border-red-200 rounded bg-red-50 text-red-700 text-sm"
                    data-testid="facturas-sii-error"
                >
                    {error}
                    <button
                        type="button"
                        onClick={() => cargar(pagina)}
                        className="ml-3 px-2 py-1 text-xs bg-red-100 hover:bg-red-200 rounded"
                        data-testid="facturas-sii-reintentar"
                    >
                        Reintentar
                    </button>
                </div>
            )}

            <div
                className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden"
                data-testid="facturas-sii-tabla"
            >
                {cargando ? (
                    <div
                        className="p-8 text-center text-sm text-slate-400"
                        data-testid="facturas-sii-cargando"
                    >
                        <i className="fas fa-spinner fa-spin mr-2" /> Cargando facturas...
                    </div>
                ) : filas.length === 0 ? (
                    <div
                        className="p-10 text-center"
                        data-testid="facturas-sii-vacio"
                    >
                        <div className="text-4xl mb-2">📄</div>
                        <p className="text-sm text-slate-500">
                            No hay facturas registradas para esta empresa.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr className="text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                                    <th className="px-4 py-3">Numero</th>
                                    <th className="px-4 py-3">Fecha</th>
                                    <th className="px-4 py-3">Cliente</th>
                                    <th className="px-4 py-3 text-right">Monto</th>
                                    <th className="px-4 py-3">Estado SII</th>
                                    <th className="px-4 py-3 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {filas.map((f) => {
                                    const expandida = seleccionId === f.factura_id;
                                    return (
                                        <React.Fragment key={f.factura_id}>
                                            <tr
                                                data-testid={`fila-factura-${f.factura_id}`}
                                                className="hover:bg-slate-50/50"
                                            >
                                                <td className="px-4 py-3 font-mono text-slate-800">
                                                    {f.numero_factura}
                                                </td>
                                                <td className="px-4 py-3 text-slate-600">
                                                    {f.fecha_emision ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <div className="flex flex-col">
                                                        <span className="text-slate-800">
                                                            {f.cliente?.razon_social ?? '—'}
                                                        </span>
                                                        {f.cliente?.rut && (
                                                            <span className="text-xs text-slate-500 font-mono">
                                                                {f.cliente.rut}
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-3 font-mono text-right text-slate-800">
                                                    {formatCLP(f.monto_bruto)}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <EstadoSiiBadge facturaId={f.factura_id} />
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleSeleccion(f.factura_id)}
                                                        className="px-3 py-1 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-md transition-colors"
                                                        data-testid={`btn-detalle-${f.factura_id}`}
                                                    >
                                                        {expandida ? 'Ocultar' : 'Ver detalle'}
                                                    </button>
                                                </td>
                                            </tr>
                                            {expandida && (
                                                <tr data-testid={`panel-factura-${f.factura_id}`}>
                                                    <td colSpan={6} className="px-4 py-3 bg-slate-50/50">
                                                        <EstadoSiiPanel facturaId={f.factura_id} />
                                                    </td>
                                                </tr>
                                            )}
                                        </React.Fragment>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}

                {!cargando && filas.length > 0 && (
                    <div
                        className="flex items-center justify-between px-4 py-3 border-t border-slate-200 bg-slate-50 text-sm"
                        data-testid="facturas-sii-paginacion"
                    >
                        <span className="text-slate-600">
                            Pagina {paginacion.pagina_actual} de {paginacion.ultima_pagina} ·{' '}
                            {paginacion.total} factura{paginacion.total === 1 ? '' : 's'}
                        </span>
                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={irAnterior}
                                disabled={pagina <= 1}
                                className="px-3 py-1 text-xs font-bold bg-white hover:bg-slate-100 border border-slate-300 rounded-md disabled:opacity-40 disabled:cursor-not-allowed"
                                data-testid="btn-pagina-anterior"
                            >
                                Anterior
                            </button>
                            <button
                                type="button"
                                onClick={irSiguiente}
                                disabled={pagina >= paginacion.ultima_pagina}
                                className="px-3 py-1 text-xs font-bold bg-white hover:bg-slate-100 border border-slate-300 rounded-md disabled:opacity-40 disabled:cursor-not-allowed"
                                data-testid="btn-pagina-siguiente"
                            >
                                Siguiente
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default FacturasSii;
