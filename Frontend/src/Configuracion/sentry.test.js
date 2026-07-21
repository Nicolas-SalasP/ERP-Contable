import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const initMock = vi.fn();
const captureExceptionMock = vi.fn();
const captureMessageMock = vi.fn();

vi.mock('@sentry/react', () => ({
    init: (...args) => initMock(...args),
    browserTracingIntegration: vi.fn(() => ({})),
    captureException: (...args) => captureExceptionMock(...args),
    captureMessage: (...args) => captureMessageMock(...args),
}));

beforeEach(() => {
    vi.resetModules();
    vi.clearAllMocks();
    vi.unstubAllEnvs();
});

afterEach(() => {
    vi.unstubAllEnvs();
});

describe('sentry (config)', () => {
    it('no inicializa Sentry ni reporta si VITE_SENTRY_DSN no esta configurada', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', '');
        vi.stubEnv('PROD', true);

        const { captureException, captureMessage } = await import('./sentry');
        captureException(new Error('boom'));
        captureMessage('hola');

        expect(initMock).not.toHaveBeenCalled();
        expect(captureExceptionMock).not.toHaveBeenCalled();
        expect(captureMessageMock).not.toHaveBeenCalled();
    });

    it('no inicializa Sentry en modo desarrollo aunque haya DSN', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://example@sentry.io/1');
        vi.stubEnv('PROD', false);

        await import('./sentry');

        expect(initMock).not.toHaveBeenCalled();
    });

    it('inicializa Sentry con dsn/environment/release cuando hay DSN y esta en produccion', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://example@sentry.io/1');
        vi.stubEnv('PROD', true);
        vi.stubEnv('MODE', 'production');
        vi.stubEnv('VITE_APP_VERSION', '1.11.0');

        await import('./sentry');

        expect(initMock).toHaveBeenCalledWith(
            expect.objectContaining({
                dsn: 'https://example@sentry.io/1',
                environment: 'production',
                release: '1.11.0',
                tracesSampleRate: 0.1,
            })
        );
    });

    it('captureException reporta a Sentry cuando esta activo', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://example@sentry.io/1');
        vi.stubEnv('PROD', true);

        const { captureException } = await import('./sentry');
        const error = new Error('boom');
        captureException(error, { extra: 1 });

        expect(captureExceptionMock).toHaveBeenCalledWith(error, { extra: 1 });
    });

    it('captureMessage reporta a Sentry con nivel default info', async () => {
        vi.stubEnv('VITE_SENTRY_DSN', 'https://example@sentry.io/1');
        vi.stubEnv('PROD', true);

        const { captureMessage } = await import('./sentry');
        captureMessage('mensaje');

        expect(captureMessageMock).toHaveBeenCalledWith('mensaje', 'info');
    });
});
