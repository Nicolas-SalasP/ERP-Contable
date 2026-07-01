import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('recharts', () => ({
    LineChart: ({ children }) => <div data-testid="line-chart">{children}</div>,
    Line: () => null,
    XAxis: () => null,
    YAxis: () => null,
    CartesianGrid: () => null,
    Tooltip: () => null,
    ResponsiveContainer: ({ children }) => <div data-testid="responsive-container">{children}</div>,
}));

import GraficoVentas from './GraficoVentas';

afterEach(() => {
    cleanup();
});

describe('GraficoVentas — encabezado', () => {
    it('siempre muestra el título del gráfico', () => {
        render(<GraficoVentas datos={[]} />);
        expect(screen.getByText('Ventas últimos 12 meses')).toBeDefined();
    });
});

describe('GraficoVentas — estado vacío', () => {
    it('muestra "Sin ventas registradas" cuando datos es array vacío', () => {
        render(<GraficoVentas datos={[]} />);
        expect(screen.getByText('Sin ventas registradas')).toBeDefined();
    });

    it('muestra "Sin ventas registradas" cuando todos los montos son cero', () => {
        const datos = [
            { mes: '2025-01', monto: 0 },
            { mes: '2025-02', monto: 0 },
        ];
        render(<GraficoVentas datos={datos} />);
        expect(screen.getByText('Sin ventas registradas')).toBeDefined();
    });

    it('muestra "Sin ventas registradas" cuando datos es null', () => {
        render(<GraficoVentas datos={null} />);
        expect(screen.getByText('Sin ventas registradas')).toBeDefined();
    });

    it('no renderiza el gráfico cuando no hay datos', () => {
        render(<GraficoVentas datos={[]} />);
        expect(screen.queryByTestId('line-chart')).toBeNull();
    });
});

describe('GraficoVentas — con datos', () => {
    const datosEjemplo = [
        { mes: '2025-01', monto: 1500000 },
        { mes: '2025-02', monto: 2300000 },
        { mes: '2025-03', monto: 870000 },
    ];

    it('renderiza el gráfico cuando hay datos con montos positivos', () => {
        render(<GraficoVentas datos={datosEjemplo} />);
        expect(screen.getByTestId('line-chart')).toBeDefined();
    });

    it('no muestra el mensaje de sin datos cuando hay montos positivos', () => {
        render(<GraficoVentas datos={datosEjemplo} />);
        expect(screen.queryByText('Sin ventas registradas')).toBeNull();
    });

    it('renderiza el contenedor responsivo', () => {
        render(<GraficoVentas datos={datosEjemplo} />);
        expect(screen.getByTestId('responsive-container')).toBeDefined();
    });
});
