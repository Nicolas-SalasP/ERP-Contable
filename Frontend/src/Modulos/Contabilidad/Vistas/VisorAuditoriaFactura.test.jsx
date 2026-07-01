import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn(), log: vi.fn() },
}));

vi.mock('react-router-dom', () => ({
    useNavigate: () => vi.fn(),
    useParams: () => ({ id: '99' }),
}));

import VisorAuditoriaFactura from './VisorAuditoriaFactura';
import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';

afterEach(cleanup);

const facturaMock = {
    id: 99,
    numero_factura: '00123',
    proveedor: 'Proveedor ABC SpA',
    estado: 'CONTABILIZADA',
    monto: 500000,
};

const historialMock = [
    {
        id: 10,
        operacion: 'CONTABILIZACIÓN',
        asiento: 'A-0055',
        fecha: '10/06/2024 14:30',
        detalle: 'Factura contabilizada automáticamente al aprobar.',
        usuario: 'María González',
        estado_ant: 'RECIBIDA',
        estado_nue: 'CONTABILIZADA',
    },
    {
        id: 9,
        operacion: 'RECEPCIÓN',
        asiento: null,
        fecha: '09/06/2024 10:15',
        detalle: 'Factura recibida y registrada en el sistema.',
        usuario: 'Juan Pérez',
        estado_ant: '-',
        estado_nue: 'RECIBIDA',
    },
];

describe('VisorAuditoriaFactura (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('muestra indicador de carga mientras se obtiene la auditoría', async () => {
        let resolverGet;
        api.get.mockReturnValueOnce(new Promise((r) => { resolverGet = r; }));

        render(<VisorAuditoriaFactura />);

        expect(screen.getByText(/Validando registro de auditoría/)).toBeDefined();

        await act(async () => {
            resolverGet({ success: true, data: { factura: facturaMock, historial: historialMock } });
        });
    });

    it('llama a la API de auditoría con el id correcto de useParams', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/facturas/99/auditoria');
        });
    });

    it('muestra el badge Registro de Auditoría y el UUID en cabecera', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(screen.getByText('Registro de Auditoría')).toBeDefined();
            expect(screen.getByText(/UUID: 99/)).toBeDefined();
        });
    });

    it('muestra el número de factura en el encabezado principal', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(screen.getByText('Factura N° 00123')).toBeDefined();
        });
    });

    it('muestra el nombre del proveedor en el encabezado', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(screen.getByText('Proveedor ABC SpA')).toBeDefined();
        });
    });

    it('muestra los eventos de auditoría del historial', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(screen.getByText('CONTABILIZACIÓN')).toBeDefined();
            expect(screen.getByText('RECEPCIÓN')).toBeDefined();
        });
    });

    it('muestra el usuario que ejecutó cada evento de auditoría', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(screen.getByText('María González')).toBeDefined();
            expect(screen.getByText('Juan Pérez')).toBeDefined();
        });
    });

    it('muestra el timestamp de cada evento', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(screen.getByText('10/06/2024 14:30')).toBeDefined();
            expect(screen.getByText('09/06/2024 10:15')).toBeDefined();
        });
    });

    it('muestra el detalle del evento entre comillas', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(
                screen.getByText(/Factura contabilizada automáticamente al aprobar/)
            ).toBeDefined();
        });
    });

    it('muestra el cambio de estado anterior al nuevo (RECIBIDA → CONTABILIZADA)', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(screen.getByText('CONTABILIZADA')).toBeDefined();
        });
    });

    it('muestra mensaje de estado vacío cuando el historial está vacío', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: [] },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(
                screen.getByText(/No se registran movimientos históricos para esta factura/)
            ).toBeDefined();
        });
    });

    it('llama a Swal con icon error cuando la API falla al cargar', async () => {
        api.get.mockRejectedValueOnce(new Error('Fallo de conexión'));

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith(
                expect.objectContaining({ icon: 'error', title: 'Error de Carga' })
            );
        });
    });

    it('muestra el título Cadena de Custodia del Documento', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(screen.getByText(/Cadena de Custodia del Documento/)).toBeDefined();
        });
    });

    it('muestra el botón Cerrar Auditoría', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: { factura: facturaMock, historial: historialMock },
        });

        render(<VisorAuditoriaFactura />);

        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Cerrar Auditoría/i })).toBeDefined();
        });
    });
});
