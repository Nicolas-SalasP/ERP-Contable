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

import ArAging from './ArAging';
import { api } from '../../../Configuracion/api';

afterEach(cleanup);

const arAgingMock = {
    resumen: {
        corriente: 500000,
        d30: 200000,
        d60: 100000,
        d90: 50000,
        d90plus: 25000,
        total: 875000,
    },
    detalle: [
        {
            cliente_id: 1,
            razon_social: 'Cliente Uno SpA',
            rut: '76.111.111-1',
            corriente: 500000,
            d30: 200000,
            d60: 0,
            d90: 0,
            d90plus: 0,
            total: 700000,
        },
        {
            cliente_id: 2,
            razon_social: 'Cliente Dos Ltda',
            rut: '76.222.222-2',
            corriente: 0,
            d30: 0,
            d60: 100000,
            d90: 50000,
            d90plus: 25000,
            total: 175000,
        },
    ],
};

describe('ArAging (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza el título Cartera de Clientes por Cobrar', () => {
        api.get.mockReturnValueOnce(new Promise(() => {}));
        render(<ArAging />);
        expect(screen.getByText(/Cartera de Clientes por Cobrar/i)).toBeDefined();
    });

    it('muestra el skeleton mientras la api no responde', async () => {
        api.get.mockReturnValueOnce(new Promise(() => {}));
        render(<ArAging />);
        await waitFor(() => {
            expect(screen.getByTestId('skeleton-carga')).toBeDefined();
        });
    });

    it('muestra la tarjeta Corriente con su etiqueta al cargar datos', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: arAgingMock });
        render(<ArAging />);
        await waitFor(() => {
            // 'Corriente' aparece en la tarjeta y en el encabezado de tabla
            expect(screen.getAllByText('Corriente').length).toBeGreaterThan(0);
        });
    });

    it('muestra las filas de clientes en la tabla', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: arAgingMock });
        render(<ArAging />);
        await waitFor(() => {
            expect(screen.getByText('Cliente Uno SpA')).toBeDefined();
            expect(screen.getByText('Cliente Dos Ltda')).toBeDefined();
        });
    });

    it('muestra la fila de TOTALES en el tfoot al cargar datos', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: arAgingMock });
        render(<ArAging />);
        await waitFor(() => {
            expect(screen.getByText('TOTALES')).toBeDefined();
        });
    });

    it('muestra el RUT de los clientes en la tabla', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: arAgingMock });
        render(<ArAging />);
        await waitFor(() => {
            expect(screen.getByText('76.111.111-1')).toBeDefined();
            expect(screen.getByText('76.222.222-2')).toBeDefined();
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
        render(<ArAging />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-vacio')).toBeDefined();
            expect(screen.getByText('No hay saldos pendientes de clientes.')).toBeDefined();
        });
    });

    it('muestra estado vacío con mensaje de error cuando la api falla', async () => {
        api.get.mockRejectedValueOnce(new Error('Error de red'));
        render(<ArAging />);
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
        render(<ArAging />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-vacio')).toBeDefined();
        });
    });

    it('llama a la ruta correcta de la api al montar', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: arAgingMock });
        render(<ArAging />);
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/contabilidad/ar-aging');
        });
    });
});
