import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import EstadoCarga from './EstadoCarga';

afterEach(() => {
    cleanup();
});

describe('EstadoCarga - prioridad de estados', () => {
    it('error tiene prioridad sobre cargando y vacio', () => {
        render(
            <EstadoCarga cargando={true} vacio={true} error="Boom">
                <div>contenido</div>
            </EstadoCarga>
        );

        expect(screen.getByText('Algo salio mal')).toBeDefined();
        expect(screen.getByText('Boom')).toBeDefined();
        expect(screen.queryByText('Cargando...')).toBeNull();
        expect(screen.queryByText('contenido')).toBeNull();
    });

    it('cargando tiene prioridad sobre vacio', () => {
        render(
            <EstadoCarga cargando={true} vacio={true}>
                <div>contenido</div>
            </EstadoCarga>
        );

        expect(screen.getByText('Cargando...')).toBeDefined();
        expect(screen.queryByText('No hay datos para mostrar.')).toBeNull();
        expect(screen.queryByText('contenido')).toBeNull();
    });

    it('vacio se muestra cuando no hay error ni cargando', () => {
        render(
            <EstadoCarga vacio={true}>
                <div>contenido</div>
            </EstadoCarga>
        );

        expect(screen.getByText('No hay datos para mostrar.')).toBeDefined();
        expect(screen.queryByText('contenido')).toBeNull();
    });

    it('renderiza children si ningun estado especial esta activo', () => {
        render(
            <EstadoCarga>
                <div data-testid="real-content">contenido real</div>
            </EstadoCarga>
        );

        expect(screen.getByTestId('real-content')).toBeDefined();
        expect(screen.getByText('contenido real')).toBeDefined();
    });
});

describe('EstadoCarga - estado cargando', () => {
    it('muestra spinner y mensaje por defecto', () => {
        render(<EstadoCarga cargando={true} />);

        expect(screen.getByText('Cargando...')).toBeDefined();
        const status = screen.getByRole('status');
        expect(status).toBeDefined();
        expect(status.getAttribute('aria-busy')).toBe('true');
    });

    it('respeta el mensaje custom', () => {
        render(<EstadoCarga cargando={true} mensajeCargando="Buscando facturas..." />);
        expect(screen.getByText('Buscando facturas...')).toBeDefined();
    });

    it('aplica clase de color custom al spinner', () => {
        const { container } = render(<EstadoCarga cargando={true} color="blue" />);
        const spinner = container.querySelector('.animate-spin');
        expect(spinner).toBeDefined();
        expect(spinner.className).toContain('border-blue-500');
    });

    it('cae a color emerald si pasan un color invalido', () => {
        const { container } = render(<EstadoCarga cargando={true} color="violet-fuxia" />);
        const spinner = container.querySelector('.animate-spin');
        expect(spinner.className).toContain('border-emerald-500');
    });
});

describe('EstadoCarga - estado vacio', () => {
    it('muestra icono y mensaje por defecto', () => {
        render(<EstadoCarga vacio={true} />);
        expect(screen.getByText('No hay datos para mostrar.')).toBeDefined();
        expect(screen.getByText('📭')).toBeDefined();
    });

    it('respeta el icono y mensaje custom', () => {
        render(
            <EstadoCarga
                vacio={true}
                iconoVacio="🔍"
                mensajeVacio="No encontramos resultados."
            />
        );
        expect(screen.getByText('🔍')).toBeDefined();
        expect(screen.getByText('No encontramos resultados.')).toBeDefined();
    });
});

describe('EstadoCarga - estado error', () => {
    it('muestra mensaje de error desde string', () => {
        render(<EstadoCarga error="Sin conexion con el servidor" />);
        expect(screen.getByRole('alert')).toBeDefined();
        expect(screen.getByText('Sin conexion con el servidor')).toBeDefined();
    });

    it('muestra mensaje de error desde objeto Error', () => {
        const err = new Error('El servidor explotó');
        render(<EstadoCarga error={err} />);
        expect(screen.getByText('El servidor explotó')).toBeDefined();
    });

    it('muestra mensaje de error desde respuesta API (response.data.message)', () => {
        const apiError = {
            response: { data: { message: 'Token expirado' } },
            message: 'Request failed with status code 401',
        };
        render(<EstadoCarga error={apiError} />);
        expect(screen.getByText('Token expirado')).toBeDefined();
        expect(screen.queryByText(/Request failed/)).toBeNull();
    });

    it('cae a mensaje generico si el error es un objeto sin campos esperados', () => {
        render(<EstadoCarga error={{ algo: 'raro' }} />);
        expect(screen.getByText('Ocurrio un error inesperado.')).toBeDefined();
    });

    it('NO muestra boton Reintentar si no se pasa onReintentar', () => {
        render(<EstadoCarga error="Error" />);
        expect(screen.queryByRole('button', { name: /reintentar/i })).toBeNull();
    });

    it('muestra boton Reintentar si se pasa onReintentar', () => {
        render(<EstadoCarga error="Error" onReintentar={vi.fn()} />);
        expect(screen.getByRole('button', { name: /reintentar/i })).toBeDefined();
    });

    it('al click en Reintentar invoca el callback', () => {
        const onReintentar = vi.fn();
        render(<EstadoCarga error="Error" onReintentar={onReintentar} />);

        fireEvent.click(screen.getByRole('button', { name: /reintentar/i }));

        expect(onReintentar).toHaveBeenCalledTimes(1);
    });
});

describe('EstadoCarga - tamaños', () => {
    it('completo es el tamaño por defecto y aplica min-h-[60vh]', () => {
        const { container } = render(<EstadoCarga cargando={true} />);
        expect(container.firstChild.className).toContain('min-h-[60vh]');
    });

    it('compacto aplica min-h-[20vh]', () => {
        const { container } = render(<EstadoCarga cargando={true} tamano="compacto" />);
        expect(container.firstChild.className).toContain('min-h-[20vh]');
    });

    it('inline aplica py-3 (sin min-height)', () => {
        const { container } = render(<EstadoCarga cargando={true} tamano="inline" />);
        expect(container.firstChild.className).toContain('py-3');
        expect(container.firstChild.className).not.toContain('min-h-');
    });

    it('cae a completo si pasan un tamaño invalido', () => {
        const { container } = render(<EstadoCarga cargando={true} tamano="gigante" />);
        expect(container.firstChild.className).toContain('min-h-[60vh]');
    });
});

describe('EstadoCarga - className adicional', () => {
    it('agrega className custom al contenedor', () => {
        const { container } = render(
            <EstadoCarga cargando={true} className="mi-clase-custom" />
        );
        expect(container.firstChild.className).toContain('mi-clase-custom');
    });
});

describe('EstadoCarga - accesibilidad', () => {
    it('el estado cargando expone role=status y aria-busy', () => {
        render(<EstadoCarga cargando={true} />);
        const status = screen.getByRole('status');
        expect(status.getAttribute('aria-live')).toBe('polite');
        expect(status.getAttribute('aria-busy')).toBe('true');
    });

    it('el estado error expone role=alert y aria-live=assertive', () => {
        render(<EstadoCarga error="Algo" />);
        const alert = screen.getByRole('alert');
        expect(alert.getAttribute('aria-live')).toBe('assertive');
    });
});
