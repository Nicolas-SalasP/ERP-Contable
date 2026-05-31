import React from 'react';

const colorPorcentaje = (disponibles, total) => {
    if (!total || total <= 0) return { bar: 'bg-slate-300', tag: 'bg-slate-100 text-slate-600' };
    const pct = (disponibles / total) * 100;
    if (pct < 20) return { bar: 'bg-rose-500',    tag: 'bg-rose-100 text-rose-700' };
    if (pct < 50) return { bar: 'bg-amber-500',   tag: 'bg-amber-100 text-amber-700' };
    return                  { bar: 'bg-emerald-500', tag: 'bg-emerald-100 text-emerald-700' };
};

const TablaSaldosCaf = ({ saldos, cargando }) => {
    if (cargando) {
        return (
            <div data-testid="saldos-loading" className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div className="animate-pulse space-y-3">
                    <div className="h-4 bg-slate-200 rounded w-1/4" />
                    <div className="h-3 bg-slate-100 rounded" />
                    <div className="h-3 bg-slate-100 rounded w-5/6" />
                    <div className="h-3 bg-slate-100 rounded w-4/6" />
                </div>
            </div>
        );
    }

    const tipos = Object.values(saldos ?? {});

    if (tipos.length === 0) {
        return (
            <div data-testid="saldos-empty" className="bg-white rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center animate-fade-in">
                <div className="text-5xl mb-3">📭</div>
                <h3 className="text-lg font-bold text-slate-700">Aun no has cargado CAFs</h3>
                <p className="text-sm text-slate-500 mt-1">Carga tu primer CAF abajo para empezar a emitir DTEs.</p>
            </div>
        );
    }

    return (
        <div className="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden animate-fade-in" data-testid="saldos-tabla">
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b border-slate-200">
                        <tr className="text-left text-xs font-bold uppercase tracking-wide text-slate-600">
                            <th className="px-4 py-3">Tipo DTE</th>
                            <th className="px-4 py-3 text-right">Total</th>
                            <th className="px-4 py-3">Disponibles</th>
                            <th className="px-4 py-3 text-right">Usados</th>
                            <th className="px-4 py-3 text-right hidden md:table-cell">Huerfanos <span title="Folios reservados que no llegaron a emitirse. No se reutilizan (regla SII)." className="cursor-help text-slate-400">ⓘ</span></th>
                            <th className="px-4 py-3 text-center">CAFs Activos</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {tipos.map((s) => {
                            const color = colorPorcentaje(s.disponibles, s.total_autorizado);
                            const pct = s.total_autorizado > 0 ? Math.min(100, Math.round((s.disponibles / s.total_autorizado) * 100)) : 0;
                            return (
                                <tr key={s.tipo_dte} data-testid={`saldo-${s.tipo_dte}`} className="hover:bg-slate-50/50">
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs font-mono bg-slate-100 text-slate-700 px-2 py-0.5 rounded">{s.tipo_dte}</span>
                                            <span className="font-medium text-slate-800">{s.nombre}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-slate-700">{s.total_autorizado}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <span className={`text-xs font-bold px-2 py-0.5 rounded ${color.tag}`} data-testid={`saldo-${s.tipo_dte}-disponibles-tag`}>{s.disponibles}</span>
                                            <div className="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden min-w-[60px]">
                                                <div data-testid={`saldo-${s.tipo_dte}-bar`} className={`h-full ${color.bar} transition-all`} style={{ width: `${pct}%` }} />
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right font-mono text-slate-700">{s.usados}</td>
                                    <td className="px-4 py-3 text-right font-mono text-slate-700 hidden md:table-cell">{s.huerfanos}</td>
                                    <td className="px-4 py-3 text-center">
                                        <span className="inline-flex items-center justify-center min-w-[2rem] h-7 px-2 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">{s.cafs_activos}</span>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
};

export default TablaSaldosCaf;
