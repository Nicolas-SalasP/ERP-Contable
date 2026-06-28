import { describe, it, expect, vi, beforeEach } from 'vitest';

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

import { api } from '../../../Configuracion/api';
import { dj1835, dj1837, dj1926, dj1887, dj1879, dj1947 } from './tributarioApi';

// ─── dj1835 ────────────────────────────────────────────────────────────────────

describe('dj1835', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('listar llama a GET /dj/1835', async () => {
        api.get.mockResolvedValue({ data: [] });
        const result = await dj1835.listar();
        expect(api.get).toHaveBeenCalledWith('/dj/1835');
        expect(result).toEqual([]);
    });

    it('generar llama a POST /dj/1835/generar con { anio }', async () => {
        api.post.mockResolvedValue({ data: { id: 1, anio: 2024 } });
        const result = await dj1835.generar(2024);
        expect(api.post).toHaveBeenCalledWith('/dj/1835/generar', { anio: 2024 });
        expect(result).toEqual({ id: 1, anio: 2024 });
    });

    it('validar llama a POST /dj/1835/42/validar', async () => {
        api.post.mockResolvedValue({ data: { id: 42, estado: 'VALIDADO' } });
        await dj1835.validar(42);
        expect(api.post).toHaveBeenCalledWith('/dj/1835/42/validar');
    });

    it('descargar llama a GET /dj/1835/42/descargar con responseType blob', async () => {
        const fakeBlob = new Blob(['contenido']);
        api.get.mockResolvedValue({ data: fakeBlob });
        const result = await dj1835.descargar(42);
        expect(api.get).toHaveBeenCalledWith('/dj/1835/42/descargar', { responseType: 'blob' });
        expect(result).toBe(fakeBlob);
    });

    it('confirmarPresentacion llama a POST /dj/1835/42/confirmar-presentacion con folio', async () => {
        api.post.mockResolvedValue({ data: { id: 42, folio_presentacion: 'FOL-001' } });
        await dj1835.confirmarPresentacion(42, 'FOL-001');
        expect(api.post).toHaveBeenCalledWith(
            '/dj/1835/42/confirmar-presentacion',
            { folio_presentacion: 'FOL-001' },
        );
    });
});

// ─── dj1837 ────────────────────────────────────────────────────────────────────

describe('dj1837', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('listar llama a GET /dj/1837', async () => {
        api.get.mockResolvedValue({ data: [] });
        const result = await dj1837.listar();
        expect(api.get).toHaveBeenCalledWith('/dj/1837');
        expect(result).toEqual([]);
    });

    it('generar llama a POST /dj/1837/generar con { anio }', async () => {
        api.post.mockResolvedValue({ data: { id: 1, anio: 2024 } });
        const result = await dj1837.generar(2024);
        expect(api.post).toHaveBeenCalledWith('/dj/1837/generar', { anio: 2024 });
        expect(result).toEqual({ id: 1, anio: 2024 });
    });

    it('validar llama a POST /dj/1837/42/validar', async () => {
        api.post.mockResolvedValue({ data: { id: 42, estado: 'VALIDADO' } });
        await dj1837.validar(42);
        expect(api.post).toHaveBeenCalledWith('/dj/1837/42/validar');
    });

    it('descargar llama a GET /dj/1837/42/descargar con responseType blob', async () => {
        const fakeBlob = new Blob(['contenido']);
        api.get.mockResolvedValue({ data: fakeBlob });
        const result = await dj1837.descargar(42);
        expect(api.get).toHaveBeenCalledWith('/dj/1837/42/descargar', { responseType: 'blob' });
        expect(result).toBe(fakeBlob);
    });

    it('confirmarPresentacion llama a POST /dj/1837/42/confirmar-presentacion con folio', async () => {
        api.post.mockResolvedValue({ data: { id: 42, folio_presentacion: 'FOL-001' } });
        await dj1837.confirmarPresentacion(42, 'FOL-001');
        expect(api.post).toHaveBeenCalledWith(
            '/dj/1837/42/confirmar-presentacion',
            { folio_presentacion: 'FOL-001' },
        );
    });
});

