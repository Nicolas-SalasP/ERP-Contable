import { Link } from 'react-router-dom';

const formatCLP = (v) => {
    if (v >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
    if (v >= 1_000) return `$${(v / 1_000).toFixed(0)}K`;
    return `$${v}`;
};

export default function GraficoOrdenesPendientes({ datos }) {
    if (!datos || datos.cantidad === 0) return null;

    return (
        <div className="bg-white dark:bg-slate-800 rounded-xl shadow p-4 flex items-center justify-between">
            <div>
                <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wide">
                    Órdenes de Compra Pendientes
                </h3>
                <p className="text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{datos.cantidad}</p>
                <p className="text-sm text-slate-500 dark:text-slate-400">{formatCLP(datos.monto_total)} comprometido</p>
            </div>
            <Link to="/comercial/ordenes-compra" className="text-indigo-500 hover:text-indigo-700 text-sm font-medium">
                Ver todas →
            </Link>
        </div>
    );
}
