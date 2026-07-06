import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, cleanup, fireEvent } from '@testing-library/react';
import { mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../test-utils';
import ModalDocumentosFactura from './ModalDocumentosFactura';

const mockSwal = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
}));
vi.mock('sweetalert2', () => ({ default: mockSwal }));

beforeEach(() => {
    cleanTestEnv();
    mockSwal.fire.mockClear();
    mockSwal.fire.mockResolvedValue({ isConfirmed: true });
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

const DOCUMENTOS = [
    { id: 1, nombre_original: 'guia.pdf', mime_type: 'application/pdf', tamano_bytes: 204800 },
    { id: 2, nombre_original: 'foto.jpg', mime_type: 'image/jpeg', tamano_bytes: 512000 },
];

describe('ModalDocumentosFactura', () => {
    it('lista los documentos existentes de la factura', async () => {
        setupFetchRouter({
            'GET /facturas/5/adjuntos': () => mockJsonResponse(200, { success: true, data: DOCUMENTOS }),
        });

        render(<ModalDocumentosFactura facturaId={5} etiqueta="Factura #1" onCerrar={() => {}} />);

        await waitFor(() => expect(screen.getByText('guia.pdf')).toBeTruthy());
        expect(screen.getByText('foto.jpg')).toBeTruthy();
    });

    it('sube varios archivos a la vez en un solo request con documentos[]', async () => {
        let initCapturado = null;
        let listaActual = [];

        setupFetchRouter({
            'GET /facturas/5/adjuntos': () => mockJsonResponse(200, { success: true, data: listaActual }),
            'POST /facturas/5/adjuntos': (body, url, init) => {
                initCapturado = init;
                listaActual = DOCUMENTOS;
                return mockJsonResponse(201, { success: true, message: '2 documentos adjuntados correctamente.', data: DOCUMENTOS });
            },
        });

        const { container } = render(<ModalDocumentosFactura facturaId={5} etiqueta="Factura #1" onCerrar={() => {}} />);
        await waitFor(() => expect(screen.getByText(/Sin documentos adjuntos/i)).toBeTruthy());

        const input = container.querySelector('input[type="file"]');
        const pdf = new File(['x'], 'guia.pdf', { type: 'application/pdf' });
        const foto = new File(['y'], 'foto.jpg', { type: 'image/jpeg' });
        fireEvent.change(input, { target: { files: [pdf, foto] } });

        await waitFor(() => expect(initCapturado).not.toBeNull());
        expect(initCapturado.body).toBeInstanceOf(FormData);
        expect(initCapturado.body.getAll('documentos[]').length).toBe(2);

        await waitFor(() => expect(screen.getByText('guia.pdf')).toBeTruthy());
    });

    it('elimina un documento tras confirmar, y refresca la lista', async () => {
        let eliminado = false;
        let lista = [...DOCUMENTOS];

        setupFetchRouter({
            'GET /facturas/5/adjuntos': () => mockJsonResponse(200, { success: true, data: lista }),
            'DELETE /facturas/5/adjuntos/1': () => {
                eliminado = true;
                lista = lista.filter((d) => d.id !== 1);
                return mockJsonResponse(200, { success: true, message: 'Documento eliminado.' });
            },
        });

        render(<ModalDocumentosFactura facturaId={5} etiqueta="Factura #1" onCerrar={() => {}} />);
        await waitFor(() => expect(screen.getByText('guia.pdf')).toBeTruthy());

        const botonesEliminar = screen.getAllByRole('button', { name: /Eliminar/i });
        fireEvent.click(botonesEliminar[0]);

        await waitFor(() => expect(eliminado).toBe(true));
        await waitFor(() => expect(screen.queryByText('guia.pdf')).toBeNull());
    });

    it('no elimina si el usuario cancela la confirmación', async () => {
        mockSwal.fire.mockResolvedValueOnce({ isConfirmed: false });
        let eliminado = false;

        setupFetchRouter({
            'GET /facturas/5/adjuntos': () => mockJsonResponse(200, { success: true, data: DOCUMENTOS }),
            'DELETE /facturas/5/adjuntos/1': () => {
                eliminado = true;
                return mockJsonResponse(200, { success: true });
            },
        });

        render(<ModalDocumentosFactura facturaId={5} etiqueta="Factura #1" onCerrar={() => {}} />);
        await waitFor(() => expect(screen.getByText('guia.pdf')).toBeTruthy());

        const botonesEliminar = screen.getAllByRole('button', { name: /Eliminar/i });
        fireEvent.click(botonesEliminar[0]);

        await waitFor(() => expect(mockSwal.fire).toHaveBeenCalled());
        expect(eliminado).toBe(false);
        expect(screen.getByText('guia.pdf')).toBeTruthy();
    });
});
