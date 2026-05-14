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
const RETRY_STATUSES = new Set([0, 502, 503, 504]);
const MAX_RETRIES = 2;
const RETRY_DELAY_MS = 800;

const globalConfig = {
    showErrorToast: true,
    timeoutMs: DEFAULT_TIMEOUT_MS,
};

// =====================================================================
// AUTH / TOKEN
// =====================================================================

const getToken = () => {
    if (typeof window === 'undefined') return null;
    let token =
        window.localStorage.getItem('erp_token') ||
        window.sessionStorage.getItem('erp_token') ||
        window.localStorage.getItem('token') ||
        window.sessionStorage.getItem('token');

    if (!token) return null;

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
    ['erp_token', 'erp_user', 'token', 'erp_token_issued_at'].forEach((k) => {
        window.localStorage.removeItem(k);
        window.sessionStorage.removeItem(k);
    });
};

// =====================================================================
// QUERY STRING BUILDER
// =====================================================================

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
    if (/^Status \d+$/.test(text)) return true;
    return false;
};

const buildError = (status, payload, statusText = '') => {
    const code = inferErrorCode(status, payload);
    const errors = payload?.errors || null;
    let message;
    if (status === 422 && errors) {
        message = formatValidationErrors(errors) || payload?.message || defaultMessage(status, code);
    } else if (payload?.message) {
        message = payload.message;
    } else if (statusText && !isGenericHttpStatusText(statusText)) {
        message = statusText;
    } else {
        message = defaultMessage(status, code);
    }

    return {
        status,
        success: false,
        code,
        message,
        errors,
        raw: payload,
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
    if (typeof window === 'undefined') return;
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

// =====================================================================
// REFRESH TOKEN
// =====================================================================

const REFRESH_THRESHOLD_MS = 90 * 60 * 1000; // 90 minutos
const ISSUED_AT_KEY = 'erp_token_issued_at';

const getTokenStorage = () => {
    if (typeof window === 'undefined') return null;
    if (window.localStorage.getItem('erp_token') || window.localStorage.getItem('token')) {
        return window.localStorage;
    }
    if (window.sessionStorage.getItem('erp_token') || window.sessionStorage.getItem('token')) {
        return window.sessionStorage;
    }
    return null;
};

export const markTokenIssued = (isoString = null) => {
    if (typeof window === 'undefined') return;
    const ts = isoString || new Date().toISOString();
    try {
        const storage = getTokenStorage() || window.localStorage;
        storage.setItem(ISSUED_AT_KEY, ts);
    } catch {
    }
};

const getTokenAgeMs = () => {
    if (typeof window === 'undefined') return null;

    const storage = getTokenStorage();
    if (!storage) return null;

    const ts = storage.getItem(ISSUED_AT_KEY);
    if (!ts) return null;

    const issuedAt = Date.parse(ts);
    if (isNaN(issuedAt)) return null;

    return Date.now() - issuedAt;
};

const tokenNeedsRefresh = () => {
    const age = getTokenAgeMs();
    if (age === null) return false;
    return age >= REFRESH_THRESHOLD_MS;
};

let refreshInFlight = null;
const refreshToken = async () => {
    if (refreshInFlight) {
        return refreshInFlight;
    }

    refreshInFlight = (async () => {
        try {
            const url = `${API_BASE_URL}/auth/refresh`;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    ...getAuthHeaders(),
                },
            });

            if (!res.ok) {
                return false;
            }

            const data = await res.json();
            if (!data.success || !data.token) {
                return false;
            }

            if (typeof window !== 'undefined') {
                if (window.localStorage.getItem('erp_token')) {
                    window.localStorage.setItem('erp_token', data.token);
                } else if (window.sessionStorage.getItem('erp_token')) {
                    window.sessionStorage.setItem('erp_token', data.token);
                } else if (window.localStorage.getItem('token')) {
                    window.localStorage.setItem('token', data.token);
                } else if (window.sessionStorage.getItem('token')) {
                    window.sessionStorage.setItem('token', data.token);
                } else {
                    window.localStorage.setItem('erp_token', data.token);
                }
            }

            markTokenIssued(data.issued_at);

            return true;
        } catch {
            return false;
        } finally {
            refreshInFlight = null;
        }
    })();

    return refreshInFlight;
};

