import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';

// Mockeamos dependencias internas de InventarioUI que no nos interesan en tests unitarios
vi.mock('../../../Componentes/AyudaModulo', () => ({
    default: ({ moduloId }) => <button data-testid="ayuda-modulo-boton">{moduloId}</button>,
}));

vi.mock('../../../Componentes/EstadoCarga', () => ({
    default: ({ texto }) => <div data-testid="estado-carga">{texto}</div>,
}));

import {
    formatNumber,
    formatCurrency,
    formatDate,
    getProductoNombre,
    getBodegaNombre,
    EstadoBadge,
} from './InventarioUI';

// ── formatNumber ───────────────────────────────────────────────────────────

describe('formatNumber', () => {
    it('formatea un entero sin decimales', () => {
        expect(formatNumber(1000)).toBe('1.000');
    });

    it('formatea con 2 decimales cuando se pide', () => {
        const result = formatNumber(1234.5, 2);
        expect(result).toContain('1.234');
    });

    it('retorna 0 para null', () => {
        expect(formatNumber(null)).toBe('0');
    });

    it('retorna 0 para undefined', () => {
        expect(formatNumber(undefined)).toBe('0');
    });

    it('retorna 0 para NaN / valores no numéricos', () => {
        expect(formatNumber('abc')).toBe('0');
    });
});

// ── formatCurrency ─────────────────────────────────────────────────────────

describe('formatCurrency', () => {
    it('formatea en pesos chilenos con símbolo $', () => {
        const result = formatCurrency(5000);
        expect(result).toContain('5.000');
        expect(result).toContain('$');
    });

    it('formatea 0 como $0', () => {
        const result = formatCurrency(0);
        expect(result).toContain('$');
    });

    it('maneja null sin lanzar excepción', () => {
        expect(() => formatCurrency(null)).not.toThrow();
    });
});

// ── formatDate ─────────────────────────────────────────────────────────────

describe('formatDate', () => {
    it('retorna "-" para null', () => {
        expect(formatDate(null)).toBe('-');
    });

    it('retorna "-" para undefined', () => {
        expect(formatDate(undefined)).toBe('-');
    });

    it('retorna "-" para cadena vacía', () => {
        expect(formatDate('')).toBe('-');
    });

    it('retorna una cadena de fecha para una fecha ISO válida', () => {
        const result = formatDate('2024-06-15');
        expect(typeof result).toBe('string');
        expect(result).not.toBe('-');
        expect(result.length).toBeGreaterThan(0);
    });
});

// ── getProductoNombre ──────────────────────────────────────────────────────

describe('getProductoNombre', () => {
    it('retorna el nombre del producto anidado', () => {
        expect(getProductoNombre({ producto: { nombre: 'Tornillo' } })).toBe('Tornillo');
    });

    it('retorna nombre_producto como fallback', () => {
        expect(getProductoNombre({ nombre_producto: 'Pintura' })).toBe('Pintura');
    });

    it('retorna producto_nombre como fallback', () => {
        expect(getProductoNombre({ producto_nombre: 'Clavo' })).toBe('Clavo');
    });

    it('retorna "Producto #5" cuando solo hay producto_id', () => {
        expect(getProductoNombre({ producto_id: 5 })).toBe('Producto #5');
    });

    it('retorna "Producto #-" para item nulo', () => {
        expect(getProductoNombre(null)).toBe('Producto #-');
    });
});

// ── getBodegaNombre ────────────────────────────────────────────────────────

describe('getBodegaNombre', () => {
    it('retorna el nombre de la bodega anidada', () => {
        expect(getBodegaNombre({ bodega: { nombre: 'Bodega Central' } })).toBe('Bodega Central');
    });

    it('retorna nombre de bodega_destino como fallback', () => {
        expect(getBodegaNombre({ bodega_destino: { nombre: 'Bodega Norte' } })).toBe('Bodega Norte');
    });

    it('retorna "Bodega #3" cuando solo hay bodega_id', () => {
        expect(getBodegaNombre({ bodega_id: 3 })).toBe('Bodega #3');
    });

    it('retorna nombre de bodega_origen en filas de Salida del Kardex (regresion)', () => {
        // Un movimiento SALIDA solo trae bodega_origen poblada (bodega_destino_id
        // es null); antes de este fix caia siempre en "Bodega #-".
        expect(getBodegaNombre({ bodega_origen: { nombre: 'Bodega Sur' }, bodega_origen_id: 5 })).toBe('Bodega Sur');
    });

    it('retorna "Bodega #5" cuando solo hay bodega_origen_id', () => {
        expect(getBodegaNombre({ bodega_origen_id: 5 })).toBe('Bodega #5');
    });
});

// ── EstadoBadge ────────────────────────────────────────────────────────────

describe('EstadoBadge', () => {
    it('renderiza el estado con guiones reemplazados por espacios', () => {
        render(<EstadoBadge value="STOCK_BAJO" />);
        expect(screen.getByText('STOCK BAJO')).toBeDefined();
    });

    it('aplica clases verdes para estado ACTIVA', () => {
        render(<EstadoBadge value="ACTIVA" />);
        const badge = screen.getByText('ACTIVA');
        expect(badge.className).toMatch(/emerald/);
    });

    it('aplica clases rojas para estado CANCELADA', () => {
        render(<EstadoBadge value="CANCELADA" />);
        const badge = screen.getByText('CANCELADA');
        expect(badge.className).toMatch(/rose/);
    });

    it('renderiza "-" para value undefined', () => {
        render(<EstadoBadge />);
        expect(screen.getByText('-')).toBeDefined();
    });
});
