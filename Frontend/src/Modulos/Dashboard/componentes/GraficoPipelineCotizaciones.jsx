import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Cell } from 'recharts';
import { formatearMoneda } from '../../../Utilidades/formato';

const COLORES_ESTADO = {
    'Borrador':  '#94a3b8',
    'Enviada':   '#3b82f6',
    'Aceptada':  '#10b981',
    'Rechazada': '#ef4444',
    'Expirada':  '#f59e0b',
};

export default function GraficoPipelineCotizaciones({ datos }) {
    if (!datos?.etapas?.length) return null;

    const series = datos.etapas.filter((e) => e.cantidad > 0);
    if (!series.length) return null;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4">
            <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide">
                    Pipeline de Cotizaciones (12 meses)
                </h3>
                {datos.tasa_conversion !== null && datos.tasa_conversion !== undefined && (
                    <span className="text-xs font-bold bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 px-2 py-1 rounded-full">
                        {datos.tasa_conversion}% conversión
                    </span>
                )}
            </div>
            <ResponsiveContainer width="100%" height={200}>
                <BarChart data={series} layout="vertical" margin={{ left: 10, right: 20 }}>
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                    <XAxis type="number" tick={{ fontSize: 11 }} allowDecimals={false} />
                    <YAxis type="category" dataKey="estado" tick={{ fontSize: 12 }} width={75} />
                    <Tooltip
                        formatter={(v, name, props) => [
                            `${v} cotizaciones (${formatearMoneda(props.payload.monto)})`,
                            'Cantidad',
                        ]}
                    />
                    <Bar dataKey="cantidad" radius={[0, 4, 4, 0]}>
                        {series.map((entry) => (
                            <Cell key={entry.estado} fill={COLORES_ESTADO[entry.estado] || '#6366f1'} />
                        ))}
                    </Bar>
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
