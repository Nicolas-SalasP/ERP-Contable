import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('recharts', () => ({
    BarChart: ({ children }) => <div data-testid="bar-chart">{children}</div>,
    Bar: ({ children, onClick }) => (
        <div
            data-testid="bar"
            role="button"
            tabIndex={0}
            onClick={() => onClick?.({ id: 42, nombre: 'Empresa XYZ SpA', monto: 4500000 })}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClick?.({ id: 42, nombre: 'Empresa XYZ SpA', monto: 4500000 }); } }}
        >
            {children}
        </div>
    ),
    XAxis: () => null,
    YAxis: () => null,
    CartesianGrid: () => null,
    Tooltip: () => null,
    ResponsiveContainer: ({ children }) => <div data-testid="responsive-container">{children}</div>,
    Cell: () => null,
}));

import GraficoTopClientes from './GraficoTopClientes';

afterEach(() => {
    cleanup();
});

describe('GraficoTopClientes — encabezado', () => {
    it('siempre muestra el título del gráfico', () => {
        render(<GraficoTopClientes datos={[]} />);
        expect(screen.getByText('Top clientes (últimos 12 meses)')).toBeDefined();
    });
});

describe('GraficoTopClientes — estado vacío', () => {
    it('muestra "Sin datos de clientes" cuando datos es array vacío', () => {
        render(<GraficoTopClientes datos={[]} />);
        expect(screen.getByText('Sin datos de clientes')).toBeDefined();
    });

    it('muestra "Sin datos de clientes" cuando todos los montos son cero', () => {
        const datos = [
            { nombre: 'Cliente A', monto: 0 },
            { nombre: 'Cliente B', monto: 0 },
        ];
        render(<GraficoTopClientes datos={datos} />);
        expect(screen.getByText('Sin datos de clientes')).toBeDefined();
    });

    it('muestra "Sin datos de clientes" cuando datos es null', () => {
        render(<GraficoTopClientes datos={null} />);
        expect(screen.getByText('Sin datos de clientes')).toBeDefined();
    });

    it('no renderiza el gráfico cuando no hay datos', () => {
        render(<GraficoTopClientes datos={[]} />);
        expect(screen.queryByTestId('bar-chart')).toBeNull();
    });
});

describe('GraficoTopClientes — con datos', () => {
    const datosEjemplo = [
        { nombre: 'Empresa XYZ SpA', monto: 4500000 },
        { nombre: 'Comercial ABC Ltda', monto: 3200000 },
        { nombre: 'Servicios 123', monto: 1800000 },
    ];

    it('renderiza el gráfico de barras cuando hay datos con montos positivos', () => {
        render(<GraficoTopClientes datos={datosEjemplo} />);
        expect(screen.getByTestId('bar-chart')).toBeDefined();
    });

    it('no muestra el mensaje de sin datos cuando hay montos positivos', () => {
        render(<GraficoTopClientes datos={datosEjemplo} />);
        expect(screen.queryByText('Sin datos de clientes')).toBeNull();
    });

    it('renderiza el contenedor responsivo', () => {
        render(<GraficoTopClientes datos={datosEjemplo} />);
        expect(screen.getByTestId('responsive-container')).toBeDefined();
    });
});

describe('GraficoTopClientes — drill-down', () => {
    const datosEjemplo = [
        { id: 42, nombre: 'Empresa XYZ SpA', monto: 4500000 },
        { id: 43, nombre: 'Comercial ABC Ltda', monto: 3200000 },
    ];

    it('no muestra la pista de click si no se pasa onClienteClick', () => {
        render(<GraficoTopClientes datos={datosEjemplo} />);
        expect(screen.queryByText(/click para ver ficha/i)).toBeNull();
    });

    it('llama a onClienteClick con el id del cliente al hacer click en la barra', () => {
        const onClienteClick = vi.fn();
        render(<GraficoTopClientes datos={datosEjemplo} onClienteClick={onClienteClick} />);
        screen.getByTestId('bar').click();
        expect(onClienteClick).toHaveBeenCalledWith(42);
    });
});
