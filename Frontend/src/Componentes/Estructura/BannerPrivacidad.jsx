import React, { useState, useEffect } from 'react';
import privacidadApi from '../../Modulos/Privacidad/privacidadApi';

const BannerPrivacidad = () => {
    const [visible, setVisible] = useState(false);
    const [politica, setPolitica] = useState(null);
    const [expanded, setExpanded] = useState(false);
    const [aceptando, setAceptando] = useState(false);

    useEffect(() => {
        let cancelled = false;

        const cargar = async () => {
            try {
                const [consentimiento, pol] = await Promise.all([
                    privacidadApi.miConsentimiento(),
                    privacidadApi.obtenerPolitica().catch(() => null),
                ]);

                if (cancelled) return;

                if (consentimiento?.data?.aceptada === false) {
                    setPolitica(pol?.data || null);
                    setVisible(true);
                }
            } catch {
                // Silently ignore all errors — never break the app
            }
        };

        cargar();
        return () => { cancelled = true; };
    }, []);

    const handleAceptar = async () => {
        setAceptando(true);
        try {
            await privacidadApi.aceptar();
            setVisible(false);
        } catch {
            // Silently ignore
        } finally {
            setAceptando(false);
        }
    };

    const handleMasTarde = () => {
        setVisible(false);
    };

    if (!visible) return null;

    return (
        <div
            data-testid="banner-privacidad"
            className="fixed bottom-0 left-0 right-0 z-50 bg-slate-800 text-white shadow-2xl border-t border-slate-600 pb-safe"
        >
            <div className="max-w-4xl mx-auto px-4 py-4">
                <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <div className="flex-1">
                        <p className="text-sm font-semibold">
                            {politica?.titulo || 'Política de Privacidad'}
                        </p>
                        <p className="text-xs text-slate-300 mt-1">
                            Para continuar usando el sistema, necesitamos tu aceptación de nuestra política de privacidad (Ley 21.719).
                        </p>
                        {expanded && politica?.contenido && (
                            <p className="text-xs text-slate-300 mt-2 border-t border-slate-600 pt-2">
                                {politica.contenido}
                            </p>
                        )}
                        {politica?.contenido && (
                            <button
                                onClick={() => setExpanded((e) => !e)}
                                className="text-xs text-blue-300 underline mt-1"
                            >
                                {expanded ? 'Ocultar' : 'Leer política completa'}
                            </button>
                        )}
                    </div>
                    <div className="flex gap-2 shrink-0">
                        <button
                            data-testid="btn-mas-tarde"
                            onClick={handleMasTarde}
                            className="text-xs px-3 py-1.5 rounded border border-slate-500 text-slate-300 hover:bg-slate-700"
                        >
                            Más tarde
                        </button>
                        <button
                            data-testid="btn-aceptar-privacidad"
                            onClick={handleAceptar}
                            disabled={aceptando}
                            className="text-xs px-4 py-1.5 rounded bg-blue-600 text-white font-semibold hover:bg-blue-500 disabled:opacity-50"
                        >
                            {aceptando ? 'Guardando...' : 'Aceptar'}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default BannerPrivacidad;