// ─── dj1926 ────────────────────────────────────────────────────────────────────

describe('dj1926', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('listar llama a GET /dj/1926', async () => {
        api.get.mockResolvedValue({ data: [] });
        const result = await dj1926.listar();
        expect(api.get).toHaveBeenCalledWith('/dj/1926');
        expect(result).toEqual([]);
    });

    it('generar llama a POST /dj/1926/generar con { anio }', async () => {
        api.post.mockResolvedValue({ data: { id: 1, anio: 2024 } });
        const result = await dj1926.generar(2024);
        expect(api.post).toHaveBeenCalledWith('/dj/1926/generar', { anio: 2024 });
        expect(result).toEqual({ id: 1, anio: 2024 });
    });

    it('validar llama a POST /dj/1926/42/validar', async () => {
        api.post.mockResolvedValue({ data: { id: 42, estado: 'VALIDADO' } });
        await dj1926.validar(42);
        expect(api.post).toHaveBeenCalledWith('/dj/1926/42/validar');
    });

    it('descargar llama a GET /dj/1926/42/descargar con responseType blob', async () => {
        const fakeBlob = new Blob(['contenido']);
        api.get.mockResolvedValue({ data: fakeBlob });
        const result = await dj1926.descargar(42);
        expect(api.get).toHaveBeenCalledWith('/dj/1926/42/descargar', { responseType: 'blob' });
        expect(result).toBe(fakeBlob);
    });

    it('confirmarPresentacion llama a POST /dj/1926/42/confirmar-presentacion con folio', async () => {
        api.post.mockResolvedValue({ data: { id: 42, folio_presentacion: 'FOL-001' } });
        await dj1926.confirmarPresentacion(42, 'FOL-001');
        expect(api.post).toHaveBeenCalledWith(
            '/dj/1926/42/confirmar-presentacion',
            { folio_presentacion: 'FOL-001' },
        );
    });
});

// ─── dj1887 ────────────────────────────────────────────────────────────────────

describe('dj1887', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('listar llama a GET /dj/1887', async () => {
        api.get.mockResolvedValue({ data: [] });
        const result = await dj1887.listar();
        expect(api.get).toHaveBeenCalledWith('/dj/1887');
        expect(result).toEqual([]);
    });

    it('generar llama a POST /dj/1887/generar con { anio }', async () => {
        api.post.mockResolvedValue({ data: { id: 1, anio: 2024 } });
        const result = await dj1887.generar(2024);
        expect(api.post).toHaveBeenCalledWith('/dj/1887/generar', { anio: 2024, anio_40_horas: undefined });
        expect(result).toEqual({ id: 1, anio: 2024 });
    });

    it('generar llama con anio_40_horas cuando se proporciona', async () => {
        api.post.mockResolvedValue({ data: { id: 1, anio: 2024 } });
        await dj1887.generar(2024, 2025);
        expect(api.post).toHaveBeenCalledWith('/dj/1887/generar', { anio: 2024, anio_40_horas: 2025 });
    });

    it('validar llama a POST /dj/1887/42/validar', async () => {
        api.post.mockResolvedValue({ data: { id: 42, estado: 'VALIDADO' } });
        await dj1887.validar(42);
        expect(api.post).toHaveBeenCalledWith('/dj/1887/42/validar');
    });

    it('descargar llama a GET /dj/1887/42/descargar con responseType blob', async () => {
        const fakeBlob = new Blob(['contenido']);
        api.get.mockResolvedValue({ data: fakeBlob });
        const result = await dj1887.descargar(42);
        expect(api.get).toHaveBeenCalledWith('/dj/1887/42/descargar', { responseType: 'blob' });
        expect(result).toBe(fakeBlob);
    });

    it('confirmarPresentacion llama a POST /dj/1887/42/confirmar-presentacion con folio', async () => {
        api.post.mockResolvedValue({ data: { id: 42, folio_presentacion: 'FOL-001' } });
        await dj1887.confirmarPresentacion(42, 'FOL-001');
        expect(api.post).toHaveBeenCalledWith(
            '/dj/1887/42/confirmar-presentacion',
            { folio_presentacion: 'FOL-001' },
        );
    });
});

