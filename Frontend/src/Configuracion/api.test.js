import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { api, API_BASE_URL } from './api.js';

// =====================================================================
// HELPERS DE MOCK
// =====================================================================
const mockResponse = (status, body, contentType = 'application/json') => ({
    ok: status >= 200 && status < 300,
    status,
    statusText: status === 200 ? 'OK' : `Status ${status}`,
    headers: {
        get: (h) => (h.toLowerCase() === 'content-type' ? contentType : null),
    },
    json: async () => body,
    blob: async () => new Blob([JSON.stringify(body)], { type: 'application/pdf' }),
});

const setupFetchSequence = (responses) => {
    const mock = vi.fn();
    responses.forEach((r) => {
        if (r === 'NETWORK_ERROR') {
            mock.mockImplementationOnce(() => Promise.reject(new TypeError('fetch failed')));
        } else if (r === 'ABORT') {
            mock.mockImplementationOnce(() => Promise.reject(new DOMException('aborted', 'AbortError')));
        } else {
            mock.mockImplementationOnce(() => Promise.resolve(r));
        }
    });
    global.fetch = mock;
    return mock;
};

// =====================================================================
// SETUP / TEARDOWN
// =====================================================================

beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    api.config({ showErrorToast: false, timeoutMs: 5000 });
});

afterEach(() => {
    vi.restoreAllMocks();
});

// =====================================================================
// HELPERS INTERNOS
// =====================================================================

describe('buildQuery', () => {
    const { buildQuery } = api._internal;

    it('devuelve string vacio para params vacios', () => {
        expect(buildQuery({})).toBe('');
        expect(buildQuery()).toBe('');
        expect(buildQuery(null)).toBe('');
    });

    it('construye query simple', () => {
        expect(buildQuery({ a: 1, b: 'hola' })).toBe('?a=1&b=hola');
    });

    it('ignora valores null/undefined/string vacio', () => {
        expect(buildQuery({ a: 1, b: null, c: undefined, d: '' })).toBe('?a=1');
    });

    it('mantiene valores 0 y false (no son null)', () => {
        expect(buildQuery({ a: 0, b: false })).toBe('?a=0&b=false');
    });

    it('escapa caracteres especiales', () => {
        expect(buildQuery({ q: 'a&b=c' })).toBe('?q=a%26b%3Dc');
    });

    it('serializa arrays como key[]=v', () => {
        const qs = buildQuery({ ids: [1, 2, 3] });
        expect(qs).toMatch(/ids(\[\]|%5B%5D)=1.*ids(\[\]|%5B%5D)=2.*ids(\[\]|%5B%5D)=3/);
    });

    it('serializa objetos anidados como JSON', () => {
        expect(buildQuery({ filter: { type: 'A' } })).toContain('filter=');
    });
});

describe('inferErrorCode', () => {
    const { inferErrorCode } = api._internal;

    it('usa error_code del payload si existe', () => {
        expect(inferErrorCode(500, { error_code: 'CUSTOM_X' })).toBe('CUSTOM_X');
    });

    it('mapea status conocidos', () => {
        expect(inferErrorCode(0, null)).toBe('ERROR_RED');
        expect(inferErrorCode(401, null)).toBe('NO_AUTORIZADO');
        expect(inferErrorCode(403, null)).toBe('PROHIBIDO');
        expect(inferErrorCode(404, null)).toBe('NO_ENCONTRADO');
        expect(inferErrorCode(422, null)).toBe('VALIDACION');
        expect(inferErrorCode(408, null)).toBe('TIMEOUT');
        expect(inferErrorCode(500, null)).toBe('ERROR_SERVIDOR');
        expect(inferErrorCode(502, null)).toBe('ERROR_SERVIDOR');
    });

    it('devuelve ERROR_DESCONOCIDO para otros', () => {
        expect(inferErrorCode(418, null)).toBe('ERROR_DESCONOCIDO');
    });
});

describe('formatValidationErrors', () => {
    const { formatValidationErrors } = api._internal;

    it('devuelve null para input vacio', () => {
        expect(formatValidationErrors(null)).toBeNull();
        expect(formatValidationErrors({})).toBeNull();
    });

    it('formatea errors estilo Laravel', () => {
        const out = formatValidationErrors({
            rut: ['Rut invalido'],
            email: ['Email ya existe', 'Formato incorrecto'],
        });
        expect(out).toContain('Rut invalido');
        expect(out).toContain('Email ya existe');
        expect(out).toContain('Formato incorrecto');
    });

    it('acepta strings sueltos', () => {
        expect(formatValidationErrors({ a: 'msg directa' })).toBe('msg directa');
    });
});

