import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';

vi.mock('../../Utilidades/glosario', () => ({
    listarModulos: vi.fn(),
    buscarModulos: vi.fn(),
}));

import Glosario from './Glosario';
import { listarModulos, buscarModulos } from '../../Utilidades/glosario';

afterEach(cleanup);

const modulosMock = [
    {
        id: 'asientoManual',
        titulo: 'Asiento Manual',
        icono: '📒',
        resumen: 'Registra ajustes contables.',
        queEs: 'Descripción del asiento manual.',
        conceptos: [{ termino: 'Debe', definicion: 'Lo que entra.' }],
        comoUsar: ['Paso 1'],
        errores: [],
        tip: 'Verifica que cuadre.',
    },
    {
        id: 'planCuentas',
        titulo: 'Plan de Cuentas',
        icono: '📋',
        resumen: 'Estructura de cuentas contables.',
        queEs: 'Descripción del plan de cuentas.',
        conceptos: [],
        comoUsar: [],
        errores: [],
        tip: null,
    },
];

beforeEach(() => {
    vi.clearAllMocks();
    listarModulos.mockReturnValue(modulosMock);
    buscarModulos.mockReturnValue([]);
});

describe('Glosario', () => {
    it('renderiza sin crash', () => {
        render(<Glosario />);
        expect(document.body).toBeTruthy();
    });

    it('muestra el título "Glosario del Sistema"', () => {
        render(<Glosario />);
        expect(screen.getByText(/Glosario del Sistema/i)).toBeTruthy();
    });

    it('muestra los módulos devueltos por listarModulos', () => {
        render(<Glosario />);
        expect(screen.getByText('Asiento Manual')).toBeTruthy();
        expect(screen.getByText('Plan de Cuentas')).toBeTruthy();
        expect(screen.getByText('Registra ajustes contables.')).toBeTruthy();
    });

    it('filtra módulos al escribir en el buscador (llama buscarModulos)', () => {
        buscarModulos.mockReturnValue([modulosMock[0]]);
        render(<Glosario />);

        const input = screen.getByPlaceholderText(/Buscar concepto/i);
        fireEvent.change(input, { target: { value: 'asiento' } });

        expect(buscarModulos).toHaveBeenCalledWith('asiento');
        expect(screen.getByText('Asiento Manual')).toBeTruthy();
    });

    it('muestra mensaje vacío cuando la búsqueda no encuentra resultados', () => {
        buscarModulos.mockReturnValue([]);
        render(<Glosario />);

        const input = screen.getByPlaceholderText(/Buscar concepto/i);
        fireEvent.change(input, { target: { value: 'xyz-inexistente' } });

        expect(screen.getByText(/No se encontraron modulos/i)).toBeTruthy();
    });
});
