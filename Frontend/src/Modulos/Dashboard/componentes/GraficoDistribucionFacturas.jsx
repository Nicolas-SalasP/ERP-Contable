import { useState } from 'react';
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

export default function GraficoDistribucionFacturas({ datos, onSegmentoClick }) {
    const [indiceActivo, setIndiceActivo] = useState(null);

    if (!datos) return null;

    const totalFacturas = Object.values(datos).reduce((acc, v) => acc + v.cantidad, 0);

    const series = Object.entries(datos)
        .filter(([, v]) => v.cantidad > 0)
        .map(([key, v]) => ({
            clave: key,
            name:  ETIQUETAS[key] || key,
            value: v.cantidad,
            monto: v.monto,
            color: COLORES[key] || '#6366f1',
        }));

    if (!series.length) return null;

    const clickeable = typeof onSegmentoClick === 'function';

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
                        onMouseEnter={(_, indice) => setIndiceActivo(indice)}
                        onMouseLeave={() => setIndiceActivo(null)}
                        onClick={clickeable ? (entry) => onSegmentoClick(entry.clave) : undefined}
                        cursor={clickeable ? 'pointer' : 'default'}
                    >
                        {series.map((entry, indice) => (
                            <Cell
                                key={entry.name}
                                fill={entry.color}
                                stroke={indice === indiceActivo ? '#0f172a' : undefined}
                                strokeWidth={indice === indiceActivo ? 2 : 0}
                                opacity={indiceActivo === null || indice === indiceActivo ? 1 : 0.45}
                            />
                        ))}
                    </Pie>
                    <Tooltip formatter={(v, name, props) => [
                        `${v} facturas (${formatCLP(props.payload.monto)}) · ${totalFacturas > 0 ? ((v / totalFacturas) * 100).toFixed(1) : 0}%`,
                        name,
                    ]} />
                    <Legend />
                </PieChart>
            </ResponsiveContainer>
        </div>
    );
}