describe('buildError', () => {
    const { buildError } = api._internal;

    it('arma error normalizado con todos los campos', () => {
        const err = buildError(404, { message: 'No existe' }, 'Not Found');
        expect(err.status).toBe(404);
        expect(err.code).toBe('NO_ENCONTRADO');
        expect(err.message).toBe('No existe');
        expect(err.success).toBe(false);
        expect(err.errors).toBeNull();
    });

    it('expone response.data para compat axios-style', () => {
        const err = buildError(500, { message: 'Boom' });
        expect(err.response).toBeDefined();
        expect(err.response.status).toBe(500);
        expect(err.response.data.message).toBe('Boom');
    });

    it('para 422 extrae errors y los junta como mensaje legible', () => {
        const err = buildError(422, {
            message: 'The given data was invalid',
            errors: { rut: ['Rut requerido'], email: ['Email invalido'] },
        });
        expect(err.code).toBe('VALIDACION');
        expect(err.errors).toEqual({ rut: ['Rut requerido'], email: ['Email invalido'] });
        expect(err.message).toContain('Rut requerido');
        expect(err.message).toContain('Email invalido');
    });

    it('cae a mensaje por defecto si no hay payload', () => {
        const err = buildError(404, null);
        expect(err.message).toContain('no existe');
    });
});

describe('token management', () => {
    const { getToken, clearAuth, getAuthHeaders } = api._internal;

    it('lee erp_token de localStorage', () => {
        localStorage.setItem('erp_token', 'abc123');
        expect(getToken()).toBe('abc123');
    });

    it('lee erp_token de sessionStorage cuando no esta en localStorage', () => {
        sessionStorage.setItem('erp_token', 'session-token');
        expect(getToken()).toBe('session-token');
    });

    it('cae a token (legacy) si no hay erp_token', () => {
        localStorage.setItem('token', 'legacy-token');
        expect(getToken()).toBe('legacy-token');
    });

    it('da prioridad a erp_token sobre token', () => {
        localStorage.setItem('erp_token', 'new');
        localStorage.setItem('token', 'old');
        expect(getToken()).toBe('new');
    });

    it('quita comillas envolventes (token con JSON.stringify)', () => {
        localStorage.setItem('erp_token', '"con-comillas"');
        expect(getToken()).toBe('con-comillas');
    });

    it('devuelve null si no hay token', () => {
        expect(getToken()).toBeNull();
    });

    it('getAuthHeaders devuelve {} sin token', () => {
        expect(getAuthHeaders()).toEqual({});
    });

    it('getAuthHeaders devuelve Authorization Bearer X con token', () => {
        localStorage.setItem('erp_token', 'xyz');
        expect(getAuthHeaders()).toEqual({ Authorization: 'Bearer xyz' });
    });

    it('clearAuth limpia todos los keys de auth', () => {
        localStorage.setItem('erp_token', 'a');
        localStorage.setItem('erp_user', 'b');
        localStorage.setItem('token', 'c');
        sessionStorage.setItem('erp_token', 'd');
        clearAuth();
        expect(localStorage.getItem('erp_token')).toBeNull();
        expect(localStorage.getItem('erp_user')).toBeNull();
        expect(localStorage.getItem('token')).toBeNull();
        expect(sessionStorage.getItem('erp_token')).toBeNull();
    });
});

// =====================================================================
// REQUESTS HAPPY PATH
// =====================================================================

