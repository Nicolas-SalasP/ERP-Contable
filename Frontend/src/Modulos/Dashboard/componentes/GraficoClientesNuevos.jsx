import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

export default function GraficoClientesNuevos({ datos = [] }) {
    if (!datos.length) return null;

    const series = datos.map((d) => ({
        mes: d.mes.slice(5),
        cantidad: d.cantidad,
    }));

    const total = datos.reduce((s, d) => s + d.cantidad, 0);
    if (total === 0) return null;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
            <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide">
                    Clientes Nuevos (6 meses)
                </h3>
                <span className="text-xs font-bold text-indigo-600 dark:text-indigo-400">{total} total</span>
            </div>
            <ResponsiveContainer width="100%" height={180}>
                <BarChart data={series} margin={{ left: 0, right: 10 }}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="mes" tick={{ fontSize: 11 }} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                    <Tooltip formatter={(v) => [`${v} clientes`, 'Nuevos']} />
                    <Bar dataKey="cantidad" name="Clientes nuevos" fill="#6366f1" radius={[4, 4, 0, 0]} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
