/**
 * Cliente API ERP Contable - Version 2.0
 *
 * Diseno:
 * - Compatibilidad backwards 100% con codigo existente que usa api.get/post/put/delete
 * - Nuevos metodos: api.patch, api.upload (FormData), api.download (blob/PDF)
 * - Manejo uniforme de errores en formato { status, code, message, errors, raw }
 * - Para compat con codigo legacy axios-style, error tambien expone .response.data.message
 * - Retry automatico solo para errores transitorios (red, 502, 503, 504)
 * - Timeout configurable (default 30s)
 * - Toast automatico opt-out con { silent: true } por request
 * - Lee token de erp_token o token (legacy) en localStorage o sessionStorage
 *
 * Forma de uso:
 *
 *   import { api } from '@/Configuracion/api';
 *
 *   // Casos basicos
 *   const data = await api.get('/activos');
 *   const data = await api.post('/facturas', payload);
 *   const data = await api.patch('/cotizaciones/5/estado', { estado_id: 2 });
 *   const data = await api.delete('/activos/proyectos/3');
 *
 *   // Con query params
 *   const data = await api.get('/activos', { params: { search: 'Notebook', per_page: 20 } });
 *
 *   // Upload (FormData) - no fuerza Content-Type para que el browser ponga el boundary
 *   const fd = new FormData();
 *   fd.append('logo', file);
 *   const data = await api.upload('/empresas/perfil', fd);
 *
 *   // Descargar archivo (PDF, Excel, etc)
 *   await api.download('/cotizaciones/pdf/5', 'cotizacion-5.pdf');
 *
 *   // Silenciar el toast automatico (cuando uno lo maneja a mano)
 *   try { await api.post('/x', body, { silent: true }); }
 *   catch (err) { console.log(err.message); }
 *
 *   // Manejo de errores - dos estilos soportados:
 *   try { await api.post('/x', body); }
 *   catch (err) {
 *      err.status         // 422
 *      err.code           // 'VALIDATION_ERROR'
 *      err.message        // mensaje legible
 *      err.errors         // { campo: ['error 1'] } cuando 422
 *      err.response.data  // payload bruto (compat axios-style)
 *   }
 */

import Swal from 'sweetalert2';

// =====================================================================
// CONFIGURACION
// =====================================================================

const hostname = typeof window !== 'undefined' ? window.location.hostname : 'localhost';
const isLocal = hostname === 'localhost' || hostname === '127.0.0.1';

export const API_BASE_URL =
    (typeof import.meta !== 'undefined' && import.meta.env?.VITE_API_URL) ||
    (isLocal ? 'http://127.0.0.1:8000/api' : 'https://erp.tenri.cl/api');

const DEFAULT_TIMEOUT_MS = 30000;
const RETRY_STATUSES = new Set([0, 502, 503, 504]); // 0 = red caida
const MAX_RETRIES = 2;
const RETRY_DELAY_MS = 800;

// Estado global del cliente (overridable via api.config())
const globalConfig = {
    showErrorToast: true,
    timeoutMs: DEFAULT_TIMEOUT_MS,
};

// =====================================================================
// AUTH / TOKEN
// =====================================================================

/**
 * Lee el token de auth. Soporta:
 * - localStorage.erp_token (estandar)
 * - sessionStorage.erp_token
 * - localStorage.token (legacy)
 * - sessionStorage.token (legacy)
 * - Tokens con comillas envolventes (JSON.stringify en algun momento)
 */
const getToken = () => {
    if (typeof window === 'undefined') return null;
    let token =
        window.localStorage.getItem('erp_token') ||
        window.sessionStorage.getItem('erp_token') ||
        window.localStorage.getItem('token') ||
        window.sessionStorage.getItem('token');

    if (!token) return null;

    // Tokens guardados con JSON.stringify quedan envueltos en comillas
    if (typeof token === 'string' && token.startsWith('"') && token.endsWith('"')) {
        token = token.slice(1, -1);
    }
    return token;
};

const getAuthHeaders = () => {
    const token = getToken();
    return token ? { Authorization: `Bearer ${token}` } : {};
};

const clearAuth = () => {
    if (typeof window === 'undefined') return;
    ['erp_token', 'erp_user', 'token'].forEach((k) => {
        window.localStorage.removeItem(k);
        window.sessionStorage.removeItem(k);
    });
};

// =====================================================================
// QUERY STRING BUILDER
// =====================================================================

/**
 * Convierte un objeto a query string. Ignora null/undefined/''.
 * Maneja arrays como ?key[]=v1&key[]=v2 (estandar Laravel).
 */
