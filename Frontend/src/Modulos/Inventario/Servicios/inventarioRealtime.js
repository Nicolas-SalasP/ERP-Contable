let echoInstance = null;

const realtimeEnabled = () => import.meta.env.VITE_INVENTARIO_REALTIME_ENABLED === 'true';

const getStorageValue = (key) => {
    if (typeof window === 'undefined') return null;

    return window.localStorage.getItem(key) || window.sessionStorage.getItem(key);
};

const getToken = () => getStorageValue('erp_token') || getStorageValue('token');

const getApiBaseUrl = () => {
    const apiUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
    return apiUrl.replace(/\/api\/?$/, '');
};

export const getEchoInventario = async () => {
    if (!realtimeEnabled() || typeof window === 'undefined') {
        return null;
    }

    if (echoInstance) {
        return echoInstance;
    }

    const [{ default: Echo }, { default: Pusher }] = await Promise.all([
        import('laravel-echo'),
        import('pusher-js'),
    ]);

    window.Pusher = Pusher;

    echoInstance = new Echo({
        broadcaster: import.meta.env.VITE_BROADCAST_DRIVER || 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: `${getApiBaseUrl()}/broadcasting/auth`,
        auth: {
            headers: {
                Authorization: `Bearer ${getToken() || ''}`,
                Accept: 'application/json',
            },
        },
    });

    return echoInstance;
};

export const suscribirInventarioEmpresa = async (empresaId, callbacks = {}) => {
    const echo = await getEchoInventario();

    if (!echo || !empresaId) {
        return () => {};
    }

    const channelName = `inventario.empresa.${empresaId}`;
    const channel = echo.private(channelName);

    channel.listen('.inventario.alertas.actualizadas', (event) => {
        callbacks.onAlertasActualizadas?.(event);
    });

    channel.listen('.inventario.stock.critico', (event) => {
        callbacks.onStockCritico?.(event);
    });

    return () => {
        echo.leave(channelName);
    };
};

export default {
    getEchoInventario,
    suscribirInventarioEmpresa,
};
