import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';

// Formatea monto abreviado para los ticks del eje Y
const formatAbreviado = (v) => {
  if (v >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
  if (v >= 1_000) return `$${(v / 1_000).toFixed(0)}K`;
  return `$${v}`;
};

// Formatea el nombre del mes "2025-07" → "Jul 25"
const formatMes = (valor) => {
  if (!valor) return '';
  const [anio, mes] = valor.split('-');
  const fecha = new Date(Number(anio), Number(mes) - 1, 1);
  const etiqueta = fecha.toLocaleDateString('es-CL', {
    month: 'short',
    year: '2-digit',
  });
  // Capitalizar primera letra
  return etiqueta.charAt(0).toUpperCase() + etiqueta.slice(1);
};

const formatCLP = new Intl.NumberFormat('es-CL', {
  style: 'currency',
  currency: 'CLP',
  maximumFractionDigits: 0,
});

// Tooltip personalizado con monto completo en CLP
const TooltipPersonalizado = ({ active, payload, label }) => {
  if (!active || !payload || payload.length === 0) return null;
  return (
    <div className="bg-slate-800 text-white text-xs rounded-lg px-3 py-2 shadow-lg">
      <p className="font-medium mb-1">{formatMes(label)}</p>
      <p>{formatCLP.format(payload[0].value)}</p>
    </div>
  );
};

/**
 * Gráfico de línea: ventas de los últimos 12 meses.
 *
 * @param {{ datos: Array<{mes: string, monto: number}> }} props
 */
export default function GraficoVentas({ datos = [] }) {
  const sinDatos =
    !datos ||
    datos.length === 0 ||
    datos.every((d) => d.monto === 0);

  return (
    <div className="bg-white rounded-xl shadow-sm p-5 border border-gray-100">
      <h3 className="text-sm font-semibold text-slate-700 mb-4">
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
              stroke="#1e293b"
              strokeOpacity={0.15}
              vertical={false}
            />
            <XAxis
              dataKey="mes"
              tickFormatter={formatMes}
              tick={{ fontSize: 11, fill: '#64748b' }}
              axisLine={false}
              tickLine={false}
            />
            <YAxis
              tickFormatter={formatAbreviado}
              tick={{ fontSize: 11, fill: '#64748b' }}
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