const buildQuery = (params = {}) => {
    if (!params || typeof params !== 'object') return '';

    const usp = new URLSearchParams();
    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') return;
        if (Array.isArray(value)) {
            value.forEach((v) => usp.append(`${key}[]`, v));
        } else if (typeof value === 'object') {
            usp.append(key, JSON.stringify(value));
        } else {
            usp.append(key, value);
        }
    });
    const query = usp.toString();
    return query ? `?${query}` : '';
};

// =====================================================================
// CONSTRUCCION DEL ERROR NORMALIZADO
// =====================================================================

/**
 * Diccionario de codigos de error legibles, segun status HTTP y payload.
 */
const inferErrorCode = (status, payload) => {
    if (payload?.error_code) return payload.error_code;
    if (status === 0) return 'ERROR_RED';
    if (status === 401) return 'NO_AUTORIZADO';
    if (status === 403) return 'PROHIBIDO';
    if (status === 404) return 'NO_ENCONTRADO';
    if (status === 422) return 'VALIDACION';
    if (status === 408) return 'TIMEOUT';
    if (status >= 500) return 'ERROR_SERVIDOR';
    return 'ERROR_DESCONOCIDO';
};

/**
 * Mensajes legibles por defecto cuando el backend no manda nada utilizable.
 */
const defaultMessage = (status, code) => {
    if (code === 'TIMEOUT') return 'La operacion tardo demasiado. Intenta nuevamente.';
    if (code === 'ERROR_RED') return 'Sin conexion con el servidor. Revisa tu internet.';
    if (status === 401) return 'Tu sesion expiro. Inicia sesion nuevamente.';
    if (status === 403) return 'No tienes permisos para realizar esta accion.';
    if (status === 404) return 'El recurso solicitado no existe.';
    if (status === 422) return 'Los datos enviados no son validos.';
    if (status >= 500) return 'Error en el servidor. Intenta nuevamente en unos segundos.';
    return 'Ocurrio un error inesperado.';
};

/**
 * Si Laravel devuelve errors: { campo: ['mensaje'] }, formatea legible.
 * Ejemplo: { rut: ['Rut invalido'], email: ['Ya existe'] }
 *   -> "Rut invalido. Ya existe."
 */
const formatValidationErrors = (errors) => {
    if (!errors || typeof errors !== 'object') return null;
    const messages = [];
    Object.values(errors).forEach((arr) => {
        if (Array.isArray(arr)) {
            arr.forEach((m) => m && messages.push(m));
        } else if (typeof arr === 'string') {
            messages.push(arr);
        }
    });
    return messages.length ? messages.join(' ') : null;
};

/**
 * Devuelve true si el statusText corresponde a un statusText HTTP estandar
 * (que ya queremos suplantar por mensaje legible), y false si parece un
 * mensaje custom (ej: 'Timeout', 'upload() requiere FormData', 'Network error').
 */
const GENERIC_HTTP_STATUS_TEXTS = new Set([
    'OK',
    'Created',
    'Accepted',
    'No Content',
    'Bad Request',
    'Unauthorized',
    'Forbidden',
    'Not Found',
    'Method Not Allowed',
    'Not Acceptable',
    'Request Timeout',
    'Conflict',
    'Gone',
    'Unprocessable Entity',
    'Unprocessable Content',
    'Too Many Requests',
    'Internal Server Error',
    'Bad Gateway',
    'Service Unavailable',
    'Gateway Timeout',
    '',
]);
const isGenericHttpStatusText = (text) => {
    if (!text || GENERIC_HTTP_STATUS_TEXTS.has(text)) return true;
    // El mockResponse en tests genera 'Status XXX', es generico tambien
    if (/^Status \d+$/.test(text)) return true;
    return false;
};

/**
 * Construye el objeto de error estandar a partir de la respuesta del servidor.
 * Incluye .response.data para compat con codigo legacy que usa axios style.
 */
const buildError = (status, payload, statusText = '') => {
    const code = inferErrorCode(status, payload);
    const errors = payload?.errors || null;

    // Para 422, preferir el detalle de errores sobre el message generico
    let message;
    if (status === 422 && errors) {
        message = formatValidationErrors(errors) || payload?.message || defaultMessage(status, code);
    } else if (payload?.message) {
        // Backend mando un mensaje explicito, usalo
        message = payload.message;
    } else if (statusText && !isGenericHttpStatusText(statusText)) {
        // statusText custom (ej: 'upload() requiere FormData', 'Timeout',
        // 'Network error en descarga'). NO los statusText HTTP estandar.
        message = statusText;
    } else {
        // Status HTTP estandar sin payload: usar nuestros mensajes legibles
        message = defaultMessage(status, code);
    }

    return {
        status,
        success: false,
        code,
        message,
        errors,
        raw: payload,
        // Compat axios-style (codigo viejo lee error.response.data.message)
        response: {
            status,
            data: payload || { message },
        },
    };
};