describe('api.get', () => {
    it('hace GET a la URL correcta y devuelve data', async () => {
        const mock = setupFetchSequence([
            mockResponse(200, { success: true, data: [{ id: 1 }] }),
        ]);
        const result = await api.get('/activos');
        expect(mock).toHaveBeenCalledWith(`${API_BASE_URL}/activos`, expect.objectContaining({
            method: 'GET',
        }));
        expect(result).toEqual({ success: true, data: [{ id: 1 }] });
    });

    it('incluye Authorization header cuando hay token', async () => {
        localStorage.setItem('erp_token', 'mi-token');
        const mock = setupFetchSequence([mockResponse(200, {})]);
        await api.get('/activos');
        const init = mock.mock.calls[0][1];
        expect(init.headers.Authorization).toBe('Bearer mi-token');
    });

    it('agrega query params con options.params', async () => {
        const mock = setupFetchSequence([mockResponse(200, {})]);
        await api.get('/activos', { params: { search: 'Notebook', per_page: 20 } });
        const url = mock.mock.calls[0][0];
        expect(url).toContain('?search=Notebook');
        expect(url).toContain('per_page=20');
    });

    it('respeta la firma legacy sin options (api.get("/x?a=1"))', async () => {
        const mock = setupFetchSequence([mockResponse(200, {})]);
        await api.get('/activos?a=1');
        expect(mock.mock.calls[0][0]).toBe(`${API_BASE_URL}/activos?a=1`);
    });
});

describe('api.post', () => {
    it('manda body como JSON y devuelve data', async () => {
        const mock = setupFetchSequence([
            mockResponse(201, { success: true, data: { id: 1 } }),
        ]);
        const result = await api.post('/facturas', { numero: 'F-1' });
        const init = mock.mock.calls[0][1];
        expect(init.method).toBe('POST');
        expect(init.headers['Content-Type']).toBe('application/json');
        expect(JSON.parse(init.body)).toEqual({ numero: 'F-1' });
        expect(result).toEqual({ success: true, data: { id: 1 } });
    });
});

describe('api.put / api.patch', () => {
    it('PUT usa method PUT', async () => {
        const mock = setupFetchSequence([mockResponse(200, {})]);
        await api.put('/activos/5', { nombre: 'X' });
        expect(mock.mock.calls[0][1].method).toBe('PUT');
    });

    it('PATCH usa method PATCH (nuevo)', async () => {
        const mock = setupFetchSequence([mockResponse(200, {})]);
        await api.patch('/cotizaciones/5/estado', { estado_id: 2 });
        const init = mock.mock.calls[0][1];
        expect(init.method).toBe('PATCH');
        expect(JSON.parse(init.body)).toEqual({ estado_id: 2 });
    });
});

describe('api.delete', () => {
    it('sin body hace DELETE plano', async () => {
        const mock = setupFetchSequence([mockResponse(200, {})]);
        await api.delete('/activos/proyectos/3');
        const init = mock.mock.calls[0][1];
        expect(init.method).toBe('DELETE');
        expect(init.body).toBeUndefined();
    });

    it('con body manda el body', async () => {
        const mock = setupFetchSequence([mockResponse(200, {})]);
        await api.delete('/x/1', { motivo: 'duplicado' });
        const init = mock.mock.calls[0][1];
        expect(JSON.parse(init.body)).toEqual({ motivo: 'duplicado' });
    });

    it('con options ({ silent }) no manda body', async () => {
        const mock = setupFetchSequence([mockResponse(200, {})]);
        await api.delete('/x/1', { silent: true });
        const init = mock.mock.calls[0][1];
        expect(init.body).toBeUndefined();
    });

    it('con body + options manda body y respeta silent', async () => {
        setupFetchSequence([
            mockResponse(400, { message: 'Bad' }),
        ]);
        await expect(
            api.delete('/x/1', { motivo: 'x' }, { silent: true })
        ).rejects.toMatchObject({ status: 400 });
        // No verificamos toast porque ya esta silenciado globalmente en tests
    });
});

// =====================================================================
// MANEJO DE ERRORES
// =====================================================================

