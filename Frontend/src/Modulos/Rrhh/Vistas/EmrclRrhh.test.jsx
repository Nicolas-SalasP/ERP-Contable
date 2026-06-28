import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
    },
}));

vi.mock('@e965/xlsx', () => ({
    utils: {
        book_new: vi.fn(() => ({})),
        aoa_to_sheet: vi.fn(() => ({})),
        book_append_sheet: vi.fn(),
    },
    writeFile: vi.fn(),
}));

vi.mock('../Utilidades/formato', () => ({
    MESES: [
        { valor: 1, label: 'Enero' },
        { valor: 2, label: 'Febrero' },
        { valor: 3, label: 'Marzo' },
        { valor: 4, label: 'Abril' },
        { valor: 5, label: 'Mayo' },
        { valor: 6, label: 'Junio' },
        { valor: 7, label: 'Julio' },
        { valor: 8, label: 'Agosto' },
        { valor: 9, label: 'Septiembre' },
        { valor: 10, label: 'Octubre' },
        { valor: 11, label: 'Noviembre' },
        { valor: 12, label: 'Diciembre' },
    ],
    nombreMes: (mes) => {
        const nombres = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return nombres[Number(mes)] ?? String(mes);
    },
}));

import EmrclRrhh from './EmrclRrhh';
import { api } from '../../../Configuracion/api';

const mockDatos = {
    cuadro1_trabajadores: { mujeres: 10, hombres: 15, total: 25 },
    cuadro2_remuneraciones: {
        mujeres: { remuneraciones_ordinarias: 5000000, aportes_patronales: 500000 },
        hombres: { remuneraciones_ordinarias: 7500000, aportes_patronales: 750000 },
    },
    cuadro3_deducciones: {
        mujeres: { afp: 550000, salud: 350000, afc: 50000, impuesto: 200000 },
        hombres: { afp: 825000, salud: 525000, afc: 75000, impuesto: 300000 },
    },
    cuadro4_horas: {
        mujeres: { horas_pagadas: 1800, derivado: false },
        hombres: { horas_pagadas: 2700, derivado: false },
    },
    advertencias: [],
};

afterEach(cleanup);

describe('EmrclRrhh (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(api.get).mockResolvedValue({ data: mockDatos });
    });

    it('renderiza el título "Reporte EMRCL — INE"', () => {
        render(<EmrclRrhh />);
        expect(screen.getByText('Reporte EMRCL — INE')).toBeTruthy();
    });

    it('muestra el mensaje de estado inicial cuando no hay datos', () => {
        render(<EmrclRrhh />);
        expect(screen.getByText(/selecciona un período y genera el reporte/i)).toBeTruthy();
    });

    it('muestra la advertencia sobre el formulario oficial del INE', () => {
        render(<EmrclRrhh />);
        expect(screen.getByText(/formulario web del INE/i)).toBeTruthy();
    });

    it('el selector de año está presente con el año actual como opción', () => {
        render(<EmrclRrhh />);
        const anioActual = new Date().getFullYear();
        expect(screen.getByDisplayValue(String(anioActual))).toBeTruthy();
    });

    it('el selector de mes está presente con los meses del año', () => {
        render(<EmrclRrhh />);
        expect(screen.getByText('Enero')).toBeTruthy();
        expect(screen.getByText('Diciembre')).toBeTruthy();
    });

    it('el botón "Generar" está presente y no está deshabilitado inicialmente', () => {
        render(<EmrclRrhh />);
        const btn = screen.getByText('Generar');
        expect(btn).toBeTruthy();
        expect(btn.disabled).toBe(false);
    });

    it('llama a api.get con la ruta correcta al hacer clic en "Generar"', async () => {
        render(<EmrclRrhh />);
        fireEvent.click(screen.getByText('Generar'));
        await waitFor(() => expect(vi.mocked(api.get)).toHaveBeenCalledTimes(1));
        const url = vi.mocked(api.get).mock.calls[0][0];
        expect(url).toMatch(/\/rrhh\/emrcl\/\d+\/\d+/);
    });

    it('muestra el Cuadro N°1 con los datos de trabajadores tras generar el reporte', async () => {
        render(<EmrclRrhh />);
        fireEvent.click(screen.getByText('Generar'));
        await waitFor(() => expect(screen.getByText('Cuadro N°1 — Trabajadores')).toBeTruthy());
        expect(screen.getByText('Trabajadores con contrato directo')).toBeTruthy();
        expect(screen.getByText('25')).toBeTruthy();
    });

    it('muestra los cuadros 2, 3 y 4 y el botón "Exportar Excel" tras cargar datos', async () => {
        render(<EmrclRrhh />);
        fireEvent.click(screen.getByText('Generar'));
        await waitFor(() => expect(screen.getByText('Exportar Excel')).toBeTruthy());
        expect(screen.getByText('Remuneraciones ordinarias')).toBeTruthy();
        expect(screen.getByText('Cotización AFP (incluye comisión)')).toBeTruthy();
        expect(screen.getByText('Total horas pagadas')).toBeTruthy();
    });
});
