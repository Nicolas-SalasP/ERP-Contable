import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('recharts', () => ({
    PieChart: ({ children }) => <div data-testid="pie-chart">{children}</div>,
    Pie: ({ children, data, onClick }) => (
        <div
            data-testid="pie"
            role="button"
            tabIndex={0}
            onClick={() => onClick?.(data[0])}
            onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onClick?.(data[0]); } }}
        >
            {children}
        </div>
    ),
    Cell: () => null,
    Tooltip: () => null,
    Legend: () => null,
    ResponsiveContainer: ({ children }) => <div data-testid="responsive-container">{children}</div>,
}));

import GraficoDistribucionFacturas from './GraficoDistribucionFacturas';

afterEach(() => {
    cleanup();
});

const DATOS = {
    pagadas:    { cantidad: 5, monto: 500000 },
    pendientes: { cantidad: 2, monto: 200000 },
    vencidas:   { cantidad: 0, monto: 0 },
    anuladas:   { cantidad: 0, monto: 0 },
};

describe('GraficoDistribucionFacturas — render', () => {
    it('no renderiza nada si no hay datos', () => {
        const { container } = render(<GraficoDistribucionFacturas datos={null} />);
        expect(container.firstChild).toBeNull();
    });

    it('no renderiza nada si todas las categorias estan en cero', () => {
        const { container } = render(
            <GraficoDistribucionFacturas
                datos={{
                    pagadas: { cantidad: 0, monto: 0 },
                    pendientes: { cantidad: 0, monto: 0 },
                    vencidas: { cantidad: 0, monto: 0 },
                    anuladas: { cantidad: 0, monto: 0 },
                }}
            />
        );
        expect(container.firstChild).toBeNull();
    });

    it('muestra el titulo cuando hay datos', () => {
        render(<GraficoDistribucionFacturas datos={DATOS} />);
        expect(screen.getByText('Distribución de Facturas')).toBeDefined();
    });
});

describe('GraficoDistribucionFacturas — drill-down', () => {
    it('llama a onSegmentoClick con la clave del segmento clickeado', () => {
        const onSegmentoClick = vi.fn();
        render(<GraficoDistribucionFacturas datos={DATOS} onSegmentoClick={onSegmentoClick} />);
        screen.getByTestId('pie').click();
        expect(onSegmentoClick).toHaveBeenCalledWith('pagadas');
    });

    it('no crashea sin onSegmentoClick', () => {
        render(<GraficoDistribucionFacturas datos={DATOS} />);
        expect(() => screen.getByTestId('pie').click()).not.toThrow();
    });
});
