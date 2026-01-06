const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost/ERP-Contable/Backend/Public/api';

const getAuthHeaders = () => {
    const token = localStorage.getItem('erp_token');
    return token ? { 'Authorization': `Bearer ${token}` } : {};
};

const handleResponse = async (response) => {
    const isJson = response.headers.get('content-type')?.includes('application/json');
    const data = isJson ? await response.json() : null;

    if (!response.ok) {
        if (response.status === 401) {
            localStorage.removeItem('erp_token');
            localStorage.removeItem('erp_user');
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
        const response = await fetch(`${BASE_URL}${endpoint}`, config);
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

export const api = {
    get: (endpoint) => request(endpoint, 'GET'),
    post: (endpoint, body) => request(endpoint, 'POST', body),
    put: (endpoint, body) => request(endpoint, 'PUT', body),
    delete: (endpoint) => request(endpoint, 'DELETE'),

    auth: {
        login: async (credentials) => {
            const data = await request('/auth/login', 'POST', credentials);
            if (data.success && data.token) {
                localStorage.setItem('erp_token', data.token);
                localStorage.setItem('erp_user', JSON.stringify(data.user));
            }
            return data;
        },
        register: (data) => request('/auth/register', 'POST', data),
        logout: () => {
            localStorage.removeItem('erp_token');
            localStorage.removeItem('erp_user');
            window.location.href = '/login';
        }
    }
};