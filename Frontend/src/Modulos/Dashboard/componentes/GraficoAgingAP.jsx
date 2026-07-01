import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts';

const formatCLP = (v) => {
    if (v >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000) return `$${(v / 1_000).toFixed(0)}K`;
    return `$${v}`;
};

const COLORES = {
    '0-30': '#10b981',
    '31-60': '#a78bfa',
    '61-90': '#ef4444',
    '91+': '#dc2626',
};

export default function GraficoAgingAP({ datos = [] }) {
    if (!datos.length) return null;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
            <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">
                Aging Cuentas por Pagar
            </h3>
            <ResponsiveContainer width="100%" height={200}>
                <BarChart data={datos} layout="vertical" margin={{ left: 20, right: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                    <XAxis type="number" tickFormatter={formatCLP} tick={{ fontSize: 11 }} />
                    <YAxis type="category" dataKey="tramo" tick={{ fontSize: 12 }} width={50} />
                    <Tooltip formatter={(v) => formatCLP(v)} />
                    <Bar dataKey="monto" radius={[0, 4, 4, 0]}>
                        {datos.map((entry) => (
                            <Cell key={entry.tramo} fill={COLORES[entry.tramo] || '#6366f1'} />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
