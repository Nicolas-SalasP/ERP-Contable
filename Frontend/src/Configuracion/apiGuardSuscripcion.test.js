import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { api, setSubscriptionStatus } from './api';
import { setupFetchRouter, mockJsonResponse, cleanTestEnv } from '../test-utils';

const swalMock = vi.hoisted(() => ({ fire: vi.fn().mockResolvedValue({ isConfirmed: true }) }));
vi.mock('sweetalert2', () => ({ default: swalMock }));

const sesionReadOnly = () => {
    setSubscriptionStatus('read_only');
    localStorage.setItem('erp_token', 'tok');
    localStorage.setItem('erp_token_issued_at', new Date().toISOString());
};

describe('api.js - guard de suscripcion en solo lectura', () => {
    beforeEach(() => {
        cleanTestEnv();
        setSubscriptionStatus(null);
        swalMock.fire.mockClear();
        api.config({ showErrorToast: false });
    });
    afterEach(() => vi.clearAllMocks());

    it('bloquea una escritura (POST) y no llama al backend', async () => {
        sesionReadOnly();
        const fetchMock = setupFetchRouter({ 'POST /facturas': () => mockJsonResponse(200, { success: true }) });

        await expect(api.post('/facturas', { x: 1 })).rejects.toMatchObject({ status: 403 });
        expect(swalMock.fire).toHaveBeenCalled();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('permite una lectura (GET) aunque la suscripcion sea read_only', async () => {
        sesionReadOnly();
        const fetchMock = setupFetchRouter({ 'GET /facturas': () => mockJsonResponse(200, { success: true, data: [] }) });

        const res = await api.get('/facturas');

        expect(res.success).toBe(true);
        expect(fetchMock).toHaveBeenCalled();
        expect(swalMock.fire).not.toHaveBeenCalled();
    });
});
