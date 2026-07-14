import { useRegisterSW } from 'virtual:pwa-register/react';
import { RefreshCw, X } from 'lucide-react';

const UNA_HORA_MS = 60 * 60 * 1000;

// registerType:'prompt' (deliberado, ver vite.config.js) no recarga solo -- si lo hiciera
// silenciosamente se perderian formularios a medio llenar. Este banner deja la decision
// de cuando recargar en manos del usuario, pero evita que se quede trabajando indefinidamente
// contra una version vieja (con el consiguiente desfase de contrato con la API nueva).
export default function ActualizacionDisponible() {
    const {
        needRefresh: [needRefresh, setNeedRefresh],
        updateServiceWorker,
    } = useRegisterSW({
        onRegisteredSW(_url, registration) {
            if (!registration) return;
            // Un tab dejado abierto por horas nunca vuelve a chequear el SW por si solo
            // (Workbox solo revisa en navegacion); se fuerza un chequeo periodico.
            setInterval(() => registration.update(), UNA_HORA_MS);
        },
    });

    if (!needRefresh) return null;

    return (
        <div className="fixed bottom-5 left-1/2 -translate-x-1/2 z-[9999] flex items-center gap-3 px-4 py-3 rounded-xl border shadow-lg bg-slate-900 text-white border-slate-700 max-w-full">
            <RefreshCw size={18} strokeWidth={1.75} className="shrink-0 text-emerald-400" />
            <p className="text-sm leading-snug">Hay una nueva versión disponible.</p>
            <button
                onClick={() => updateServiceWorker(true)}
                className="shrink-0 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-colors"
            >
                Actualizar ahora
            </button>
            <button
                onClick={() => setNeedRefresh(false)}
                className="shrink-0 opacity-50 hover:opacity-100 transition-opacity"
                title="Recordar más tarde"
            >
                <X size={14} strokeWidth={1.75} />
            </button>
        </div>
    );
}