// =====================================================================
// TOAST GLOBAL (Swal)
// =====================================================================

const titleForError = (error) => {
    switch (error.code) {
        case 'ERROR_RED': return 'Sin conexion';
        case 'TIMEOUT': return 'Tiempo de espera agotado';
        case 'VALIDACION': return 'Datos invalidos';
        case 'NO_ENCONTRADO': return 'No encontrado';
        case 'PROHIBIDO': return 'Sin permisos';
        case 'ERROR_SERVIDOR': return 'Error del servidor';
        default: return 'Error';
    }
};

const showErrorToast = (error) => {
    if (!globalConfig.showErrorToast) return;
    if (typeof window === 'undefined') return; // SSR safety

    // 401 -> no mostramos toast porque ya redirigimos a login
    if (error.status === 401) return;

    Swal.fire({
        icon: 'error',
        title: titleForError(error),
        text: error.message,
        confirmButtonColor: '#0f172a',
        confirmButtonText: 'Entendido',
    });
};

// =====================================================================
// CORE: REQUEST CON RETRY + TIMEOUT
// =====================================================================

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Crea AbortController combinando un timeout y una signal opcional del usuario.
 */
const buildController = (timeoutMs, externalSignal) => {
    const controller = new AbortController();
    let timedOut = false;
    const timeoutId = setTimeout(() => {
        timedOut = true;
        controller.abort();
    }, timeoutMs);

    if (externalSignal) {
        if (externalSignal.aborted) {
            controller.abort();
        } else {
            externalSignal.addEventListener('abort', () => controller.abort());
        }
    }

    return {
        signal: controller.signal,
        cleanup: () => clearTimeout(timeoutId),
        wasTimeout: () => timedOut,
    };
};

/**
 * Ejecuta una request a fetch con timeout, manejo de errores.
 */
const doFetch = async (url, init, options) => {
    const timeoutMs = options.timeoutMs ?? globalConfig.timeoutMs;
    const ctrl = buildController(timeoutMs, options.signal);

    try {
        const response = await fetch(url, { ...init, signal: ctrl.signal });
        return { response, timedOut: false };
    } catch {
        if (ctrl.wasTimeout()) {
            return { response: null, timedOut: true };
        }
        return { response: null, timedOut: false, networkError: true };
    } finally {
        ctrl.cleanup();
    }
};

/**
 * Parsea el body de una respuesta tolerando JSON malformado o vacio.
 */
const parseBody = async (response) => {
    if (!response) return null;
    const contentType = response.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) return null;
    try {
        return await response.json();
    } catch {
        return null;
    }
};

const handle401 = () => {
    if (typeof window === 'undefined') return;
    clearAuth();
    if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login';
    }
};

/**
 * Request principal con manejo completo (retry, timeout, errores normalizados, toast).
 * @returns Promise<data> en exito, rechaza con Error normalizado.
 */
const request = async (endpoint, method, body, options = {}) => {
    const url = `${API_BASE_URL}${endpoint}`;
    const isFormData = body instanceof FormData;
    const silent = options.silent === true;

    const headers = {
        Accept: 'application/json',
        ...getAuthHeaders(),
        ...(options.headers || {}),
    };

    // Para FormData, NO seteamos Content-Type: el browser lo arma con boundary
    if (body && !isFormData) {
        headers['Content-Type'] = headers['Content-Type'] || 'application/json';
    }

    const init = { method, headers };
    if (body !== null && body !== undefined) {
        init.body = isFormData ? body : JSON.stringify(body);
    }

    let attempt = 0;
    let lastError;

    while (attempt <= MAX_RETRIES) {
        const { response, timedOut, networkError } = await doFetch(url, init, options);

        // Timeout: error 408 sintetico, retry como transitorio
        if (timedOut) {
            lastError = buildError(408, null, 'Timeout');
            if (attempt < MAX_RETRIES) {
                attempt++;
                await sleep(RETRY_DELAY_MS * attempt);
                continue;
            }
            break;
        }

        // Error de red: error 0 sintetico, retry
        if (networkError) {
            lastError = buildError(0, null, 'Network error');
            if (attempt < MAX_RETRIES) {
                attempt++;
                await sleep(RETRY_DELAY_MS * attempt);
                continue;
            }
            break;
        }

        // Hubo respuesta
        if (response.ok) {
            const data = await parseBody(response);
            return data;
        }

        // No ok. Parse y armar error
        const payload = await parseBody(response);

        if (response.status === 401) {
            handle401();
        }

        lastError = buildError(response.status, payload, response.statusText);

        if (RETRY_STATUSES.has(response.status) && attempt < MAX_RETRIES) {
            attempt++;
            await sleep(RETRY_DELAY_MS * attempt);
            continue;
        }
        break;
    }

    if (!silent) showErrorToast(lastError);
    return Promise.reject(lastError);
};

