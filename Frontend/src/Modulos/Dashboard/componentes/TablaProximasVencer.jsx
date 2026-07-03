import { formatearMoneda } from '../../../Utilidades/formato';

export default function TablaProximasVencer({ datos = [] }) {
    if (!datos.length) return null;

    const formatCLP = formatearMoneda;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
            <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">
                Facturas que Vencen en 7 Días
            </h3>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-xs text-slate-400 uppercase border-b border-slate-100 dark:border-slate-700">
                            <th className="text-left pb-2">N° Factura</th>
                            <th className="text-left pb-2">Vencimiento</th>
                            <th className="text-left pb-2">Días</th>
                            <th className="text-right pb-2">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((f) => (
                            <tr key={f.id} className="border-b border-slate-50 dark:border-slate-700/50 last:border-0">
                                <td className="py-2 font-mono text-xs text-slate-600 dark:text-slate-400">{f.numero_factura}</td>
                                <td className="py-2 text-slate-600 dark:text-slate-400">{f.fecha_vencimiento}</td>
                                <td className="py-2">
                                    <span className={`text-xs font-bold px-2 py-0.5 rounded-full ${
                                        f.dias_restantes <= 2
                                            ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300'
                                    }`}>
                                        {f.dias_restantes === 0 ? 'Hoy' : `${f.dias_restantes}d`}
                                    </span>
                                </td>
                                <td className="py-2 text-right font-semibold text-slate-700 dark:text-slate-300">
                                    {formatCLP(f.monto_bruto)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
