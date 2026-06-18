import { Inbox } from 'lucide-react';

export function EstadoVacio({
    mensaje = 'No hay datos para mostrar.',
    detalle = null,
    Icono = Inbox,
    accion = null,
}) {
    return (
        <tr>
            <td colSpan={99}>
                <div className="flex flex-col items-center justify-center py-16 px-4 text-center">
                    <div className="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                        <Icono size={28} strokeWidth={1.25} className="text-slate-400 dark:text-slate-500" />
                    </div>
                    <p className="text-sm font-semibold text-slate-600 dark:text-slate-300">{mensaje}</p>
                    {detalle && <p className="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-xs">{detalle}</p>}
                    {accion && <div className="mt-4">{accion}</div>}
                </div>
            </td>
        </tr>
    );
}

export function EstadoVacioDiv({
    mensaje = 'No hay datos para mostrar.',
    detalle = null,
    Icono = Inbox,
    accion = null,
    className = '',
}) {
    return (
        <div className={`flex flex-col items-center justify-center py-16 px-4 text-center ${className}`}>
            <div className="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center mb-4">
                <Icono size={28} strokeWidth={1.25} className="text-slate-400 dark:text-slate-500" />
            </div>
            <p className="text-sm font-semibold text-slate-600 dark:text-slate-300">{mensaje}</p>
            {detalle && <p className="text-xs text-slate-400 dark:text-slate-500 mt-1 max-w-xs">{detalle}</p>}
            {accion && <div className="mt-4">{accion}</div>}
        </div>
    );
}
