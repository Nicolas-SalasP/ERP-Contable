import * as Sentry from '@sentry/react';

const dsn = import.meta.env?.VITE_SENTRY_DSN;
const activo = Boolean(dsn) && import.meta.env?.PROD;

if (activo) {
    Sentry.init({
        dsn,
        environment: import.meta.env.MODE ?? 'production',
        release: import.meta.env.VITE_APP_VERSION,
        integrations: [
            Sentry.browserTracingIntegration(),
        ],
        tracesSampleRate: 0.1,
    });
}

/** Reporta una excepción a Sentry; no-op si VITE_SENTRY_DSN no está configurada o en modo desarrollo. */
export const captureException = (error, contexto) => {
    if (activo) Sentry.captureException(error, contexto);
};

/** Reporta un mensaje informativo/warning a Sentry. */
export const captureMessage = (mensaje, nivel = 'info') => {
    if (activo) Sentry.captureMessage(mensaje, nivel);
};
