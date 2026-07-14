import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  Brush,
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

const TooltipPersonalizado = ({ active, payload, label, comparando }) => {
  if (!active || !payload || payload.length === 0) return null;

  if (comparando) {
    const actual = payload.find((p) => p.dataKey === 'actual');
    const anterior = payload.find((p) => p.dataKey === 'anio_anterior');
    const valorActual = actual?.value ?? 0;
    const valorAnterior = anterior?.value ?? 0;
    const variacion =
      valorAnterior > 0 ? (((valorActual - valorAnterior) / valorAnterior) * 100).toFixed(1) : null;

    return (
      <div className="bg-slate-800 text-white text-xs rounded-lg px-3 py-2 shadow-lg">
        <p className="font-medium mb-1">{formatMes(label)}</p>
        <p className="text-emerald-300">Este año: {formatCLP.format(valorActual)}</p>
        <p className="text-slate-300">Año anterior: {formatCLP.format(valorAnterior)}</p>
        {variacion !== null && (
          <p className={`mt-1 font-bold ${Number(variacion) >= 0 ? 'text-emerald-400' : 'text-red-400'}`}>
            {Number(variacion) >= 0 ? '+' : ''}
            {variacion}% vs año anterior
          </p>
        )}
      </div>
    );
  }

  return (
    <div className="bg-slate-800 text-white text-xs rounded-lg px-3 py-2 shadow-lg">
      <p className="font-medium mb-1">{formatMes(label)}</p>
      <p>{formatCLP.format(payload[0].value)}</p>
    </div>
  );
};

export default function GraficoVentas({ datos = [], comparacion = null }) {
  const oscuro = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  const comparando = Boolean(comparacion?.serie?.length);
  const datosGrafico = comparando ? comparacion.serie : datos;

  const sinDatos =
    !datosGrafico ||
    datosGrafico.length === 0 ||
    (comparando
      ? datosGrafico.every((d) => (d.actual ?? 0) === 0 && (d.anio_anterior ?? 0) === 0)
      : datosGrafico.every((d) => d.monto === 0));

  const mostrarBrush = !sinDatos && datosGrafico.length > 6;

  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-slate-700">
      <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4">
        {comparando ? 'Ventas — este año vs año anterior' : 'Ventas últimos 12 meses'}
      </h3>

      {sinDatos ? (
        <div className="flex items-center justify-center h-[220px] text-sm text-slate-400">
          Sin ventas registradas
        </div>
      ) : (
        <ResponsiveContainer width="100%" height={comparando ? 260 : 220}>
          <LineChart
            data={datosGrafico}
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
              content={<TooltipPersonalizado comparando={comparando} />}
              cursor={{ stroke: '#10b981', strokeWidth: 1, strokeDasharray: '4 2' }}
            />
            {comparando && (
              <Legend
                wrapperStyle={{ fontSize: 12 }}
                formatter={(value) => (value === 'actual' ? 'Este año' : 'Año anterior')}
              />
            )}
            {comparando ? (
              <>
                <Line
                  type="monotone"
                  dataKey="actual"
                  name="actual"
                  stroke="#10b981"
                  strokeWidth={2}
                  dot={{ r: 3, fill: '#10b981', strokeWidth: 0 }}
                  activeDot={{ r: 5, fill: '#10b981', strokeWidth: 0 }}
                />
                <Line
                  type="monotone"
                  dataKey="anio_anterior"
                  name="anio_anterior"
                  stroke="#94a3b8"
                  strokeWidth={2}
                  strokeDasharray="4 3"
                  dot={{ r: 3, fill: '#94a3b8', strokeWidth: 0 }}
                  activeDot={{ r: 5, fill: '#94a3b8', strokeWidth: 0 }}
                />
              </>
            ) : (
              <Line
                type="monotone"
                dataKey="monto"
                stroke="#10b981"
                strokeWidth={2}
                dot={{ r: 3, fill: '#10b981', strokeWidth: 0 }}
                activeDot={{ r: 5, fill: '#10b981', strokeWidth: 0 }}
              />
            )}
            {mostrarBrush && (
              <Brush
                dataKey="mes"
                height={22}
                stroke="#10b981"
                tickFormatter={formatMes}
                travellerWidth={8}
              />
            )}
          </LineChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}
