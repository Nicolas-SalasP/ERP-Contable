import React from 'react';
import { useAuth } from '../Contextos/AuthContext';

// Clases Tailwind completas por estado (no interpolar bg-${color}: el purgador no las detecta).
const ESTILOS = {
    grace:     { wrap: 'bg-amber-50 border-amber-200', text: 'text-amber-800', btn: 'bg-amber-600' },
    read_only: { wrap: 'bg-red-50 border-red-200',     text: 'text-red-800',   btn: 'bg-red-600' },
    expired:   { wrap: 'bg-red-50 border-red-200',     text: 'text-red-800',   btn: 'bg-red-600' },
    inactive:  { wrap: 'bg-blue-50 border-blue-200',   text: 'text-blue-800',  btn: 'bg-blue-600' },
};

const diasRestantes = (endsAt) => {
    if (!endsAt) return 0;
    return Math.max(0, Math.ceil((new Date(endsAt).getTime() - Date.now()) / 86400000));
};

const mensaje = (status, dias) => {
    switch (status) {
        case 'grace':     return `Tu plan vence en ${dias} ${dias === 1 ? 'día' : 'días'}. Renueva para no perder acceso.`;
        case 'read_only': return 'Solo lectura: tu plan venció. Renueva para volver a operar.';
        case 'expired':   return 'Acceso suspendido. Renueva tu plan para continuar.';
        case 'inactive':  return 'Sin plan activo. Elige un plan para comenzar.';
        default:          return '';
    }
};

const BannerSuscripcion = () => {
    const auth = useAuth();
    const status = auth?.user?.subscription_status;
    const cfg = ESTILOS[status];
    if (!cfg) return null;

    const dias = diasRestantes(auth?.user?.subscription_ends_at);

    return (
        <div data-testid="banner-suscripcion" className={`border-b px-6 py-2.5 flex items-center justify-between gap-3 ${cfg.wrap}`}>
            <span className={`text-sm font-bold ${cfg.text}`}>{mensaje(status, dias)}</span>
            <a
                href="https://tenri.cl/servicios"
                target="_blank"
                rel="noreferrer"
                className={`text-xs font-black px-4 py-1.5 rounded-lg text-white shrink-0 ${cfg.btn}`}
            >
                Ver planes
            </a>
        </div>
    );
};

export default BannerSuscripcion;
