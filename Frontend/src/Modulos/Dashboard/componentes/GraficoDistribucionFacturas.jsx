import { PieChart, Pie, Cell, Tooltip, Legend, ResponsiveContainer } from 'recharts';

const formatCLP = (v) => {
    if (v >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000) return `$${(v / 1_000).toFixed(0)}K`;
    return `$${v}`;
};

const COLORES = {
    pagadas:    '#10b981',
    pendientes: '#3b82f6',
    vencidas:   '#ef4444',
    anuladas:   '#94a3b8',
};

const ETIQUETAS = {
    pagadas:    'Pagadas',
    pendientes: 'Pendientes',
    vencidas:   'Vencidas',
    anuladas:   'Anuladas',
};

export default function GraficoDistribucionFacturas({ datos }) {
    if (!datos) return null;

    const series = Object.entries(datos)
        .filter(([, v]) => v.cantidad > 0)
        .map(([key, v]) => ({
            name:  ETIQUETAS[key] || key,
            value: v.cantidad,
            monto: v.monto,
            color: COLORES[key] || '#6366f1',
        }));

    if (!series.length) return null;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
            <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3 uppercase tracking-wide">
                Distribución de Facturas
            </h3>
            <ResponsiveContainer width="100%" height={220}>
                <PieChart>
                    <Pie
                        data={series}
                        cx="50%"
                        cy="50%"
                        innerRadius={55}
                        outerRadius={85}
                        dataKey="value"
                        label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                        labelLine={false}
                    >
                        {series.map((entry) => (
                            <Cell key={entry.name} fill={entry.color} />
                        ))}
                    </Pie>
                    <Tooltip formatter={(v, name, props) => [
                        `${v} facturas (${formatCLP(props.payload.monto)})`,
                        name,
                    ]} />
                    <Legend />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
}
