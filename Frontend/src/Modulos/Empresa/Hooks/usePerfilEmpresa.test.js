import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
    },
}));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn() },
}));

import { api } from '../../../Configuracion/api';
import { usePerfilEmpresa, INITIAL_FORM_DATA } from './usePerfilEmpresa';

const perfilMock = {
    rut: '12.345.678-9',
    razon_social: 'Empresa Test SpA',
    direccion: 'Av. Test 123',
    email: 'test@empresa.cl',
    telefono: '+56 9 1234 5678',
    logo_path: '',
    color_primario: '#ff0000',
    regimen_tributario: '14_A',
    bancos: [{ id: 1, banco: 'BCI', numero_cuenta: '123' }],
    centros_costo: [{ id: 1, codigo: 'VTA', nombre: 'Ventas' }],
};

describe('usePerfilEmpresa', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('inicia con loading=true y formData vacío', () => {
        api.get.mockReturnValue(new Promise(() => {}));
        const { result } = renderHook(() => usePerfilEmpresa());
        expect(result.current.loading).toBe(true);
        expect(result.current.formData).toEqual(INITIAL_FORM_DATA);
    });

    it('carga perfil y popula formData, bancos y centros', async () => {
        api.get.mockImplementation((url) => {
            if (url === '/empresas/perfil') return Promise.resolve({ success: true, data: perfilMock });
            if (url === '/empresas/catalogo-bancos') return Promise.resolve({ success: true, data: [] });
            return Promise.resolve({ success: false });
        });

        const { result } = renderHook(() => usePerfilEmpresa());

        await waitFor(() => expect(result.current.loading).toBe(false));

        expect(result.current.formData.rut).toBe('12.345.678-9');
        expect(result.current.formData.razon_social).toBe('Empresa Test SpA');
        expect(result.current.formData.color_primario).toBe('#ff0000');
        expect(result.current.bancos).toHaveLength(1);
        expect(result.current.centros).toHaveLength(1);
    });

    it('carga catalogo de bancos en listaBancos', async () => {
        const catalogoMock = [{ id: 1, nombre: 'Banco de Chile' }, { id: 2, nombre: 'BCI' }];
        api.get.mockImplementation((url) => {
            if (url === '/empresas/perfil') return Promise.resolve({ success: false });
            if (url === '/empresas/catalogo-bancos') return Promise.resolve({ success: true, data: catalogoMock });
            return Promise.resolve({ success: false });
        });

        const { result } = renderHook(() => usePerfilEmpresa());

        await waitFor(() => expect(result.current.loading).toBe(false));
        expect(result.current.listaBancos).toHaveLength(2);
    });

    it('termina con loading=false aunque el perfil falle', async () => {
        api.get.mockRejectedValue(new Error('Network error'));

        const { result } = renderHook(() => usePerfilEmpresa());

        await waitFor(() => expect(result.current.loading).toBe(false));
        expect(result.current.formData).toEqual(INITIAL_FORM_DATA);
    });

    it('expone recargar como función', async () => {
        api.get.mockResolvedValue({ success: false });
        const { result } = renderHook(() => usePerfilEmpresa());
        await waitFor(() => expect(result.current.loading).toBe(false));
        expect(typeof result.current.recargar).toBe('function');
    });
});
