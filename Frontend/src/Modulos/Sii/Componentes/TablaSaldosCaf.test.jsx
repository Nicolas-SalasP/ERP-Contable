import React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import TablaSaldosCaf from './TablaSaldosCaf';

afterEach(cleanup);

const saldoFactura = ({ disponibles = 30, total = 50 } = {}) => ({
    '33': {
        tipo_dte: 33,
        nombre: 'Factura Electronica',
        total_autorizado: total,
        disponibles,
        usados: total - disponibles,
        huerfanos: 0,
        cafs_activos: 1,
        cafs_agotados: 0,
    },
});

describe('TablaSaldosCaf', () => {
    it('renderiza skeleton cuando cargando', () => {
        render(<TablaSaldosCaf saldos={{}} cargando={true} />);
        expect(screen.getByTestId('saldos-loading')).toBeDefined();
    });

    it('renderiza empty state si saldos vacio', () => {
        render(<TablaSaldosCaf saldos={{}} cargando={false} />);
        expect(screen.getByTestId('saldos-empty')).toBeDefined();
    });

    it('renderiza columnas para cada tipo', () => {
        render(<TablaSaldosCaf saldos={saldoFactura()} cargando={false} />);
        expect(screen.getByTestId('saldo-33')).toBeDefined();
        expect(screen.getByText(/Factura Electronica/i)).toBeDefined();
    });

    it('barra verde cuando disponibles > 50% del total', () => {
        render(<TablaSaldosCaf saldos={saldoFactura({ disponibles: 40, total: 50 })} cargando={false} />);
        const bar = screen.getByTestId('saldo-33-bar');
        expect(bar.className).toMatch(/bg-emerald-500/);
    });

    it('barra roja cuando disponibles < 20% del total', () => {
        render(<TablaSaldosCaf saldos={saldoFactura({ disponibles: 5, total: 100 })} cargando={false} />);
        const bar = screen.getByTestId('saldo-33-bar');
        expect(bar.className).toMatch(/bg-rose-500/);
    });

    it('barra ambar cuando disponibles entre 20% y 50%', () => {
        render(<TablaSaldosCaf saldos={saldoFactura({ disponibles: 15, total: 50 })} cargando={false} />);
        const bar = screen.getByTestId('saldo-33-bar');
        expect(bar.className).toMatch(/bg-amber-500/);
    });
});
