import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

const permisosMock = vi.hoisted(() => ({
    tienePermiso: vi.fn().mockReturnValue(true),
}));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => permisosMock,
}));

vi.mock('../../../Componentes/EstadoVacio', () => ({
    EstadoVacioDiv: ({ mensaje }) => <div data-testid="estado-vacio">{mensaje}</div>,
}));

vi.mock('../alertasApi', () => ({
    default: {
        listar: vi.fn(),
        resolver: vi.fn(),
    },
}));

import Alertas from './Alertas';
import alertasApi from '../alertasApi';

afterEach(cleanup);
beforeEach(() => {
    vi.clearAllMocks();
    permisosMock.tienePermiso.mockReturnValue(true);
});

const alertasMock = [
    {
        id: 1,
        tipo: 'cxc_vencida',
        nivel: 'critico',
        mensaje: 'Factura de venta N° 100 vencida hace 65 dias, saldo pendiente $500.000',
        created_at: '2026-07-01T10:00:00Z',
    },
    {
        id: 2,
        tipo: 'periodo_sin_cerrar',
        nivel: 'advertencia',
        mensaje: 'El periodo contable 06/2026 sigue sin cerrarse',
        created_at: '2026-07-05T08:00:00Z',
    },
];

describe('Alertas', () => {
    it('muestra el titulo Central de Alertas', async () => {
        alertasApi.listar.mockResolvedValue({ data: { data: { data: [] } } });
        render(<Alertas />);
        expect(screen.getByText('Central de Alertas')).toBeTruthy();
    });

    it('muestra estado vacio cuando no hay alertas', async () => {
        alertasApi.listar.mockResolvedValue({ data: { data: { data: [] } } });
        render(<Alertas />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-vacio')).toBeTruthy();
        });
    });

    it('renderiza la lista de alertas', async () => {
        alertasApi.listar.mockResolvedValue({ data: { data: { data: alertasMock } } });
        render(<Alertas />);
        await waitFor(() => {
            expect(screen.getByText(/Factura de venta N° 100/)).toBeTruthy();
            expect(screen.getByText(/periodo contable 06\/2026/)).toBeTruthy();
        });
    });

    it('muestra botones de resolver/descartar cuando el usuario tiene permiso de gestion', async () => {
        alertasApi.listar.mockResolvedValue({ data: { data: { data: alertasMock } } });
        render(<Alertas />);
        await waitFor(() => {
            expect(screen.getAllByText('Resolver').length).toBeGreaterThan(0);
        });
    });

    it('oculta las acciones cuando el usuario no tiene permiso alertas.gestionar', async () => {
        permisosMock.tienePermiso.mockReturnValue(false);
        alertasApi.listar.mockResolvedValue({ data: { data: { data: alertasMock } } });
        render(<Alertas />);
        await waitFor(() => {
            expect(screen.getByText(/Factura de venta N° 100/)).toBeTruthy();
        });
        expect(screen.queryByText('Resolver')).toBeNull();
    });

    it('resolver llama a alertasApi.resolver con estado resuelta', async () => {
        alertasApi.listar.mockResolvedValue({ data: { data: { data: alertasMock } } });
        alertasApi.resolver.mockResolvedValue({});
        render(<Alertas />);

        await waitFor(() => screen.getAllByText('Resolver')[0]);
        fireEvent.click(screen.getAllByText('Resolver')[0]);

        await waitFor(() => {
            expect(alertasApi.resolver).toHaveBeenCalledWith(1, 'resuelta');
        });
    });

    it('muestra mensaje de error cuando falla la carga', async () => {
        alertasApi.listar.mockRejectedValue(new Error('Error de red'));
        render(<Alertas />);
        await waitFor(() => {
            expect(screen.getByText(/No se pudieron cargar las alertas/i)).toBeTruthy();
        });
    });
});
