export default function GraficoFlujoCaja({ datos }) {
    if (!datos) return null;

    const formatCLP = (v) => {
        if (Math.abs(v) >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
        if (Math.abs(v) >= 1_000) return `$${(v / 1_000).toFixed(0)}K`;
        return `$${v}`;
    };

    const { entradas_30d, salidas_30d, neto_30d } = datos;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
            <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">
                Flujo de Caja — Próximos 30 días
            </h3>
            <div className="grid grid-cols-3 gap-3">
                <div className="text-center p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Entradas esperadas</p>
                    <p className="text-lg font-bold text-emerald-600 dark:text-emerald-400">{formatCLP(entradas_30d)}</p>
                </div>
                <div className="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Salidas proyectadas</p>
                    <p className="text-lg font-bold text-red-500 dark:text-red-400">{formatCLP(salidas_30d)}</p>
                </div>
                <div className={`text-center p-3 rounded-lg ${neto_30d >= 0 ? 'bg-blue-50 dark:bg-blue-900/20' : 'bg-orange-50 dark:bg-orange-900/20'}`}>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Neto proyectado</p>
                    <p className={`text-lg font-bold ${neto_30d >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-orange-600 dark:text-orange-400'}`}>
                        {neto_30d >= 0 ? '+' : ''}{formatCLP(neto_30d)}
                    </p>
                </div>
            </div>
        </div>
    );
}
