import React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import GraficoStock from './GraficoStock';

afterEach(() => {
    cleanup();
});

const renderConRouter = (ui) => render(<MemoryRouter>{ui}</MemoryRouter>);

describe('GraficoStock — encabezado', () => {
    it('siempre muestra el título del widget', () => {
        renderConRouter(<GraficoStock />);
        expect(screen.getByText('Inventario — estado actual')).toBeDefined();
    });

    it('siempre muestra el enlace al dashboard de inventario', () => {
        renderConRouter(<GraficoStock />);
        expect(screen.getByText('Ver dashboard de inventario →')).toBeDefined();
    });
});

describe('GraficoStock — estado vacío', () => {
    it('muestra "Sin datos de inventario" cuando datos es undefined', () => {
        renderConRouter(<GraficoStock />);
        expect(screen.getByText('Sin datos de inventario')).toBeDefined();
    });

    it('muestra "Sin datos de inventario" cuando total_productos es 0', () => {
        const datos = { total_productos: 0, bajo_minimo: 0, valor_stock: 0 };
        renderConRouter(<GraficoStock datos={datos} />);
        expect(screen.getByText('Sin datos de inventario')).toBeDefined();
    });

    it('no muestra KPIs cuando no hay datos', () => {
        renderConRouter(<GraficoStock />);
        expect(screen.queryByText('Productos activos')).toBeNull();
    });
});

describe('GraficoStock — con datos', () => {
    const datos = {
        total_productos: 120,
        bajo_minimo: 3,
        valor_stock: 5800000,
    };

    it('no muestra el mensaje de sin datos cuando hay productos', () => {
        renderConRouter(<GraficoStock datos={datos} />);
        expect(screen.queryByText('Sin datos de inventario')).toBeNull();
    });

    it('muestra la cantidad de productos activos', () => {
        renderConRouter(<GraficoStock datos={datos} />);
        expect(screen.getByText('Productos activos')).toBeDefined();
        expect(screen.getByText('120')).toBeDefined();
    });

    it('muestra el conteo de productos bajo stock mínimo', () => {
        renderConRouter(<GraficoStock datos={datos} />);
        expect(screen.getByText('Bajo stock mínimo')).toBeDefined();
        expect(screen.getByText('3')).toBeDefined();
    });

    it('muestra el valor en stock formateado', () => {
        renderConRouter(<GraficoStock datos={datos} />);
        expect(screen.getByText('Valor en stock')).toBeDefined();
        // El helper formatAbreviado convierte 5800000 → "$5.8M"
        expect(screen.getByText('$5.8M')).toBeDefined();
    });

    it('cuando bajo_minimo es 0 no muestra alerta en rojo', () => {
        const sinAlerta = { total_productos: 50, bajo_minimo: 0, valor_stock: 100000 };
        renderConRouter(<GraficoStock datos={sinAlerta} />);
        const span = screen.getByText('0');
        expect(span.className).not.toContain('text-rose-500');
    });

    it('cuando bajo_minimo > 0 el contador aparece en rojo', () => {
        renderConRouter(<GraficoStock datos={datos} />);
        const span = screen.getByText('3');
        expect(span.className).toContain('text-rose-500');
    });
});
