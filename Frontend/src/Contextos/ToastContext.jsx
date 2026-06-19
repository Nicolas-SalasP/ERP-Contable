import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { CheckCircle, XCircle, AlertTriangle, Info, X } from 'lucide-react';

const ToastContext = createContext(null);

const ICONOS = {
    success: CheckCircle,
    error:   XCircle,
    warning: AlertTriangle,
    info:    Info,
};

const COLORES = {
    success: 'bg-emerald-50 dark:bg-emerald-900/40 border-emerald-200 dark:border-emerald-700 text-emerald-800 dark:text-emerald-200',
    error:   'bg-red-50 dark:bg-red-900/40 border-red-200 dark:border-red-700 text-red-800 dark:text-red-200',
    warning: 'bg-amber-50 dark:bg-amber-900/40 border-amber-200 dark:border-amber-700 text-amber-800 dark:text-amber-200',
    info:    'bg-blue-50 dark:bg-blue-900/40 border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-200',
};

const ICONO_COLORES = {
    success: 'text-emerald-500',
    error:   'text-red-500',
    warning: 'text-amber-500',
    info:    'text-blue-500',
};

let _idCounter = 0;

function ToastItem({ id, mensaje, tipo, duracion, onClose }) {
    const [visible, setVisible] = useState(false);
    const [progreso, setProgreso] = useState(100);
    const intervaloRef = useRef(null);
    const Icono = ICONOS[tipo] ?? Info;

    useEffect(() => {
        requestAnimationFrame(() => setVisible(true));

        const paso = 100 / (duracion / 50);
        intervaloRef.current = setInterval(() => {
            setProgreso(p => {
                if (p <= 0) { clearInterval(intervaloRef.current); return 0; }
                return p - paso;
            });
        }, 50);

        const t = setTimeout(() => {
            setVisible(false);
            setTimeout(() => onClose(id), 300);
        }, duracion);

        return () => { clearTimeout(t); clearInterval(intervaloRef.current); };
    }, []);

    return (
        <div
            className={`
                relative flex items-start gap-3 w-80 max-w-full px-4 py-3 rounded-xl border shadow-lg
                transition-all duration-300 overflow-hidden
                ${visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'}
                ${COLORES[tipo]}
            `}
        >
            <Icono size={18} strokeWidth={1.75} className={`mt-0.5 shrink-0 ${ICONO_COLORES[tipo]}`} />
            <p className="text-sm flex-1 leading-snug">{mensaje}</p>
            <button
                onClick={() => { setVisible(false); setTimeout(() => onClose(id), 300); }}
                className="shrink-0 opacity-50 hover:opacity-100 transition-opacity"
            >
                <X size={14} strokeWidth={1.75} />
            </button>
            <div
                className="absolute bottom-0 left-0 h-0.5 bg-current opacity-30 transition-all duration-50 ease-linear"
                style={{ width: `${progreso}%` }}
            />
        </div>
    );
}

export function ToastProvider({ children }) {
    const [toasts, setToasts] = useState([]);

    const toast = useCallback((mensaje, tipo = 'info', duracion = 4000) => {
        const id = ++_idCounter;
        setToasts(prev => [...prev, { id, mensaje, tipo, duracion }]);
    }, []);

    const cerrar = useCallback((id) => {
        setToasts(prev => prev.filter(t => t.id !== id));
    }, []);

    return (
        <ToastContext.Provider value={{ toast }}>
            {children}
            <div className="fixed bottom-5 right-5 z-[9999] flex flex-col gap-2 items-end pointer-events-none">
                {toasts.map(t => (
                    <div key={t.id} className="pointer-events-auto">
                        <ToastItem {...t} onClose={cerrar} />
                    </div>
                ))}
            </div>
        </ToastContext.Provider>
    );
}

export function useToast() {
    const ctx = useContext(ToastContext);
    if (!ctx) throw new Error('useToast debe usarse dentro de ToastProvider');
    return ctx;
}
