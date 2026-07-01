import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('react-router-dom', () => ({
    useNavigate: () => vi.fn(),
    useParams: () => ({ id: '15' }),
}));

vi.mock('../../../Componentes/EstadoCarga', () => ({
    default: ({ mensajeCargando }) => (
        <div role="status" data-testid="estado-carga">
            {mensajeCargando}
        </div>
    ),
}));

import VisorAsientoCompleto from './VisorAsientoCompleto';
import { api } from '../../../Configuracion/api';

afterEach(cleanup);

const asientoMock = {
    cabecera: {
        id: 15,
        numero_comprobante: 'C-015',
        fecha: '2024-06-10T00:00:00Z',
        glosa: 'Pago arriendo oficina junio',
        estado: 'CONCILIADO',
        tipo_asiento: 'EGRESO',
        origen_modulo: 'Tesoreria',
        usuario_id: 3,
        usuario: { nombre: 'María González' },
        updated_at: '2024-06-10T14:30:00Z',
        created_at: '2024-06-10T14:30:00Z',
    },
    detalles: [
        {
            id: 201,
            cuenta_contable: '6201',
            cuenta_nombre: 'Arriendo',
            glosa_detalle: 'Arriendo junio 2024',
            debe: 500000,
            haber: 0,
        },
        {
            id: 202,
            cuenta_contable: '1101',
            cuenta_nombre: 'Caja',
            glosa_detalle: 'Pago en efectivo',
            debe: 0,
            haber: 500000,
        },
    ],
};

describe('VisorAsientoCompleto (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('muestra el componente de carga mientras se obtiene el asiento', async () => {
        let resolverGet;
        api.get.mockReturnValueOnce(new Promise((r) => { resolverGet = r; }));

        render(<VisorAsientoCompleto />);

        expect(screen.getByTestId('estado-carga')).toBeDefined();
        expect(screen.getByText('Cargando información del asiento...')).toBeDefined();

        await act(async () => {
            resolverGet({ success: true, data: asientoMock });
        });
    });

    it('llama a la API con el id del asiento obtenido de useParams', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: asientoMock });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/facturas/15/asiento');
        });
    });

    it('muestra la cabecera con el número de comprobante', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: asientoMock });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(screen.getByText(/Comprobante Contable #C-015/)).toBeDefined();
        });
    });

    it('muestra la fecha y el estado del asiento', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: asientoMock });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(screen.getByText(/CONCILIADO/)).toBeDefined();
        });
    });

    it('muestra la glosa del asiento en la cabecera', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: asientoMock });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(screen.getByText('Pago arriendo oficina junio')).toBeDefined();
        });
    });

    it('muestra las cuentas contables de las líneas de detalle', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: asientoMock });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(screen.getByText('6201')).toBeDefined();
            expect(screen.getByText('1101')).toBeDefined();
        });
    });

    it('muestra la fila de TOTALES con el debe y haber calculados', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: asientoMock });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(screen.getByText('TOTALES:')).toBeDefined();
            // Ambos totales son 500000; deben aparecer al menos dos veces en la tabla
            const celdas500 = screen.getAllByText('500.000');
            expect(celdas500.length).toBeGreaterThanOrEqual(2);
        });
    });

    it('muestra mensaje de error cuando la API falla', async () => {
        api.get.mockRejectedValueOnce({ message: 'Asiento no encontrado' });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(screen.getByText('Asiento no encontrado')).toBeDefined();
        });
    });

    it('muestra el módulo de origen al pie de la vista', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: asientoMock });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(screen.getByText(/Tesoreria/i)).toBeDefined();
        });
    });

    it('muestra el nombre del usuario que realizó la última modificación', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: asientoMock });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(screen.getByText('María González')).toBeDefined();
        });
    });

    it('el selector de vista cambia al valor seleccionado', async () => {
        api.get.mockResolvedValueOnce({ success: true, data: asientoMock });

        render(<VisorAsientoCompleto />);

        await waitFor(() => {
            expect(screen.getByText('Vista 1 (Básica)')).toBeDefined();
        });

        const selector = screen.getByRole('combobox');
        fireEvent.change(selector, { target: { value: '2' } });

        expect(selector.value).toBe('2');
    });
});
