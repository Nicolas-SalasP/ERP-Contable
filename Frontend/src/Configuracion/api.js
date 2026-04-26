// Detección dinámica del entorno
const hostname = window.location.hostname;
const isLocal = hostname === 'localhost' || hostname === '127.0.0.1';

export const API_BASE_URL = import.meta.env.VITE_API_URL || 
    (isLocal 
        ? 'http://127.0.0.1:8000/api' 
        : 'https://erp.tenri.cl/api');
        
// Función para obtener headers con limpieza de token
const getAuthHeaders = () => {
    let token = localStorage.getItem('erp_token') || sessionStorage.getItem('erp_token');
    
    if (token) {
        if (token.startsWith('"') && token.endsWith('"')) {
            token = token.slice(1, -1);
        }
        return { 'Authorization': `Bearer ${token}` };
    }
    return {};
};

// Manejador de respuestas unificado
const handleResponse = async (response) => {
    const isJson = response.headers.get('content-type')?.includes('application/json');
    const data = isJson ? await response.json() : null;

    if (!response.ok) {
        if (response.status === 401) {
            localStorage.removeItem('erp_token');
            localStorage.removeItem('erp_user');
            sessionStorage.removeItem('erp_token');
            sessionStorage.removeItem('erp_user');
            
            if (!window.location.pathname.includes('/login')) {
                window.location.href = '/login';
            }
        }

        const error = {
            status: response.status,
            success: false,
            code: data?.error_code || 'ERROR_DESCONOCIDO',
            message: data?.message || response.statusText,
        };
        
        return Promise.reject(error);
    }

    return data;
};

// Función base para realizar peticiones (Wrapper de Fetch)
const request = async (endpoint, method, body = null, customHeaders = {}) => {
    const config = {
        method,
        headers: {
            'Content-Type': 'application/json',
            ...getAuthHeaders(),
            ...customHeaders,
        },
    };

    if (body) {
        config.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, config);
        return await handleResponse(response);
    } catch (error) {
        if (!error.status) {
            throw {
                success: false,
                status: 0,
                code: 'ERROR_RED',
                message: 'No hay conexión con el servidor.',
            };
        }
        throw error;
    }
};

// Objeto API exportado para usar en los componentes
export const api = {
    defaults: {
        baseURL: API_BASE_URL
    },

    get: (endpoint) => request(endpoint, 'GET'),
    post: (endpoint, body) => request(endpoint, 'POST', body),
    put: (endpoint, body) => request(endpoint, 'PUT', body),
    delete: (endpoint) => request(endpoint, 'DELETE'),

    auth: {
        login: async (credentials) => {
            return await request('/auth/login', 'POST', credentials);
        },
        register: (data) => request('/auth/register', 'POST', data),
        logout: () => {
            localStorage.removeItem('erp_token');
            localStorage.removeItem('erp_user');
            sessionStorage.removeItem('erp_token');
            sessionStorage.removeItem('erp_user');
            window.location.href = '/login';
        }
    }
};