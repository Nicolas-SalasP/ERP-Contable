import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock('sweetalert2', () => ({
    default: {
        fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
        showLoading: vi.fn(),
    },
}));

vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({ default: () => null }));

vi.mock('../../../Componentes/BotonAccion', () => ({
    default: ({ children, type, disabled, cargando }) => (
        <button type={type || 'button'} disabled={disabled || cargando}>
            {cargando ? 'Buscando...' : children}
        </button>
    ),
}));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn(), log: vi.fn() },
}));

import AnulacionGeneral from './AnulacionGeneral';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';

afterEach(cleanup);

const documentoVigente = {
    id: 42,
    tipo: 'ASIENTO',
    estado: 'VIGENTE',
    descripcion: 'Venta contado enero',
    entidad: 'Módulo Ventas',
    fecha: '2024-01-15T00:00:00Z',
    total: 100000,
    detalles: [
        { id: 1, cuenta_contable: '1101', cuenta: { nombre: 'Caja' }, debe: 100000, haber: 0 },
        { id: 2, cuenta_contable: '4101', cuenta: { nombre: 'Ventas' }, debe: 0, haber: 100000 },
    ],
};

const documentoAnulado = {
    ...documentoVigente,
    estado: 'ANULADO',
};

describe('AnulacionGeneral (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza el título Anulación de Documentos', () => {
        render(<AnulacionGeneral />);
        expect(screen.getByText('Anulación de Documentos')).toBeDefined();
    });

    it('muestra el input con placeholder para código único del documento', () => {
        render(<AnulacionGeneral />);
        expect(screen.getByPlaceholderText('Ej: 2626000001')).toBeDefined();
    });

    it('muestra botón Buscar Documento deshabilitado sin texto en el input', () => {
        render(<AnulacionGeneral />);
        const boton = screen.getByRole('button', { name: /Buscar Documento/i });
        expect(boton.disabled).toBe(true);
    });

    it('busca documento y muestra descripción al recibir respuesta exitosa', async () => {
        api.post.mockResolvedValueOnce({ success: true, data: documentoVigente });

        render(<AnulacionGeneral />);

        const input = screen.getByPlaceholderText('Ej: 2626000001');
        fireEvent.change(input, { target: { value: '2626000001' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() => {
            expect(screen.getByText('Venta contado enero')).toBeDefined();
        });

        expect(api.post).toHaveBeenCalledWith('/anulacion/buscar', {
            tipo_documento: 'ASIENTO',
            numero_documento: '2626000001',
        });
    });

    it('muestra las cuentas de las líneas de detalle del asiento encontrado', async () => {
        api.post.mockResolvedValueOnce({ success: true, data: documentoVigente });

        render(<AnulacionGeneral />);

        const input = screen.getByPlaceholderText('Ej: 2626000001');
        fireEvent.change(input, { target: { value: '2626000001' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() => {
            expect(screen.getByText('1101')).toBeDefined();
            expect(screen.getByText('4101')).toBeDefined();
        });
    });

    it('muestra mensaje de error cuando la API indica que no se encontró el documento', async () => {
        api.post.mockResolvedValueOnce({ success: false, mensaje: 'Documento no encontrado o código inválido.' });

        render(<AnulacionGeneral />);

        const input = screen.getByPlaceholderText('Ej: 2626000001');
        fireEvent.change(input, { target: { value: '0000' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() => {
            expect(screen.getByText(/Documento no encontrado/)).toBeDefined();
        });
    });

    it('muestra mensaje de error de conexión cuando la llamada lanza excepción', async () => {
        api.post.mockRejectedValueOnce(new Error('Network Error'));

        render(<AnulacionGeneral />);

        const input = screen.getByPlaceholderText('Ej: 2626000001');
        fireEvent.change(input, { target: { value: '2626000001' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() => {
            expect(screen.getByText(/Error de conexión al buscar/)).toBeDefined();
        });
    });

    it('muestra badge ANULADO cuando el documento ya fue anulado previamente', async () => {
        api.post.mockResolvedValueOnce({ success: true, data: documentoAnulado });

        render(<AnulacionGeneral />);

        const input = screen.getByPlaceholderText('Ej: 2626000001');
        fireEvent.change(input, { target: { value: '2626000001' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() => {
            expect(screen.getByText('ANULADO')).toBeDefined();
        });
    });

    it('el botón Confirmar Anulación está deshabilitado cuando no hay motivo', async () => {
        api.post.mockResolvedValueOnce({ success: true, data: documentoVigente });

        render(<AnulacionGeneral />);

        const input = screen.getByPlaceholderText('Ej: 2626000001');
        fireEvent.change(input, { target: { value: '2626000001' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() => {
            expect(screen.getByText('Zona de Anulación')).toBeDefined();
        });

        // El botón está deshabilitado cuando el motivo está vacío (validación en la UI)
        const btn = screen.getByRole('button', { name: /Confirmar Anulación/i });
        expect(btn.disabled).toBe(true);
    });

    it('ejecuta la anulación y muestra mensaje de éxito tras confirmar con motivo', async () => {
        api.post
            .mockResolvedValueOnce({ success: true, data: documentoVigente })
            .mockResolvedValueOnce({ success: true });

        render(<AnulacionGeneral />);

        const input = screen.getByPlaceholderText('Ej: 2626000001');
        fireEvent.change(input, { target: { value: '2626000001' } });
        fireEvent.submit(input.closest('form'));

        await waitFor(() => {
            expect(screen.getByPlaceholderText(/Describa claramente/)).toBeDefined();
        });

        fireEvent.change(screen.getByPlaceholderText(/Describa claramente/), {
            target: { value: 'Error de digitación en monto' },
        });

        await act(async () => {
            fireEvent.click(screen.getByRole('button', { name: /Confirmar Anulación/i }));
        });

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith('/anulacion/anular', expect.objectContaining({
                tipo_documento: 'ASIENTO',
                documento_id: 42,
                motivo: 'Error de digitación en monto',
            }));
        });

        await waitFor(() => {
            expect(screen.getByText(/Operación Exitosa/)).toBeDefined();
        });
    });
});
