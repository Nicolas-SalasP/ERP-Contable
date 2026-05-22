import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import BotonAccion from './BotonAccion';

afterEach(() => {
    cleanup();
});

describe('BotonAccion - render normal', () => {
    it('renderiza el texto/children', () => {
        render(<BotonAccion>Guardar</BotonAccion>);
        expect(screen.getByRole('button', { name: 'Guardar' })).toBeDefined();
    });

    it('no tiene disabled cuando no esta cargando y no se le pasa disabled', () => {
        render(<BotonAccion>Aceptar</BotonAccion>);
        const btn = screen.getByRole('button');
        expect(btn.disabled).toBe(false);
        expect(btn.getAttribute('aria-busy')).toBe('false');
    });

    it('renderiza el icono cuando se pasa como string (FontAwesome)', () => {
        const { container } = render(
            <BotonAccion icono="fas fa-save">Guardar</BotonAccion>
        );
        const i = container.querySelector('i.fas.fa-save');
        expect(i).toBeDefined();
        expect(i).not.toBeNull();
    });

    it('renderiza el icono cuando se pasa como JSX', () => {
        render(
            <BotonAccion icono={<span data-testid="mi-icono">📝</span>}>
                Editar
            </BotonAccion>
        );
        expect(screen.getByTestId('mi-icono')).toBeDefined();
    });

    it('aplica color emerald por defecto', () => {
        render(<BotonAccion>Click</BotonAccion>);
        expect(screen.getByRole('button').className).toContain('bg-emerald-600');
    });

    it('aplica color custom (blue)', () => {
        render(<BotonAccion color="blue">Click</BotonAccion>);
        expect(screen.getByRole('button').className).toContain('bg-blue-600');
    });

    it('cae a emerald si pasan color invalido', () => {
        render(<BotonAccion color="violet-fuxia">Click</BotonAccion>);
        expect(screen.getByRole('button').className).toContain('bg-emerald-600');
    });

    it('aplica tamaño md por defecto', () => {
        render(<BotonAccion>Click</BotonAccion>);
        expect(screen.getByRole('button').className).toContain('px-4');
    });

    it('aplica tamaño sm', () => {
        render(<BotonAccion tamano="sm">Click</BotonAccion>);
        expect(screen.getByRole('button').className).toContain('px-3');
    });

    it('agrega className adicional', () => {
        render(<BotonAccion className="mi-clase-extra">Click</BotonAccion>);
        expect(screen.getByRole('button').className).toContain('mi-clase-extra');
    });
});

describe('BotonAccion - estado cargando', () => {
    it('muestra textoCargando en lugar de children', () => {
        render(
            <BotonAccion cargando={true} textoCargando="Guardando...">
                Guardar
            </BotonAccion>
        );
        expect(screen.getByText('Guardando...')).toBeDefined();
        expect(screen.queryByText('Guardar')).toBeNull();
    });

    it('muestra spinner SVG cuando esta cargando', () => {
        const { container } = render(
            <BotonAccion cargando={true}>Guardar</BotonAccion>
        );
        const svg = container.querySelector('svg.animate-spin');
        expect(svg).toBeDefined();
        expect(svg).not.toBeNull();
    });

    it('NO muestra spinner cuando no esta cargando', () => {
        const { container } = render(<BotonAccion>Guardar</BotonAccion>);
        expect(container.querySelector('svg.animate-spin')).toBeNull();
    });

    it('queda disabled cuando esta cargando', () => {
        render(<BotonAccion cargando={true}>Guardar</BotonAccion>);
        const btn = screen.getByRole('button');
        expect(btn.disabled).toBe(true);
    });

    it('aria-busy es true cuando esta cargando', () => {
        render(<BotonAccion cargando={true}>Guardar</BotonAccion>);
        expect(screen.getByRole('button').getAttribute('aria-busy')).toBe('true');
    });

    it('usa textoCargando default si no se pasa', () => {
        render(<BotonAccion cargando={true}>Algo</BotonAccion>);
        expect(screen.getByText('Procesando...')).toBeDefined();
    });

    it('aplica clase visual de disabled cuando cargando', () => {
        render(<BotonAccion cargando={true} color="emerald">X</BotonAccion>);
        expect(screen.getByRole('button').className).toContain('cursor-not-allowed');
    });
});

describe('BotonAccion - disabled manual', () => {
    it('queda disabled cuando disabled=true (sin cargando)', () => {
        render(<BotonAccion disabled={true}>Click</BotonAccion>);
        const btn = screen.getByRole('button');
        expect(btn.disabled).toBe(true);
        // Pero no debe mostrar spinner
        expect(btn.getAttribute('aria-busy')).toBe('false');
    });

    it('aplica clase visual de disabled cuando disabled=true', () => {
        render(<BotonAccion disabled={true}>X</BotonAccion>);
        expect(screen.getByRole('button').className).toContain('cursor-not-allowed');
    });

    it('muestra children normalmente cuando disabled (no textoCargando)', () => {
        render(
            <BotonAccion disabled={true} textoCargando="Procesando...">
                Click
            </BotonAccion>
        );
        // El children "Click" debe aparecer, no "Procesando..."
        expect(screen.getByText('Click')).toBeDefined();
        expect(screen.queryByText('Procesando...')).toBeNull();
    });
});

describe('BotonAccion - eventos', () => {
    it('invoca onClick cuando se hace click', () => {
        const onClick = vi.fn();
        render(<BotonAccion onClick={onClick}>Click</BotonAccion>);

        fireEvent.click(screen.getByRole('button'));

        expect(onClick).toHaveBeenCalledTimes(1);
    });

    it('NO invoca onClick cuando esta cargando', () => {
        const onClick = vi.fn();
        render(
            <BotonAccion cargando={true} onClick={onClick}>
                Click
            </BotonAccion>
        );

        fireEvent.click(screen.getByRole('button'));

        expect(onClick).not.toHaveBeenCalled();
    });

    it('NO invoca onClick cuando esta disabled', () => {
        const onClick = vi.fn();
        render(
            <BotonAccion disabled={true} onClick={onClick}>
                Click
            </BotonAccion>
        );

        fireEvent.click(screen.getByRole('button'));

        expect(onClick).not.toHaveBeenCalled();
    });
});

describe('BotonAccion - type', () => {
    it('por defecto type=button (no submit)', () => {
        render(<BotonAccion>X</BotonAccion>);
        expect(screen.getByRole('button').type).toBe('button');
    });

    it('respeta type=submit cuando se pasa', () => {
        render(<BotonAccion type="submit">Enviar</BotonAccion>);
        expect(screen.getByRole('button').type).toBe('submit');
    });
});

describe('BotonAccion - pasa props adicionales al boton', () => {
    it('respeta title (tooltip)', () => {
        render(<BotonAccion title="Pista util">X</BotonAccion>);
        expect(screen.getByRole('button').title).toBe('Pista util');
    });
});