// ─── dj1879 ────────────────────────────────────────────────────────────────────

describe('dj1879', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('listar llama a GET /dj/1879', async () => {
        api.get.mockResolvedValue({ data: [] });
        const result = await dj1879.listar();
        expect(api.get).toHaveBeenCalledWith('/dj/1879');
        expect(result).toEqual([]);
    });

    it('generar llama a POST /dj/1879/generar con { anio }', async () => {
        api.post.mockResolvedValue({ data: { id: 1, anio: 2024 } });
        const result = await dj1879.generar(2024);
        expect(api.post).toHaveBeenCalledWith('/dj/1879/generar', { anio: 2024 });
        expect(result).toEqual({ id: 1, anio: 2024 });
    });

    it('validar llama a POST /dj/1879/42/validar', async () => {
        api.post.mockResolvedValue({ data: { id: 42, estado: 'VALIDADO' } });
        await dj1879.validar(42);
        expect(api.post).toHaveBeenCalledWith('/dj/1879/42/validar');
    });

    it('descargar llama a GET /dj/1879/42/descargar con responseType blob', async () => {
        const fakeBlob = new Blob(['contenido']);
        api.get.mockResolvedValue({ data: fakeBlob });
        const result = await dj1879.descargar(42);
        expect(api.get).toHaveBeenCalledWith('/dj/1879/42/descargar', { responseType: 'blob' });
        expect(result).toBe(fakeBlob);
    });

    it('confirmarPresentacion llama a POST /dj/1879/42/confirmar-presentacion con folio', async () => {
        api.post.mockResolvedValue({ data: { id: 42, folio_presentacion: 'FOL-001' } });
        await dj1879.confirmarPresentacion(42, 'FOL-001');
        expect(api.post).toHaveBeenCalledWith(
            '/dj/1879/42/confirmar-presentacion',
            { folio_presentacion: 'FOL-001' },
        );
    });
});

// ─── dj1947 ────────────────────────────────────────────────────────────────────

describe('dj1947', () => {
    beforeEach(() => { vi.clearAllMocks(); });

    it('listar llama a GET /dj/1947', async () => {
        api.get.mockResolvedValue({ data: [] });
        const result = await dj1947.listar();
        expect(api.get).toHaveBeenCalledWith('/dj/1947');
        expect(result).toEqual([]);
    });

    it('generar llama a POST /dj/1947/generar con { anio }', async () => {
        api.post.mockResolvedValue({ data: { id: 1, anio: 2024 } });
        const result = await dj1947.generar(2024);
        expect(api.post).toHaveBeenCalledWith('/dj/1947/generar', { anio: 2024 });
        expect(result).toEqual({ id: 1, anio: 2024 });
    });

    it('validar llama a POST /dj/1947/42/validar', async () => {
        api.post.mockResolvedValue({ data: { id: 42, estado: 'VALIDADO' } });
        await dj1947.validar(42);
        expect(api.post).toHaveBeenCalledWith('/dj/1947/42/validar');
    });

    it('descargar llama a GET /dj/1947/42/descargar con responseType blob', async () => {
        const fakeBlob = new Blob(['contenido']);
        api.get.mockResolvedValue({ data: fakeBlob });
        const result = await dj1947.descargar(42);
        expect(api.get).toHaveBeenCalledWith('/dj/1947/42/descargar', { responseType: 'blob' });
        expect(result).toBe(fakeBlob);
    });

    it('confirmarPresentacion llama a POST /dj/1947/42/confirmar-presentacion con folio', async () => {
        api.post.mockResolvedValue({ data: { id: 42, folio_presentacion: 'FOL-001' } });
        await dj1947.confirmarPresentacion(42, 'FOL-001');
        expect(api.post).toHaveBeenCalledWith(
            '/dj/1947/42/confirmar-presentacion',
            { folio_presentacion: 'FOL-001' },
        );
    });
});
