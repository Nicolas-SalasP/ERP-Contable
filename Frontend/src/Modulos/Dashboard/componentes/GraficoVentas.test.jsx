import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('recharts', () => ({
    LineChart: ({ children }) => <div data-testid="line-chart">{children}</div>,
    Line: (props) => <div data-testid={`line-${props.dataKey}`} />,
    XAxis: () => null,
    YAxis: () => null,
    CartesianGrid: () => null,
    Tooltip: () => null,
    Legend: () => <div data-testid="legend" />,
    Brush: () => <div data-testid="brush" />,
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

    it('renderiza el Brush cuando hay mas de 6 puntos', () => {
        const datosLargos = Array.from({ length: 12 }, (_, i) => ({
            mes: `2025-${String(i + 1).padStart(2, '0')}`,
            monto: 100000 + i,
        }));
        render(<GraficoVentas datos={datosLargos} />);
        expect(screen.getByTestId('brush')).toBeDefined();
    });

    it('no renderiza el Brush con pocos puntos', () => {
        render(<GraficoVentas datos={datosEjemplo} />);
        expect(screen.queryByTestId('brush')).toBeNull();
    });
});

describe('GraficoVentas — comparación con año anterior', () => {
    const comparacion = {
        serie: [
            { mes: '2026-01', mes_anio_anterior: '2025-01', actual: 500000, anio_anterior: 400000 },
            { mes: '2026-02', mes_anio_anterior: '2025-02', actual: 300000, anio_anterior: 350000 },
        ],
    };

    it('muestra el título de comparación cuando viene la prop comparacion', () => {
        render(<GraficoVentas datos={[]} comparacion={comparacion} />);
        expect(screen.getByText('Ventas — este año vs año anterior')).toBeDefined();
    });

    it('renderiza dos líneas: actual y año anterior', () => {
        render(<GraficoVentas datos={[]} comparacion={comparacion} />);
        expect(screen.getByTestId('line-actual')).toBeDefined();
        expect(screen.getByTestId('line-anio_anterior')).toBeDefined();
    });

    it('renderiza la leyenda cuando compara', () => {
        render(<GraficoVentas datos={[]} comparacion={comparacion} />);
        expect(screen.getByTestId('legend')).toBeDefined();
    });

    it('sin comparacion no renderiza leyenda ni línea de año anterior', () => {
        render(<GraficoVentas datos={[{ mes: '2025-01', monto: 1500000 }]} />);
        expect(screen.queryByTestId('legend')).toBeNull();
        expect(screen.queryByTestId('line-anio_anterior')).toBeNull();
    });
});
