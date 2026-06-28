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
    Legend: () => null,
    ResponsiveContainer: ({ children }) => <div data-testid="responsive-container">{children}</div>,
}));

import GraficoComprasVsVentas from './GraficoComprasVsVentas';

afterEach(cleanup);

const ventasMock = [
    { mes: '2025-01', monto: 1000000 },
    { mes: '2025-02', monto: 1200000 },
    { mes: '2025-03', monto: 900000 },
];

const comprasMock = [
    { mes: '2025-01', monto: 500000 },
    { mes: '2025-02', monto: 600000 },
    { mes: '2025-03', monto: 450000 },
];

describe('GraficoComprasVsVentas', () => {
    it('renderiza el título del gráfico', () => {
        render(<GraficoComprasVsVentas ventas={ventasMock} compras={comprasMock} />);
        expect(screen.getByText(/Ventas vs Compras — últimos 12 meses/i)).toBeTruthy();
    });

    it('muestra "Sin datos" cuando no hay ventas ni compras', () => {
        render(<GraficoComprasVsVentas ventas={[]} compras={[]} />);
        expect(screen.getByText(/Sin datos de ventas y compras/i)).toBeTruthy();
    });

    it('muestra "Sin datos" cuando montos son todos cero', () => {
        render(
            <GraficoComprasVsVentas
                ventas={[{ mes: '2025-01', monto: 0 }]}
                compras={[{ mes: '2025-01', monto: 0 }]}
            />
        );
        expect(screen.getByText(/Sin datos de ventas y compras/i)).toBeTruthy();
    });

    it('renderiza el gráfico cuando hay datos', () => {
        render(<GraficoComprasVsVentas ventas={ventasMock} compras={comprasMock} />);
        expect(screen.getByTestId('responsive-container')).toBeTruthy();
        expect(screen.getByTestId('line-chart')).toBeTruthy();
    });

    it('funciona sin props (props por defecto son arrays vacíos)', () => {
        render(<GraficoComprasVsVentas />);
        expect(screen.getByText(/Sin datos de ventas y compras/i)).toBeTruthy();
    });

    it('combina ventas y compras por mes correctamente cuando hay meses distintos', () => {
        const ventas = [{ mes: '2025-01', monto: 1000 }, { mes: '2025-02', monto: 2000 }];
        const compras = [{ mes: '2025-01', monto: 500 }]; // Solo tiene enero
        render(<GraficoComprasVsVentas ventas={ventas} compras={compras} />);
        // Con datos reales debe renderizar el gráfico, no "Sin datos"
        expect(screen.queryByText(/Sin datos de ventas y compras/i)).toBeNull();
    });

    it('renderiza correctamente con solo ventas y sin compras', () => {
        render(<GraficoComprasVsVentas ventas={ventasMock} compras={[]} />);
        expect(screen.queryByText(/Sin datos de ventas y compras/i)).toBeNull();
        expect(screen.getByTestId('line-chart')).toBeTruthy();
    });
});

describe('GraficoComprasVsVentas — casos borde', () => {
    it('acepta compras sin meses coincidentes con ventas (compras usa 0 por defecto)', () => {
        const ventas = [{ mes: '2025-04', monto: 5000 }];
        const compras = [{ mes: '2025-05', monto: 3000 }];
        render(<GraficoComprasVsVentas ventas={ventas} compras={compras} />);
        // ventas > 0 pero no hay compras para el mismo mes: no es sinDatos
        expect(screen.queryByText(/Sin datos de ventas y compras/i)).toBeNull();
    });

    it('muestra título correcto con datos de múltiples meses', () => {
        const v = [
            { mes: '2025-01', monto: 100 },
            { mes: '2025-02', monto: 200 },
            { mes: '2025-03', monto: 300 },
        ];
        render(<GraficoComprasVsVentas ventas={v} compras={v} />);
        expect(screen.getByText(/Ventas vs Compras — últimos 12 meses/i)).toBeTruthy();
        expect(screen.getByTestId('line-chart')).toBeTruthy();
    });
});
