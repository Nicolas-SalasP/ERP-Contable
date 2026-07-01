import { ComposedChart, Bar, Line, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';

const formatCLP = (v) => {
    if (Math.abs(v) >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
    if (Math.abs(v) >= 1_000) return `$${(v / 1_000).toFixed(0)}K`;
    return `$${v}`;
};

export default function GraficoMargenBruto({ ventas = [], compras = [] }) {
    const datos = ventas.map((v) => {
        const compra = compras.find((c) => c.mes === v.mes);
        const margen = v.monto - (compra?.monto ?? 0);
        return {
            mes: v.mes.slice(5),
            ventas: v.monto,
            compras: compra?.monto ?? 0,
            margen,
        };
    });

    if (!datos.length) return null;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
            <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">
                Ventas vs Compras y Margen Bruto (12 meses)
            </h3>
            <ResponsiveContainer width="100%" height={260}>
                <ComposedChart data={datos} margin={{ left: 10, right: 10 }}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="mes" tick={{ fontSize: 11 }} />
                    <YAxis tickFormatter={formatCLP} tick={{ fontSize: 11 }} width={60} />
                    <Tooltip formatter={(v, name) => [formatCLP(v), name]} />
                    <Legend />
                    <Bar dataKey="ventas" name="Ventas" fill="#10b981" radius={[3, 3, 0, 0]} />
                    <Bar dataKey="compras" name="Compras" fill="#f59e0b" radius={[3, 3, 0, 0]} />
                    <Line dataKey="margen" name="Margen" stroke="#3b82f6" strokeWidth={2} dot={false} type="monotone" />
                </ComposedChart>
            </ResponsiveContainer>
        </div>
    );
}
