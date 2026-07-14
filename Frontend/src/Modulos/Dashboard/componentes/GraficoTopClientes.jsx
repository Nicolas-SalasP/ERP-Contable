import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Cell,
} from 'recharts';
import { formatearMoneda } from '../../../Utilidades/formato';

// Tonos de esmeralda para diferencias visuales entre barras (más oscuro al más claro)
const COLORES_ESMERALDA = [
  '#059669', // emerald-600
  '#10b981', // emerald-500
  '#34d399', // emerald-400
  '#6ee7b7', // emerald-300
  '#a7f3d0', // emerald-200
];

const formatAbreviado = (v) => {
  if (v >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
  if (v >= 1_000) return `$${(v / 1_000).toFixed(0)}K`;
  return `$${v}`;
};

const formatCLP = { format: formatearMoneda };

const TooltipPersonalizado = ({ active, payload }) => {
  if (!active || !payload || payload.length === 0) return null;
  const { nombre, monto } = payload[0].payload;
  return (
    <div className="bg-slate-800 text-white text-xs rounded-lg px-3 py-2 shadow-lg max-w-[200px]">
      <p className="font-medium mb-1 truncate">{nombre}</p>
      <p>{formatCLP.format(monto)}</p>
    </div>
  );
};

const tickNombre = (valor) => {
  if (!valor) return '';
  return valor.length > 16 ? `${valor.slice(0, 15)}…` : valor;
};

export default function GraficoTopClientes({ datos = [], onClienteClick }) {
  const oscuro = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
  const sinDatos =
    !datos ||
    datos.length === 0 ||
    datos.every((d) => d.monto === 0);

  const clickeable = typeof onClienteClick === 'function';

  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-slate-700">
      <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4">
        Top clientes (últimos 12 meses)
        {clickeable && <span className="ml-2 text-[10px] font-normal text-slate-400">(click para ver ficha)</span>}
      </h3>

      {sinDatos ? (
        <div className="flex items-center justify-center h-[200px] text-sm text-slate-400">
          Sin datos de clientes
        </div>
      ) : (
        <ResponsiveContainer width="100%" height={200}>
          <BarChart
            data={datos}
            layout="vertical"
            margin={{ top: 0, right: 16, left: 0, bottom: 0 }}
          >
            <CartesianGrid
              strokeDasharray="3 3"
              stroke={oscuro ? '#334155' : '#e2e8f0'}
              strokeOpacity={1}
              horizontal={false}
            />
            <XAxis
              type="number"
              tickFormatter={formatAbreviado}
              tick={{ fontSize: 11, fill: oscuro ? '#94a3b8' : '#64748b' }}
              axisLine={false}
              tickLine={false}
            />
            <YAxis
              type="category"
              dataKey="nombre"
              width={120}
              tickFormatter={tickNombre}
              tick={{ fontSize: 11, fill: oscuro ? '#94a3b8' : '#334155' }}
              axisLine={false}
              tickLine={false}
            />
            <Tooltip
              content={<TooltipPersonalizado />}
              cursor={{ fill: oscuro ? '#1e293b' : '#f1f5f9' }}
            />
            <Bar
              dataKey="monto"
              radius={[0, 4, 4, 0]}
              cursor={clickeable ? 'pointer' : 'default'}
              onClick={clickeable ? (data) => data?.id && onClienteClick(data.id) : undefined}
            >
              {datos.map((_, indice) => (
                <Cell
                  key={`celda-${indice}`}
                  fill={COLORES_ESMERALDA[indice % COLORES_ESMERALDA.length]}
                />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      )}
    </div>
  );
}
