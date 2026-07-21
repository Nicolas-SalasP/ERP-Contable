import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const listenMock = vi.fn();
const leaveMock = vi.fn();
const privateMock = vi.fn(() => ({ listen: listenMock }));
const EchoConstructorMock = vi.fn(function EchoMock() {
    this.private = privateMock;
    this.leave = leaveMock;
});

vi.mock('laravel-echo', () => ({
    default: EchoConstructorMock,
}));

vi.mock('pusher-js', () => ({
    default: function PusherMock() {},
}));

beforeEach(() => {
    vi.resetModules();
    vi.clearAllMocks();
    vi.unstubAllEnvs();
    window.localStorage.clear();
    window.sessionStorage.clear();
    delete window.Pusher;
});

afterEach(() => {
    vi.unstubAllEnvs();
});

describe('inventarioRealtime', () => {
    it('getEchoInventario retorna null si el realtime esta deshabilitado', async () => {
        vi.stubEnv('VITE_INVENTARIO_REALTIME_ENABLED', 'false');
        window.localStorage.setItem('erp_token', 'tok');

        const { getEchoInventario } = await import('./inventarioRealtime');
        const echo = await getEchoInventario();

        expect(echo).toBeNull();
        expect(EchoConstructorMock).not.toHaveBeenCalled();
    });

    it('getEchoInventario retorna null si no hay token en localStorage ni sessionStorage', async () => {
        vi.stubEnv('VITE_INVENTARIO_REALTIME_ENABLED', 'true');

        const { getEchoInventario } = await import('./inventarioRealtime');
        const echo = await getEchoInventario();

        expect(echo).toBeNull();
        expect(EchoConstructorMock).not.toHaveBeenCalled();
    });

    it('getEchoInventario crea una instancia de Echo con el token de localStorage', async () => {
        vi.stubEnv('VITE_INVENTARIO_REALTIME_ENABLED', 'true');
        window.localStorage.setItem('erp_token', 'mi-token');

        const { getEchoInventario } = await import('./inventarioRealtime');
        const echo = await getEchoInventario();

        expect(echo).not.toBeNull();
        expect(EchoConstructorMock).toHaveBeenCalledTimes(1);
        expect(EchoConstructorMock).toHaveBeenCalledWith(
            expect.objectContaining({
                auth: expect.objectContaining({
                    headers: expect.objectContaining({ Authorization: 'Bearer mi-token' }),
                }),
            })
        );
        expect(window.Pusher).toBeDefined();
    });

    it('getEchoInventario usa el token de sessionStorage si no hay en localStorage', async () => {
        vi.stubEnv('VITE_INVENTARIO_REALTIME_ENABLED', 'true');
        window.sessionStorage.setItem('token', 'token-sesion');

        const { getEchoInventario } = await import('./inventarioRealtime');
        await getEchoInventario();

        expect(EchoConstructorMock).toHaveBeenCalledWith(
            expect.objectContaining({
                auth: expect.objectContaining({
                    headers: expect.objectContaining({ Authorization: 'Bearer token-sesion' }),
                }),
            })
        );
    });

    it('getEchoInventario reusa la instancia cacheada en llamadas subsecuentes', async () => {
        vi.stubEnv('VITE_INVENTARIO_REALTIME_ENABLED', 'true');
        window.localStorage.setItem('erp_token', 'tok');

        const { getEchoInventario } = await import('./inventarioRealtime');
        const primera = await getEchoInventario();
        const segunda = await getEchoInventario();

        expect(primera).toBe(segunda);
        expect(EchoConstructorMock).toHaveBeenCalledTimes(1);
    });

    it('suscribirInventarioEmpresa retorna funcion vacia si no hay empresaId', async () => {
        vi.stubEnv('VITE_INVENTARIO_REALTIME_ENABLED', 'true');
        window.localStorage.setItem('erp_token', 'tok');

        const { suscribirInventarioEmpresa } = await import('./inventarioRealtime');
        const cleanup = await suscribirInventarioEmpresa(null, {});

        expect(typeof cleanup).toBe('function');
        expect(privateMock).not.toHaveBeenCalled();
    });

    it('suscribirInventarioEmpresa retorna funcion vacia si echo es null (realtime deshabilitado)', async () => {
        vi.stubEnv('VITE_INVENTARIO_REALTIME_ENABLED', 'false');

        const { suscribirInventarioEmpresa } = await import('./inventarioRealtime');
        const cleanup = await suscribirInventarioEmpresa(1, {});

        expect(typeof cleanup).toBe('function');
        cleanup();
        expect(leaveMock).not.toHaveBeenCalled();
    });

    it('suscribirInventarioEmpresa se suscribe al canal privado y escucha ambos eventos', async () => {
        vi.stubEnv('VITE_INVENTARIO_REALTIME_ENABLED', 'true');
        window.localStorage.setItem('erp_token', 'tok');

        const onAlertasActualizadas = vi.fn();
        const onStockCritico = vi.fn();

        const { suscribirInventarioEmpresa } = await import('./inventarioRealtime');
        await suscribirInventarioEmpresa(42, { onAlertasActualizadas, onStockCritico });

        expect(privateMock).toHaveBeenCalledWith('inventario.empresa.42');
        expect(listenMock).toHaveBeenCalledWith('.inventario.alertas.actualizadas', expect.any(Function));
        expect(listenMock).toHaveBeenCalledWith('.inventario.stock.critico', expect.any(Function));

        const handlerAlertas = listenMock.mock.calls.find((c) => c[0] === '.inventario.alertas.actualizadas')[1];
        const evento = { foo: 'bar' };
        handlerAlertas(evento);
        expect(onAlertasActualizadas).toHaveBeenCalledWith(evento);
    });

    it('el cleanup retornado hace leave del canal', async () => {
        vi.stubEnv('VITE_INVENTARIO_REALTIME_ENABLED', 'true');
        window.localStorage.setItem('erp_token', 'tok');

        const { suscribirInventarioEmpresa } = await import('./inventarioRealtime');
        const cleanup = await suscribirInventarioEmpresa(9, {});
        cleanup();

        expect(leaveMock).toHaveBeenCalledWith('inventario.empresa.9');
    });
});