const ensureTokenFresh = async () => {
    if (!getToken()) return;
    if (!tokenNeedsRefresh()) return;

    const ok = await refreshToken();
    if (!ok) {
        handle401();
    }
};

// =====================================================================
// MULTI-TAB SYNC (sin window.localStorage event como sessionStorage,
// pero localStorage si dispara storage events entre tabs)
// =====================================================================
//
// Si la app esta abierta en 2 tabs y una hace refresh:
// - El token nuevo se guarda en storage (local o session segun "Recordarme")
// - Si es localStorage: la otra tab recibe automaticamente el cambio via
//   el evento 'storage' del browser (nativo, no requiere libreria)
// - Si es sessionStorage: cada tab tiene su propio sessionStorage, asi
//   que no hay sync automatico. Cada tab refresca por su cuenta cuando
//   le toque. Sin race condition porque el backend acepta refrescos
//   sucesivos (cada uno revoca el anterior).
//
// Aca implementamos UN listener basico para localStorage que:
// 1. Detecta cuando otra tab cambio erp_token (login/refresh/logout)
// 2. Limpia el refreshInFlight si esta en curso (la otra tab ya lo hizo)
// 3. Si erp_token quedo null (logout en otra tab), aca tambien hace logout
//
// Para sessionStorage no hay sync porque cada tab tiene la suya. Eso
// es comportamiento esperado: 2 tabs sin "Recordarme" son sesiones
// independientes desde el punto de vista del browser.

if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
    window.addEventListener('storage', (event) => {
        // Solo nos interesan cambios en las claves de auth
        if (event.key !== 'erp_token' && event.key !== 'erp_token_issued_at') {
            return;
        }

        // Si el token cambio (otra tab hizo refresh o login), descartamos
        // cualquier refresh en flight aca - el resultado de la otra tab
        // ya esta guardado y es lo que vamos a usar.
        if (event.key === 'erp_token') {
            refreshInFlight = null;

            // Si erp_token quedo en null (otra tab hizo logout), hacemos
            // logout aca tambien para mantener consistencia.
            // event.newValue es null cuando se ejecuto removeItem en otra tab.
            if (event.newValue === null && window.location.pathname !== '/login') {
                // No llamamos handle401 directamente porque eso intenta hacer
                // cleanup que ya hizo la otra tab. Solo redirigimos.
                clearAuth();
                window.location.href = '/login';
            }
        }
    });
}

const request = async (endpoint, method, body, options = {}) => {
    const esEndpointAuth = endpoint.startsWith('/auth/');
    if (!esEndpointAuth) {
        await ensureTokenFresh();
    }

    const url = `${API_BASE_URL}${endpoint}`;
    const isFormData = body instanceof FormData;
    const silent = options.silent === true;

    const headers = {
        Accept: 'application/json',
        ...getAuthHeaders(),
        ...(options.headers || {}),
    };

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

        if (timedOut) {
            lastError = buildError(408, null, 'Timeout');
            if (attempt < MAX_RETRIES) {
                attempt++;
                await sleep(RETRY_DELAY_MS * attempt);
                continue;
            }
            break;
        }

        if (networkError) {
            lastError = buildError(0, null, 'Network error');
            if (attempt < MAX_RETRIES) {
                attempt++;
                await sleep(RETRY_DELAY_MS * attempt);
                continue;
            }
            break;
        }

        if (response.ok) {
            const data = await parseBody(response);
            return data;
        }

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

    config(opts = {}) {
        if (typeof opts.showErrorToast === 'boolean') {
            globalConfig.showErrorToast = opts.showErrorToast;
        }
        if (typeof opts.timeoutMs === 'number') {
            globalConfig.timeoutMs = opts.timeoutMs;
        }
        return { ...globalConfig };
    },

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

    upload(endpoint, formData, options = {}) {
        if (!(formData instanceof FormData)) {
            return Promise.reject(buildError(0, null, 'upload() requiere FormData'));
        }
        return request(endpoint, options.method || 'POST', formData, options);
    },

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
