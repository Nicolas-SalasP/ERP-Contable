import { Link } from 'react-router-dom';

// Formatea monto abreviado en CLP
const formatAbreviado = (v) => {
  if (!v && v !== 0) return '$0';
  if (v >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
  if (v >= 1_000) return `$${(v / 1_000).toFixed(0)}K`;
  return `$${v}`;
};

/**
 * Tarjeta de estado de inventario: 3 KPIs en fila.
 *
 * @param {{
 *   datos?: { total_productos: number, bajo_minimo: number, valor_stock: number }
 * }} props
 */
export default function GraficoStock({ datos }) {
  const sinDatos = !datos || datos.total_productos === 0;

  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-5 border border-gray-100 dark:border-slate-700">
      <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-4">
        Inventario — estado actual
      </h3>

      {sinDatos ? (
        <div className="flex items-center justify-center h-[80px] text-sm text-slate-400">
          Sin datos de inventario
        </div>
      ) : (
        <div className="flex gap-4">
          {/* Productos activos */}
          <div className="flex-1 flex flex-col items-center gap-1 p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
            <i className="fas fa-boxes text-slate-400 text-lg" />
            <span className="text-xl font-bold text-slate-700 dark:text-slate-300">
              {datos.total_productos.toLocaleString('es-CL')}
            </span>
            <span className="text-xs text-slate-500 text-center">
              Productos activos
            </span>
          </div>

          {/* Bajo stock mínimo */}
          <div className="flex-1 flex flex-col items-center gap-1 p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
            <i
              className={`fas fa-exclamation-triangle text-lg ${
                datos.bajo_minimo > 0 ? 'text-rose-500' : 'text-slate-400'
              }`}
            />
            <span
              className={`text-xl font-bold ${
                datos.bajo_minimo > 0 ? 'text-rose-500' : 'text-slate-700'
              }`}
            >
              {datos.bajo_minimo.toLocaleString('es-CL')}
            </span>
            <span className="text-xs text-slate-500 text-center">
              Bajo stock mínimo
            </span>
          </div>

          {/* Valor en stock */}
          <div className="flex-1 flex flex-col items-center gap-1 p-3 rounded-lg bg-slate-50 dark:bg-slate-900">
            <i className="fas fa-dollar-sign text-slate-400 text-lg" />
            <span className="text-xl font-bold text-slate-700 dark:text-slate-300">
              {formatAbreviado(datos.valor_stock)}
            </span>
            <span className="text-xs text-slate-500 text-center">
              Valor en stock
            </span>
          </div>
        </div>
      )}

      <div className="mt-4">
        <Link
          to="/inventario/dashboard"
          className="text-xs text-emerald-600 hover:underline"
        >
          Ver dashboard de inventario →
        </Link>
      </div>
    </div>
  );
}
