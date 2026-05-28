import React from 'react';

// Catalogo de tipos DTE para el dropdown de filtro (espeja SiiDteEmitido del backend).
const TIPOS_DTE = [
    { codigo: 33,  nombre: 'Factura Electronica' },
    { codigo: 34,  nombre: 'Factura Exenta' },
    { codigo: 39,  nombre: 'Boleta Electronica' },
    { codigo: 41,  nombre: 'Boleta Exenta' },
    { codigo: 43,  nombre: 'Liquidacion Factura' },
    { codigo: 46,  nombre: 'Factura de Compra' },
    { codigo: 52,  nombre: 'Guia de Despacho' },
    { codigo: 56,  nombre: 'Nota de Debito' },
    { codigo: 61,  nombre: 'Nota de Credito' },
    { codigo: 110, nombre: 'Factura de Exportacion' },
    { codigo: 111, nombre: 'Nota de Debito Exportacion' },
    { codigo: 112, nombre: 'Nota de Credito Exportacion' },
];

const NOMBRE_TIPO = Object.fromEntries(TIPOS_DTE.map(t => [t.codigo, t.nombre]));

const COLOR_ESTADO = {
    activo:   'bg-emerald-100 text-emerald-700 border-emerald-300',
    agotado:  'bg-slate-200 text-slate-700 border-slate-300',
    vencido:  'bg-orange-100 text-orange-700 border-orange-300',
    revocado: 'bg-rose-100 text-rose-700 border-rose-300',
};

const diasParaVencer = (fechaIso) => {
    if (!fechaIso) return null;
    const fin = new Date(fechaIso).getTime();
    if (Number.isNaN(fin)) return null;
    return Math.floor((fin - Date.now()) / (1000 * 60 * 60 * 24));
};

const badgeVencimiento = (fechaIso) => {
    const dias = diasParaVencer(fechaIso);
    if (dias === null) return null;
    if (dias < 0)  return { texto: 'Vencido',         clases: 'bg-slate-200 text-slate-700 border-slate-300' };
    if (dias <= 7) return { texto: `${dias}d`,        clases: 'bg-rose-100 text-rose-700 border-rose-300' };
    if (dias <= 30) return { texto: `${dias}d`,       clases: 'bg-orange-100 text-orange-700 border-orange-300' };
    if (dias <= 60) return { texto: `${dias}d`,       clases: 'bg-amber-100 text-amber-700 border-amber-300' };
    return                    { texto: `${dias}d`,    clases: 'bg-emerald-100 text-emerald-700 border-emerald-300' };
};

const formatearFecha = (iso) => {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('es-CL', { year: 'numeric', month: 'short', day: '2-digit' });
};

const TablaCafsHistorial = ({ cafs, cargando, filtroTipo, onCambiarFiltro, onRevocar }) => {
    const handleFiltro = (e) => {
        const v = e.target.value;
        onCambiarFiltro(v === '' ? null : Number(v));
    };

    return (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden animate-fade-in" data-testid="historial-tabla">
            <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-slate-200 bg-slate-50">
                <h3 className="font-bold text-slate-800 flex items-center gap-2">
                    <i className="fas fa-history text-slate-500" /> Historial de CAFs
                </h3>
                <label className="flex items-center gap-2 text-sm">
                    <span className="text-slate-600">Filtrar por tipo:</span>
                    <select
                        data-testid="filtro-tipo"
                        value={filtroTipo ?? ''}
                        onChange={handleFiltro}
                        className="border border-slate-300 rounded-md px-2 py-1 text-sm bg-white"
                    >
                        <option value="">Todos</option>
                        {TIPOS_DTE.map((t) => (
                            <option key={t.codigo} value={t.codigo}>{t.codigo} - {t.nombre}</option>
                        ))}
                    </select>
                </label>
            </div>

            {cargando ? (
                <div data-testid="historial-loading" className="p-8 text-center text-sm text-slate-400">
                    <i className="fas fa-spinner fa-spin mr-2" /> Cargando historial...
                </div>
            ) : cafs.length === 0 ? (
                <div data-testid="historial-empty" className="p-10 text-center">
                    <div className="text-4xl mb-2">📂</div>
                    <p className="text-sm text-slate-500">No hay CAFs cargados para el filtro seleccionado.</p>
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-200">
                            <tr className="text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                                <th className="px-4 py-3">Tipo</th>
                                <th className="px-4 py-3">Rango</th>
                                <th className="px-4 py-3">Uso</th>
                                <th className="px-4 py-3 hidden md:table-cell">IDK</th>
                                <th className="px-4 py-3 hidden md:table-cell">Fecha</th>
                                <th className="px-4 py-3">Vencimiento</th>
                                <th className="px-4 py-3">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {cafs.map((caf) => {
                                const total = caf.folio_hasta - caf.folio_desde + 1;
                                const venc = badgeVencimiento(caf.fecha_vencimiento);
                                return (
                                    <tr key={caf.id} data-testid={`caf-row-${caf.id}`} className="hover:bg-slate-50/50">
                                        <td className="px-4 py-3">
                                            <div className="flex flex-col">
                                                <span className="text-xs font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded w-fit">{caf.tipo_dte}</span>
                                                <span className="text-xs text-slate-500 mt-0.5">{NOMBRE_TIPO[caf.tipo_dte] ?? `Tipo ${caf.tipo_dte}`}</span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-slate-700">{caf.folio_desde} - {caf.folio_hasta}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2 text-xs">
                                                <span className="font-mono text-slate-700">{caf.folios_usados}/{total}</span>
                                                <div className="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                    <div className="h-full bg-blue-500" style={{ width: `${total > 0 ? (caf.folios_usados / total) * 100 : 0}%` }} />
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 font-mono text-xs text-slate-600 hidden md:table-cell">{caf.sii_idk}</td>
                                        <td className="px-4 py-3 text-xs text-slate-600 hidden md:table-cell">{formatearFecha(caf.fecha_autorizacion)}</td>
                                        <td className="px-4 py-3">
                                            {venc ? (
                                                <span className={`text-xs font-bold px-2 py-1 rounded-full border ${venc.clases}`}>{venc.texto}</span>
                                            ) : (
                                                <span className="text-xs text-slate-400">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span className={`text-xs font-bold px-2 py-1 rounded-full border ${COLOR_ESTADO[caf.estado] ?? 'bg-slate-100 text-slate-600 border-slate-300'} capitalize`}>
                                                {caf.estado}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {caf.estado === 'activo' && (
                                                <button
                                                    type="button"
                                                    data-testid={`btn-revocar-${caf.id}`}
                                                    onClick={() => onRevocar(caf)}
                                                    className="px-3 py-1 text-xs font-bold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-md transition-colors"
                                                >
                                                    Revocar
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};

export default TablaCafsHistorial;
