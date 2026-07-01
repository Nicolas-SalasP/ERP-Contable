import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

// vi.mock ANTES de imports del módulo bajo test
vi.mock('../Servicios/comercialApi', () => ({
    ordenesCompra: {
        listar: vi.fn(),
        crear: vi.fn(),
        obtener: vi.fn(),
        recibirMercaderia: vi.fn(),
        anular: vi.fn(),
    },
}));
vi.mock('../../../Componentes/Skeleton', () => ({
    TablaSkeleton: () => <tr><td colSpan="6">Cargando...</td></tr>,
}));
vi.mock('../../../Componentes/EstadoVacio', () => ({
    EstadoVacio: ({ mensaje }) => <tr><td colSpan="6">{mensaje}</td></tr>,
}));

import { ordenesCompra } from '../Servicios/comercialApi';
import GestionOrdenes from './GestionOrdenes';

afterEach(cleanup);

const ocBorrador = {
    id: 1,
    numero_oc: 'OC-0001',
    proveedor: { razon_social: 'Proveedor Prueba SA' },
    fecha_emision: '2026-01-15',
    fecha_entrega_esperada: '2026-01-30',
    total: 119000,
    estado: 'BORRADOR',
};

const ocEnviada = {
    id: 2,
    numero_oc: 'OC-0002',
    proveedor: null,
    fecha_emision: '2026-02-01',
    fecha_entrega_esperada: null,
    total: 59500,
    estado: 'ENVIADA',
};

describe('GestionOrdenes', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        ordenesCompra.listar.mockResolvedValue({ data: [] });
    });

    it('renderiza el título "Órdenes de Compra"', async () => {
        render(<GestionOrdenes />);
        await waitFor(() => {
            expect(screen.getAllByText('Órdenes de compra').length).toBeGreaterThan(0);
        });
    });

    it('muestra estado vacío cuando no hay OCs', async () => {
        ordenesCompra.listar.mockResolvedValue({ data: [] });
        render(<GestionOrdenes />);
        await waitFor(() => {
            expect(screen.getByText('Sin órdenes de compra registradas.')).toBeTruthy();
        });
    });

    it('muestra filas de OCs cuando la api retorna datos', async () => {
        ordenesCompra.listar.mockResolvedValue({ data: [ocBorrador, ocEnviada] });
        render(<GestionOrdenes />);
        await waitFor(() => {
            expect(screen.getByText('OC-0001')).toBeTruthy();
            expect(screen.getByText('OC-0002')).toBeTruthy();
        });
    });

    it('muestra el nombre del proveedor o "Sin proveedor" cuando es null', async () => {
        ordenesCompra.listar.mockResolvedValue({ data: [ocBorrador, ocEnviada] });
        render(<GestionOrdenes />);
        await waitFor(() => {
            expect(screen.getByText('Proveedor Prueba SA')).toBeTruthy();
            expect(screen.getByText('Sin proveedor')).toBeTruthy();
        });
    });

    it('muestra badge "Borrador" para OC en estado BORRADOR', async () => {
        ordenesCompra.listar.mockResolvedValue({ data: [ocBorrador] });
        render(<GestionOrdenes />);
        await waitFor(() => {
            expect(screen.getByText('Borrador')).toBeTruthy();
        });
    });

    it('muestra badge "Enviada" para OC en estado ENVIADA', async () => {
        ordenesCompra.listar.mockResolvedValue({ data: [ocEnviada] });
        render(<GestionOrdenes />);
        await waitFor(() => {
            expect(screen.getByText('Enviada')).toBeTruthy();
        });
    });

    it('botón "Nueva OC" abre el modal de creación', async () => {
        render(<GestionOrdenes />);
        await waitFor(() => screen.getByText('Sin órdenes de compra registradas.'));
        fireEvent.click(screen.getByRole('button', { name: /Nueva OC/i }));
        expect(screen.getByText('Nueva Orden de Compra')).toBeTruthy();
    });

    it('el modal de creación se cierra con el botón Cancelar', async () => {
        render(<GestionOrdenes />);
        await waitFor(() => screen.getByText('Sin órdenes de compra registradas.'));
        fireEvent.click(screen.getByRole('button', { name: /Nueva OC/i }));
        expect(screen.getByText('Nueva Orden de Compra')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: /Cancelar/i }));
        expect(screen.queryByText('Nueva Orden de Compra')).toBeNull();
    });

    it('filtro por estado actualiza el parámetro enviado a listar', async () => {
        render(<GestionOrdenes />);
        await waitFor(() => screen.getByText('Sin órdenes de compra registradas.'));
        const select = screen.getByDisplayValue('Todos');
        fireEvent.change(select, { target: { value: 'ENVIADA' } });
        await waitFor(() => {
            expect(ordenesCompra.listar).toHaveBeenLastCalledWith({ estado: 'ENVIADA' });
        });
    });
});