// =====================================================================
// DESCARGAS BINARIAS (BLOB)
// =====================================================================

const downloadBlob = async (endpoint, filename, options = {}) => {
    const url = `${API_BASE_URL}${endpoint}`;
    const silent = options.silent === true;

    const headers = {
        ...getAuthHeaders(),
        ...(options.headers || {}),
    };

    try {
        const response = await fetch(url, {
            method: options.method || 'GET',
            headers,
            body: options.body
                ? (options.body instanceof FormData ? options.body : JSON.stringify(options.body))
                : undefined,
            signal: options.signal,
        });

        if (!response.ok) {
            const payload = await parseBody(response);
            const error = buildError(response.status, payload, response.statusText);
            if (response.status === 401) handle401();
            if (!silent) showErrorToast(error);
            return Promise.reject(error);
        }

        const blob = await response.blob();
        if (typeof window === 'undefined') return blob; // SSR

        const objectUrl = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = objectUrl;
        link.setAttribute('download', filename || 'archivo.bin');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(objectUrl);
        return { success: true, filename };
    } catch {
        const error = buildError(0, null, 'Network error en descarga');
        if (!silent) showErrorToast(error);
        return Promise.reject(error);
    }
};

// =====================================================================
// API PUBLICA
// =====================================================================

export const api = {
    defaults: {
        baseURL: API_BASE_URL,
    },

    /**
     * Configuracion global del cliente.
     * Ejemplo: api.config({ showErrorToast: false, timeoutMs: 60000 })
     */
    config(opts = {}) {
        if (typeof opts.showErrorToast === 'boolean') {
            globalConfig.showErrorToast = opts.showErrorToast;
        }
        if (typeof opts.timeoutMs === 'number') {
            globalConfig.timeoutMs = opts.timeoutMs;
        }
        return { ...globalConfig };
    },

    /**
     * GET con soporte para query params: api.get('/x', { params: { a: 1, b: 'x' } })
     * Tambien soporta la firma legacy api.get('/x?a=1') sin params.
     */
    get(endpoint, options = {}) {
        const qs = options.params ? buildQuery(options.params) : '';
        return request(endpoint + qs, 'GET', null, options);
    },

    post(endpoint, body, options = {}) {
        return request(endpoint, 'POST', body, options);
    },

    put(endpoint, body, options = {}) {
        return request(endpoint, 'PUT', body, options);
    },

    patch(endpoint, body, options = {}) {
        return request(endpoint, 'PATCH', body, options);
    },

    delete(endpoint, bodyOrOptions, maybeOptions) {
        // Compat: api.delete('/x') o api.delete('/x', { motivo: 'x' }) o api.delete('/x', body, { silent: true })
        let body = null;
        let options = {};
        if (bodyOrOptions && typeof bodyOrOptions === 'object') {
            const optionKeys = new Set(['silent', 'signal', 'timeoutMs', 'headers', 'params']);
            const keys = Object.keys(bodyOrOptions);
            const isOptions = keys.length > 0 && keys.every((k) => optionKeys.has(k));
            if (isOptions) {
                options = bodyOrOptions;
            } else {
                body = bodyOrOptions;
                options = maybeOptions || {};
            }
        }
        return request(endpoint, 'DELETE', body, options);
    },

    /**
     * Subir archivos via FormData. NO setea Content-Type para que el browser arme el boundary.
     */
    upload(endpoint, formData, options = {}) {
        if (!(formData instanceof FormData)) {
            return Promise.reject(buildError(0, null, 'upload() requiere FormData'));
        }
        return request(endpoint, options.method || 'POST', formData, options);
    },

    /**
     * Descargar archivo binario y disparar download en el browser.
     */
    download: downloadBlob,

    auth: {
        async login(credentials) {
            return await request('/auth/login', 'POST', credentials);
        },
        register(data) {
            return request('/auth/register', 'POST', data);
        },
        logout() {
            clearAuth();
            if (typeof window !== 'undefined') {
                window.location.href = '/login';
            }
        },
    },

    // ===== Internals exportados para testing =====
    _internal: {
        buildQuery,
        buildError,
        inferErrorCode,
        formatValidationErrors,
        getToken,
        clearAuth,
        getAuthHeaders,
        titleForError,
        defaultMessage,
    },
};
