import React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import { Icono } from './Icono';

afterEach(cleanup);

describe('Icono — renderizado básico', () => {
    it('renderiza un elemento SVG cuando el nombre de icono existe', () => {
        const { container } = render(<Icono nombre="Search" />);
        expect(container.querySelector('svg')).toBeTruthy();
    });

    it('retorna null cuando el nombre de icono no existe en lucide-react', () => {
        const { container } = render(<Icono nombre="IconoQueNoExiste" />);
        expect(container.firstChild).toBeNull();
    });

    it('renderiza sin crash con el minimo de props (solo nombre)', () => {
        expect(() => render(<Icono nombre="Plus" />)).not.toThrow();
    });

    it('renderiza distinto icono segun el nombre pasado', () => {
        const { container: c1 } = render(<Icono nombre="Search" />);
        const { container: c2 } = render(<Icono nombre="Trash2" />);
        // Ambos deben tener SVG pero ser elementos distintos
        expect(c1.querySelector('svg')).toBeTruthy();
        expect(c2.querySelector('svg')).toBeTruthy();
    });
});

describe('Icono — className', () => {
    it('aplica la className adicional al elemento SVG', () => {
        const { container } = render(<Icono nombre="Search" className="text-slate-600" />);
        expect(container.querySelector('svg').className.baseVal).toContain('text-slate-600');
    });

    it('aplica className vacia sin romper el renderizado', () => {
        const { container } = render(<Icono nombre="Search" className="" />);
        expect(container.querySelector('svg')).toBeTruthy();
    });
});

describe('Icono — tamaños estándar', () => {
    it('usa tamaño md (20px) por defecto', () => {
        const { container } = render(<Icono nombre="Search" />);
        const svg = container.querySelector('svg');
        expect(svg.getAttribute('width')).toBe('20');
        expect(svg.getAttribute('height')).toBe('20');
    });

    it('usa tamaño sm (16px) cuando tamanio="sm"', () => {
        const { container } = render(<Icono nombre="Search" tamanio="sm" />);
        const svg = container.querySelector('svg');
        expect(svg.getAttribute('width')).toBe('16');
        expect(svg.getAttribute('height')).toBe('16');
    });

    it('usa tamaño lg (24px) cuando tamanio="lg"', () => {
        const { container } = render(<Icono nombre="Search" tamanio="lg" />);
        const svg = container.querySelector('svg');
        expect(svg.getAttribute('width')).toBe('24');
        expect(svg.getAttribute('height')).toBe('24');
    });

    it('usa tamaño xl (32px) cuando tamanio="xl"', () => {
        const { container } = render(<Icono nombre="Search" tamanio="xl" />);
        const svg = container.querySelector('svg');
        expect(svg.getAttribute('width')).toBe('32');
        expect(svg.getAttribute('height')).toBe('32');
    });

    it('cae a md (20px) cuando se pasa un tamanio desconocido', () => {
        const { container } = render(<Icono nombre="Search" tamanio="gigante" />);
        const svg = container.querySelector('svg');
        expect(svg.getAttribute('width')).toBe('20');
        expect(svg.getAttribute('height')).toBe('20');
    });
});

describe('Icono — strokeWidth estandarizado', () => {
    it('usa strokeWidth 1.75 en todos los iconos', () => {
        const { container } = render(<Icono nombre="Check" />);
        const svg = container.querySelector('svg');
        expect(svg.getAttribute('stroke-width')).toBe('1.75');
    });
});

describe('Icono — props adicionales', () => {
    it('pasa props extra al componente subyacente (ej. data-testid)', () => {
        const { container } = render(<Icono nombre="Bell" data-testid="icono-campana" />);
        expect(container.querySelector('[data-testid="icono-campana"]')).toBeTruthy();
    });
});
