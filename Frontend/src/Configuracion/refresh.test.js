import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { markTokenIssued } from './api';

const limpiarStorage = () => {
    window.localStorage.clear();
    window.sessionStorage.clear();
};

beforeEach(() => {
    limpiarStorage();
    vi.restoreAllMocks();
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('markTokenIssued', () => {
    it('guarda el timestamp actual cuando no se le pasa argumento', () => {
        markTokenIssued();
        const guardado = window.localStorage.getItem('erp_token_issued_at');
        expect(guardado).toBeTruthy();
        const fechaGuardada = new Date(guardado).getTime();
        const ahora = Date.now();
        expect(Math.abs(ahora - fechaGuardada)).toBeLessThan(5000); // < 5s
    });

    it('guarda el timestamp del argumento cuando se pasa', () => {
        const fecha = '2026-05-12T10:30:00Z';
        markTokenIssued(fecha);
        expect(window.localStorage.getItem('erp_token_issued_at')).toBe(fecha);
    });

    it('no rompe si localStorage no esta disponible', () => {
        const original = window.localStorage.setItem;
        window.localStorage.setItem = () => {
            throw new Error('Storage cuota agotada');
        };

        expect(() => markTokenIssued()).not.toThrow();

        window.localStorage.setItem = original;
    });
});

describe('refresh proactivo - integracion con request', () => {
    const importarApiFresca = async () => {
        vi.resetModules();
        return await import('./api');
    };

    it('no llama a refresh si el token es nuevo (recien emitido)', async () => {
        window.localStorage.setItem('erp_token', 'tok-nuevo');
        markTokenIssued();

        const { api } = await importarApiFresca();

        const fetchMock = vi.spyOn(global, 'fetch').mockResolvedValue(
            new Response(JSON.stringify({ success: true, data: [] }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            })
        );

        await api.get('/clientes');
        const urls = fetchMock.mock.calls.map(([url]) => url);
        expect(urls.some((u) => u.includes('/auth/refresh'))).toBe(false);
        expect(urls.some((u) => u.includes('/clientes'))).toBe(true);
    });

    it('llama a refresh si el token tiene mas de 90 minutos', async () => {
        window.localStorage.setItem('erp_token', 'tok-viejo');
        const haceUnRato = new Date(Date.now() - 100 * 60 * 1000).toISOString();
        markTokenIssued(haceUnRato);

        const { api } = await importarApiFresca();

        const fetchMock = vi.spyOn(global, 'fetch').mockImplementation((url) => {
            if (url.includes('/auth/refresh')) {
                return Promise.resolve(
                    new Response(
                        JSON.stringify({
                            success: true,
                            token: 'tok-nuevo-recien-refrescado',
                            issued_at: new Date().toISOString(),
                        }),
                        { status: 200, headers: { 'Content-Type': 'application/json' } }
                    )
                );
            }
            return Promise.resolve(
                new Response(JSON.stringify({ success: true, data: [] }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                })
            );
        });

        await api.get('/clientes');

        const urls = fetchMock.mock.calls.map(([url]) => url);
        expect(urls.some((u) => u.includes('/auth/refresh'))).toBe(true);
        expect(urls.some((u) => u.includes('/clientes'))).toBe(true);
        expect(window.localStorage.getItem('erp_token')).toBe('tok-nuevo-recien-refrescado');
    });

    it('NO llama a refresh para el endpoint /auth/* (evita recursion)', async () => {
        window.localStorage.setItem('erp_token', 'tok-viejo');
        markTokenIssued(new Date(Date.now() - 100 * 60 * 1000).toISOString());

        const { api } = await importarApiFresca();

        const fetchMock = vi.spyOn(global, 'fetch').mockImplementation(() =>
            Promise.resolve(
                new Response(JSON.stringify({ success: true, token: 'tok-refresco-falso' }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                })
            )
        );

        await api.post('/auth/login', { email: 'x', password: 'y' });
        const urls = fetchMock.mock.calls.map(([url]) => url);
        const refreshCalls = urls.filter((u) => u.includes('/auth/refresh'));
        expect(refreshCalls.length).toBe(0);
    });

    it('coalescencia: si 5 requests concurrentes necesitan refresh, solo se hace 1 refresh real', async () => {
        window.localStorage.setItem('erp_token', 'tok-viejo');
        markTokenIssued(new Date(Date.now() - 100 * 60 * 1000).toISOString());

        const { api } = await importarApiFresca();

        let llamadasRefresh = 0;
        let resolveRefresh = null;

        const fetchMock = vi.spyOn(global, 'fetch').mockImplementation((url) => {
            if (url.includes('/auth/refresh')) {
                llamadasRefresh++;
                return new Promise((resolve) => {
                    resolveRefresh = () =>
                        resolve(
                            new Response(
                                JSON.stringify({
                                    success: true,
                                    token: 'tok-renovado',
                                    issued_at: new Date().toISOString(),
                                }),
                                { status: 200, headers: { 'Content-Type': 'application/json' } }
                            )
                        );
                });
            }
            return Promise.resolve(
                new Response(JSON.stringify({ success: true, data: [] }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                })
            );
        });

        const promesas = [
            api.get('/endpoint1'),
            api.get('/endpoint2'),
            api.get('/endpoint3'),
            api.get('/endpoint4'),
            api.get('/endpoint5'),
        ];

        await new Promise((r) => setTimeout(r, 10));
        resolveRefresh();
        await Promise.all(promesas);
        expect(llamadasRefresh).toBe(1);
        const otrosUrls = fetchMock.mock.calls
            .map(([url]) => url)
            .filter((u) => !u.includes('/auth/refresh'));
        expect(otrosUrls.length).toBe(5);
    });

    it('si el refresh falla con error de red, ejecuta handle401 (redirige a login)', async () => {
        window.localStorage.setItem('erp_token', 'tok-viejo');
        markTokenIssued(new Date(Date.now() - 100 * 60 * 1000).toISOString());
        const originalLocation = window.location;
        delete window.location;
        window.location = { ...originalLocation, href: '/', pathname: '/' };
        const locationSetter = vi.fn();
        Object.defineProperty(window.location, 'href', {
            set: locationSetter,
            get: () => '/',
        });

        const { api } = await importarApiFresca();

        vi.spyOn(global, 'fetch').mockImplementation((url) => {
            if (url.includes('/auth/refresh')) {
                return Promise.reject(new Error('Network error'));
            }
            return Promise.resolve(
                new Response(JSON.stringify({}), { status: 200 })
            );
        });

        try {
            await api.get('/clientes');
        } catch {
        }

        expect(window.localStorage.getItem('erp_token')).toBeNull();

        window.location = originalLocation;
    });

    it('si el refresh devuelve success=false, hace logout', async () => {
        window.localStorage.setItem('erp_token', 'tok-viejo');
        markTokenIssued(new Date(Date.now() - 100 * 60 * 1000).toISOString());

        const originalLocation = window.location;
        delete window.location;
        window.location = { ...originalLocation, href: '/', pathname: '/' };

        const { api } = await importarApiFresca();

        vi.spyOn(global, 'fetch').mockImplementation((url) => {
            if (url.includes('/auth/refresh')) {
                return Promise.resolve(
                    new Response(
                        JSON.stringify({ success: false, message: 'Token invalido' }),
                        { status: 401 }
                    )
                );
            }
            return Promise.resolve(
                new Response(JSON.stringify({}), { status: 200 })
            );
        });

        try {
            await api.get('/clientes');
        } catch {
        }

        expect(window.localStorage.getItem('erp_token')).toBeNull();
        expect(window.localStorage.getItem('erp_token_issued_at')).toBeNull();

        window.location = originalLocation;
    });
});

describe('refresh - sin token guardado no hace nada', () => {
    it('si no hay token, no llama a refresh aunque pase tiempo', async () => {
        markTokenIssued(new Date(Date.now() - 100 * 60 * 1000).toISOString());

        vi.resetModules();
        const { api } = await import('./api');

        const fetchMock = vi.spyOn(global, 'fetch').mockResolvedValue(
            new Response(JSON.stringify({ success: true, data: [] }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            })
        );

        await api.get('/clientes');

        const urls = fetchMock.mock.calls.map(([url]) => url);
        expect(urls.some((u) => u.includes('/auth/refresh'))).toBe(false);
    });
});

describe('refresh - sessionStorage (login sin "Recordarme")', () => {
    it('markTokenIssued guarda el timestamp en sessionStorage si el token esta ahi', () => {
        window.sessionStorage.setItem('erp_token', 'tok-session');
        markTokenIssued();
        expect(window.sessionStorage.getItem('erp_token_issued_at')).toBeTruthy();
        expect(window.localStorage.getItem('erp_token_issued_at')).toBeNull();
    });

    it('markTokenIssued guarda el timestamp en localStorage si el token esta ahi', () => {
        window.localStorage.setItem('erp_token', 'tok-local');
        markTokenIssued();

        expect(window.localStorage.getItem('erp_token_issued_at')).toBeTruthy();
        expect(window.sessionStorage.getItem('erp_token_issued_at')).toBeNull();
    });

    it('refresh se dispara correctamente cuando token y timestamp estan en sessionStorage', async () => {
        window.sessionStorage.setItem('erp_token', 'tok-viejo-session');
        const haceUnRato = new Date(Date.now() - 100 * 60 * 1000).toISOString();
        markTokenIssued(haceUnRato);
        expect(window.sessionStorage.getItem('erp_token_issued_at')).toBe(haceUnRato);

        vi.resetModules();
        const { api } = await import('./api');

        const fetchMock = vi.spyOn(global, 'fetch').mockImplementation((url) => {
            if (url.includes('/auth/refresh')) {
                return Promise.resolve(
                    new Response(
                        JSON.stringify({
                            success: true,
                            token: 'tok-nuevo-session',
                            issued_at: new Date().toISOString(),
                        }),
                        { status: 200, headers: { 'Content-Type': 'application/json' } }
                    )
                );
            }
            return Promise.resolve(
                new Response(JSON.stringify({ success: true, data: [] }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                })
            );
        });

        await api.get('/clientes');

        const urls = fetchMock.mock.calls.map(([url]) => url);
        expect(urls.some((u) => u.includes('/auth/refresh'))).toBe(true);
        expect(window.sessionStorage.getItem('erp_token')).toBe('tok-nuevo-session');
        expect(window.localStorage.getItem('erp_token')).toBeNull();
    });
});
