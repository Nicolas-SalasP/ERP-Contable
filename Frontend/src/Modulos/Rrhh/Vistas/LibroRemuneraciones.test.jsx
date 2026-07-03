import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

const permisosMock = vi.hoisted(() => ({ tienePermiso: () => true }));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => permisosMock,
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../Servicios/rrhhApi', () => ({
    default: {
        libroRemuneraciones: {
            simular: vi.fn(),
            descargar: vi.fn().mockResolvedValue({}),
        },
    },
}));

import LibroRemuneraciones from './LibroRemuneraciones';
import rrhhApi from '../Servicios/rrhhApi';

const datosSimulacion = {
    cantidad_trabajadores: 1,
    filas: [
        {
            rut: '11.111.111-1',
            nombre: 'Ana Soto',
            cargo: 'Analista',
            dias_trabajados: 30,
            sueldo_base: 800000,
            horas_extras: 0,
            total_haberes: 800000,
            descuento_previsional: 100000,
            descuento_legal: 20000,
            otros_descuentos: 0,
            total_descuentos: 120000,
            liquido: 680000,
        },
    ],
    totales: {
        sueldo_base: 800000,
        horas_extras: 0,
        total_haberes: 800000,
        descuento_previsional: 100000,
        descuento_legal: 20000,
        otros_descuentos: 0,
        total_descuentos: 120000,
        liquido: 680000,
    },
};

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    rrhhApi.libroRemuneraciones.simular.mockClear();
    rrhhApi.libroRemuneraciones.descargar.mockClear();
    rrhhApi.libroRemuneraciones.simular.mockResolvedValue({ data: datosSimulacion });
    rrhhApi.libroRemuneraciones.descargar.mockResolvedValue({});
});

afterEach(cleanup);

describe('LibroRemuneraciones', () => {
    it('carga y lista los datos del período al previsualizar', async () => {
        render(<LibroRemuneraciones />);
        fireEvent.click(screen.getByText('Previsualizar'));

        expect(await screen.findByText('Ana Soto')).toBeDefined();
        expect(screen.getByText('11.111.111-1')).toBeDefined();
        expect(screen.getByText('1 trabajador')).toBeDefined();
    });

    it('muestra los botones de descarga con permiso de ver y permite exportar', async () => {
        render(<LibroRemuneraciones />);
        fireEvent.click(screen.getByText('Previsualizar'));
        await screen.findByText('Ana Soto');

        const botonExcel = screen.getByText(/Descargar Excel/);
        expect(botonExcel).toBeDefined();

        fireEvent.click(botonExcel);
        await waitFor(() => {
            expect(rrhhApi.libroRemuneraciones.descargar).toHaveBeenCalledWith(
                expect.any(Number),
                expect.any(Number),
                'excel',
            );
        });
    });

    it('muestra mensaje informativo cuando no hay liquidaciones en el período', async () => {
        rrhhApi.libroRemuneraciones.simular.mockResolvedValue({
            data: { cantidad_trabajadores: 0, filas: [], totales: null },
        });
        render(<LibroRemuneraciones />);
        fireEvent.click(screen.getByText('Previsualizar'));

        expect(await screen.findByText('Sin liquidaciones emitidas para el período.')).toBeDefined();
    });

    it('deshabilita previsualizar y descargar sin permiso de ver', async () => {
        permisosMock.tienePermiso = () => false;
        render(<LibroRemuneraciones />);

        expect(screen.getByText('Previsualizar').closest('button').disabled).toBe(true);
        expect(rrhhApi.libroRemuneraciones.simular).not.toHaveBeenCalled();
    });
});