describe('errores 4xx', () => {
    it('404 rechaza con error normalizado', async () => {
        setupFetchSequence([
            mockResponse(404, { message: 'Activo no existe' }),
        ]);
        await expect(api.get('/activos/999')).rejects.toMatchObject({
            status: 404,
            code: 'NO_ENCONTRADO',
            message: 'Activo no existe',
        });
    });

    it('422 extrae errors y los muestra legibles', async () => {
        setupFetchSequence([
            mockResponse(422, {
                message: 'The given data was invalid.',
                errors: { rut: ['Rut requerido'], email: ['Email invalido'] },
            }),
        ]);
        await expect(api.post('/clientes', {})).rejects.toMatchObject({
            status: 422,
            code: 'VALIDACION',
            errors: { rut: ['Rut requerido'], email: ['Email invalido'] },
        });
    });

    it('422 sin errors usa message del payload', async () => {
        setupFetchSequence([
            mockResponse(422, { message: 'Validacion personalizada' }),
        ]);
        const err = await api.post('/x', {}).catch((e) => e);
        expect(err.message).toBe('Validacion personalizada');
    });

    it('expone error.response.data.message para compat axios-style', async () => {
        setupFetchSequence([
            mockResponse(400, { message: 'Error legacy' }),
        ]);
        const err = await api.post('/x', {}).catch((e) => e);
        // Codigo viejo lee error.response.data.message
        expect(err.response.data.message).toBe('Error legacy');
    });

    it('401 limpia el token y redirige a /login', async () => {
        // Mock de window.location
        const originalLocation = window.location;
        delete window.location;
        window.location = { ...originalLocation, pathname: '/dashboard', href: '' };

        localStorage.setItem('erp_token', 'expirado');
        setupFetchSequence([
            mockResponse(401, { message: 'Token expired' }),
        ]);

        await expect(api.get('/perfil')).rejects.toMatchObject({ status: 401 });

        expect(localStorage.getItem('erp_token')).toBeNull();
        expect(window.location.href).toBe('/login');

        window.location = originalLocation;
    });

    it('401 desde /login NO redirige (evita loop)', async () => {
        const originalLocation = window.location;
        delete window.location;
        window.location = { ...originalLocation, pathname: '/login', href: '' };

        setupFetchSequence([mockResponse(401, { message: 'Wrong creds' })]);
        await expect(api.post('/auth/login', { email: 'x' })).rejects.toMatchObject({
            status: 401,
        });
        // href NO se cambio
        expect(window.location.href).toBe('');

        window.location = originalLocation;
    });

    it('403 no redirige', async () => {
        const originalLocation = window.location;
        delete window.location;
        window.location = { ...originalLocation, pathname: '/dashboard', href: '' };

        setupFetchSequence([mockResponse(403, { message: 'Sin permisos' })]);
        await expect(api.get('/admin')).rejects.toMatchObject({ status: 403 });
        expect(window.location.href).toBe('');

        window.location = originalLocation;
    });
});

describe('errores 5xx con retry', () => {
    it('503 reintenta automaticamente y eventualmente exito', async () => {
        const mock = setupFetchSequence([
            mockResponse(503, { message: 'Service unavailable' }),
            mockResponse(200, { success: true, data: 'OK' }),
        ]);
        const result = await api.get('/x');
        expect(mock).toHaveBeenCalledTimes(2);
        expect(result.data).toBe('OK');
    });

    it('503 que persiste rechaza despues de 3 intentos (1 + 2 retries)', async () => {
        const mock = setupFetchSequence([
            mockResponse(503, { message: 'down' }),
            mockResponse(503, { message: 'down' }),
            mockResponse(503, { message: 'down' }),
        ]);
        await expect(api.get('/x')).rejects.toMatchObject({ status: 503 });
        expect(mock).toHaveBeenCalledTimes(3);
    });

    it('500 NO reintenta (no es transitorio)', async () => {
        const mock = setupFetchSequence([
            mockResponse(500, { message: 'Boom' }),
        ]);
        await expect(api.get('/x')).rejects.toMatchObject({ status: 500 });
        expect(mock).toHaveBeenCalledTimes(1);
    });

    it('400 NO reintenta', async () => {
        const mock = setupFetchSequence([
            mockResponse(400, { message: 'Bad' }),
        ]);
        await expect(api.get('/x')).rejects.toMatchObject({ status: 400 });
        expect(mock).toHaveBeenCalledTimes(1);
    });
});

describe('errores de red', () => {
    it('error de red reintenta y eventualmente exito', async () => {
        const mock = setupFetchSequence([
            'NETWORK_ERROR',
            mockResponse(200, { ok: true }),
        ]);
        const result = await api.get('/x');
        expect(mock).toHaveBeenCalledTimes(2);
        expect(result.ok).toBe(true);
    });

    it('error de red persistente rechaza con status 0', async () => {
        setupFetchSequence([
            'NETWORK_ERROR',
            'NETWORK_ERROR',
            'NETWORK_ERROR',
        ]);
        await expect(api.get('/x')).rejects.toMatchObject({
            status: 0,
            code: 'ERROR_RED',
        });
    });
});

