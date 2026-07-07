import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';
import { formatearMoneda } from '../../../Utilidades/formato';

const formatAbreviado = (v) => {
  if (v >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
  if (v >= 1_000) return `$${(v / 1_000).toFixed(0)}K`;
  return `$${v}`;
};

const formatMes = (valor) => {
  if (!valor) return '';
  const [anio, mes] = valor.split('-');
  const fecha = new Date(Number(anio), Number(mes) - 1, 1);
  const etiqueta = fecha.toLocaleDateString('es-CL', {
    month: 'short',
    year: '2-digit',
  });
  return etiqueta.charAt(0).toUpperCase() + etiqueta.slice(1);
};

const formatCLP = { format: formatearMoneda };

const TooltipPersonalizado = ({ active, payload, label }) => {
  if (!active || !payload || payload.length === 0) return null;
  return (
    <div className="bg-slate-800 text-white text-xs rounded-lg px-3 py-2 shadow-lg">
      <p className="font-medium mb-1">{formatMes(label)}</p>
      <p>{formatCLP.format(payload[0].value)}</p>
    </div>
  );
};

export default function GraficoVentas({ datos = [] }) {
  const oscuro = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  const sinDatos =
    !datos ||
    datos.length === 0 ||
    datos.every((d) => d.monto === 0);

  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-slate-700">
      <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4">
        Ventas últimos 12 meses
      </h3>

      {sinDatos ? (
        <div className="flex items-center justify-center h-[220px] text-sm text-slate-400">
          Sin ventas registradas
        </div>
      ) : (
        <ResponsiveContainer width="100%" height={220}>
          <LineChart
            data={datos}
            margin={{ top: 4, right: 8, left: 0, bottom: 0 }}
          >
            <CartesianGrid
              strokeDasharray="3 3"
              stroke={oscuro ? '#334155' : '#e2e8f0'}
              strokeOpacity={1}
              vertical={false}
            />
            <XAxis
              dataKey="mes"
              tickFormatter={formatMes}
              tick={{ fontSize: 11, fill: oscuro ? '#94a3b8' : '#64748b' }}
              axisLine={false}
              tickLine={false}
            />
            <YAxis
              tickFormatter={formatAbreviado}
              tick={{ fontSize: 11, fill: oscuro ? '#94a3b8' : '#64748b' }}
              axisLine={false}
              tickLine={false}
              width={52}
            />
            <Tooltip
              content={<TooltipPersonalizado />}
              cursor={{ stroke: '#10b981', strokeWidth: 1, strokeDasharray: '4 2' }}
            />
            <Line
              type="monotone"
              dataKey="monto"
              stroke="#10b981"
              strokeWidth={2}
              dot={{ r: 3, fill: '#10b981', strokeWidth: 0 }}
              activeDot={{ r: 5, fill: '#10b981', strokeWidth: 0 }}
            />
          </LineChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}
