import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn() },
}));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn(), warn: vi.fn(), log: vi.fn() },
}));

vi.mock('../../../Contextos/ToastContext', () => ({
    useToast: () => ({ toast: vi.fn() }),
}));

vi.mock('../../../Componentes/Skeleton', () => ({
    TablaSkeleton: () => (
        <tr data-testid="skeleton-carga">
            <td>Cargando...</td>
        </tr>
    ),
}));

vi.mock('../../../Componentes/EstadoVacio', () => ({
    EstadoVacio: ({ mensaje }) => (
        <tr data-testid="estado-vacio">
            <td>{mensaje}</td>
        </tr>
    ),
}));

import ApAging from './ApAging';
import { api } from '../../../Configuracion/api';

afterEach(cleanup);

const apAgingMock = {
    resumen: {
        corriente: 300000,
        d30: 150000,
        d60: 75000,
        d90: 0,
        d90plus: 0,
        total: 525000,
    },
    detalle: [
        {
            proveedor_id: 1,
            razon_social: 'Proveedor Uno SA',
            rut: '76.333.333-3',
            corriente: 300000,
            d30: 150000,
            d60: 75000,
            d90: 0,
            d90plus: 0,
            total: 525000,
        },
    ],
};

describe('ApAging (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza el título CxP por Antigüedad', () => {
        api.get.mockReturnValueOnce(new Promise(() => {}));
        render(<ApAging />);
        expect(screen.getByText(/CxP por Antigüedad/i)).toBeDefined();
    });

    it('muestra el skeleton mientras la api no responde', async () => {
        api.get.mockReturnValueOnce(new Promise(() => {}));
        render(<ApAging />);
        await waitFor(() => {
            expect(screen.getByTestId('skeleton-carga')).toBeDefined();
        });
    });

    it('muestra la tarjeta Corriente con su etiqueta en el resumen', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: apAgingMock });
        render(<ApAging />);
        await waitFor(() => {
            // 'Corriente' aparece en la tarjeta y en el encabezado de tabla
            expect(screen.getAllByText('Corriente').length).toBeGreaterThan(0);
        });
    });

    it('muestra la fila del proveedor en la tabla', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: apAgingMock });
        render(<ApAging />);
        await waitFor(() => {
            expect(screen.getByText('Proveedor Uno SA')).toBeDefined();
        });
    });

    it('muestra el RUT del proveedor en la tabla', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: apAgingMock });
        render(<ApAging />);
        await waitFor(() => {
            expect(screen.getByText('76.333.333-3')).toBeDefined();
        });
    });

    it('muestra la fila de TOTALES en el tfoot al cargar datos', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: apAgingMock });
        render(<ApAging />);
        await waitFor(() => {
            expect(screen.getByText('TOTALES')).toBeDefined();
        });
    });

    it('muestra estado vacío cuando el detalle es un array vacío', async () => {
        api.get.mockResolvedValueOnce({
            success: true,
            data: {
                resumen: { corriente: 0, d30: 0, d60: 0, d90: 0, d90plus: 0, total: 0 },
                detalle: [],
            },
        });
        render(<ApAging />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-vacio')).toBeDefined();
            expect(screen.getByText('No hay saldos pendientes con proveedores.')).toBeDefined();
        });
    });

    it('muestra estado vacío con mensaje de error cuando la api falla', async () => {
        api.get.mockRejectedValueOnce(new Error('Error de red'));
        render(<ApAging />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-vacio')).toBeDefined();
            expect(screen.getByText(/Error al cargar los datos/i)).toBeDefined();
        });
    });

    it('muestra estado vacío de error cuando la api retorna success false', async () => {
        api.get.mockResolvedValueOnce({
            success: false,
            message: 'Sin autorización',
        });
        render(<ApAging />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-vacio')).toBeDefined();
        });
    });

    it('llama a la ruta correcta de la api al montar', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: apAgingMock });
        render(<ApAging />);
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/contabilidad/ap-aging');
        });
    });
});