describe('respuesta no-JSON', () => {
    it('200 sin JSON devuelve null sin romper', async () => {
        setupFetchSequence([mockResponse(200, null, 'text/plain')]);
        const result = await api.get('/x');
        expect(result).toBeNull();
    });

    it('error con HTML (no JSON) usa defaults', async () => {
        setupFetchSequence([mockResponse(500, '<html>error</html>', 'text/html')]);
        const err = await api.get('/x').catch((e) => e);
        expect(err.status).toBe(500);
        expect(err.message).toContain('servidor');
    });
});

// =====================================================================
// FORMDATA UPLOAD
// =====================================================================

describe('api.upload (FormData)', () => {
    it('NO setea Content-Type (browser pone boundary)', async () => {
        const mock = setupFetchSequence([mockResponse(200, { ok: true })]);
        const fd = new FormData();
        fd.append('logo', new Blob(['x'], { type: 'image/png' }), 'logo.png');
        await api.upload('/empresas/perfil', fd);
        const init = mock.mock.calls[0][1];
        expect(init.headers['Content-Type']).toBeUndefined();
        // Y debe haber pasado el FormData como body
        expect(init.body).toBe(fd);
    });

    it('rechaza si no es FormData', async () => {
        await expect(api.upload('/x', { plain: 'obj' })).rejects.toMatchObject({
            message: expect.stringContaining('FormData'),
        });
    });

    it('soporta method PUT (override para emular PUT con archivos)', async () => {
        const mock = setupFetchSequence([mockResponse(200, {})]);
        const fd = new FormData();
        fd.append('a', 'b');
        await api.upload('/x', fd, { method: 'PUT' });
        expect(mock.mock.calls[0][1].method).toBe('PUT');
    });
});

// =====================================================================
// CONFIG
// =====================================================================

describe('api.config', () => {
    it('puede deshabilitar el toast', () => {
        const cfg = api.config({ showErrorToast: false });
        expect(cfg.showErrorToast).toBe(false);
    });

    it('puede cambiar el timeout', () => {
        const cfg = api.config({ timeoutMs: 60000 });
        expect(cfg.timeoutMs).toBe(60000);
        api.config({ timeoutMs: 5000 }); // restaurar
    });

    it('ignora keys no validas', () => {
        const before = api.config({});
        api.config({ noExisteEstaKey: 'x' });
        const after = api.config({});
        expect(after).toEqual(before);
    });
});

// =====================================================================
// AUTH
// =====================================================================

describe('api.auth', () => {
    it('login hace POST a /auth/login', async () => {
        const mock = setupFetchSequence([
            mockResponse(200, { token: 't', user: { id: 1 } }),
        ]);
        const result = await api.auth.login({ email: 'a@b.cl', password: 'x' });
        expect(mock.mock.calls[0][0]).toContain('/auth/login');
        expect(result.token).toBe('t');
    });

    it('logout limpia tokens', () => {
        const originalLocation = window.location;
        delete window.location;
        window.location = { ...originalLocation, href: '' };

        localStorage.setItem('erp_token', 'x');
        sessionStorage.setItem('erp_token', 'y');
        api.auth.logout();
        expect(localStorage.getItem('erp_token')).toBeNull();
        expect(sessionStorage.getItem('erp_token')).toBeNull();
        expect(window.location.href).toBe('/login');

        window.location = originalLocation;
    });
});

// =====================================================================
// EXPORTS
// =====================================================================

describe('exports', () => {
    it('exporta API_BASE_URL', () => {
        expect(API_BASE_URL).toMatch(/^\/api|^https?:\/\//);
    });

    it('expone api.defaults.baseURL para compat', () => {
        expect(api.defaults.baseURL).toBe(API_BASE_URL);
    });

    it('expone todos los metodos esperados', () => {
        expect(typeof api.get).toBe('function');
        expect(typeof api.post).toBe('function');
        expect(typeof api.put).toBe('function');
        expect(typeof api.patch).toBe('function');
        expect(typeof api.delete).toBe('function');
        expect(typeof api.upload).toBe('function');
        expect(typeof api.download).toBe('function');
        expect(typeof api.config).toBe('function');
        expect(typeof api.auth.login).toBe('function');
        expect(typeof api.auth.register).toBe('function');
        expect(typeof api.auth.logout).toBe('function');
    });
});
