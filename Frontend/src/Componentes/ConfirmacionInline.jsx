import { useState } from 'react';
import { Trash2, Check, X } from 'lucide-react';

export function BotonEliminar({
    onConfirmar,
    labelInicial = 'Eliminar',
    labelConfirmar = '¿Confirmar?',
    disabled = false,
    className = '',
}) {
    const [pidiendo, setPidiendo] = useState(false);

    if (!pidiendo) {
        return (
            <button
                onClick={() => setPidiendo(true)}
                disabled={disabled}
                className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 border border-transparent hover:border-red-200 dark:hover:border-red-800 transition-colors disabled:opacity-40 ${className}`}
            >
                <Trash2 size={13} strokeWidth={1.75} />
                {labelInicial}
            </button>
        );
    }

    return (
        <div className="flex items-center gap-1 animate-fade-in">
            <span className="text-xs text-slate-500 dark:text-slate-400 mr-1">{labelConfirmar}</span>
            <button
                onClick={async () => { await onConfirmar(); setPidiendo(false); }}
                className="flex items-center gap-1 px-2.5 py-1.5 text-xs font-bold rounded-lg bg-red-600 text-white hover:bg-red-700 transition-colors"
            >
                <Check size={12} strokeWidth={2.5} />
                Sí
            </button>
            <button
                onClick={() => setPidiendo(false)}
                className="flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
            >
                <X size={12} strokeWidth={2} />
                No
            </button>
        </div>
    );
}

export function useConfirmacion() {
    const [pendiente, setPendiente] = useState(null);

    const pedir = (id) => setPendiente(id);
    const cancelar = () => setPendiente(null);
    const esPendiente = (id) => pendiente === id;
    const confirmar = async (fn) => { await fn(); setPendiente(null); };

    return { pedir, cancelar, esPendiente, confirmar };
}
