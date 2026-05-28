import React from 'react';
import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import AyudaModulo from './AyudaModulo';
import { listarModulos, obtenerModulo, buscarModulos, glosario } from '../Utilidades/glosario';

afterEach(cleanup);

describe('AyudaModulo - boton', () => {
    it('renderiza el boton con accesibilidad correcta', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        const boton = screen.getByTestId('ayuda-modulo-boton');
        expect(boton).toBeDefined();
        expect(boton.getAttribute('aria-label')).toContain('Asiento Manual');
        expect(boton.getAttribute('title')).toContain('Asiento Manual');
    });

    it('aplica el tamaño custom via prop size', () => {
        render(<AyudaModulo moduloId="asientoManual" size={32} />);
        const boton = screen.getByTestId('ayuda-modulo-boton');
        expect(boton.style.width).toBe('32px');
        expect(boton.style.height).toBe('32px');
    });

    it('NO renderiza nada si moduloId no existe', () => {
        // Silenciar console.warn esperado
        const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const { container } = render(<AyudaModulo moduloId="moduloInexistente" />);
        expect(container.innerHTML).toBe('');
        expect(warnSpy).toHaveBeenCalledWith(
            expect.stringContaining('moduloInexistente')
        );
        warnSpy.mockRestore();
    });
});

describe('AyudaModulo - modal', () => {
    it('al click abre el modal con el contenido correcto', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        expect(screen.queryByTestId('ayuda-modulo-modal')).toBeNull();

        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));

        expect(screen.getByTestId('ayuda-modulo-modal')).toBeDefined();
        expect(screen.getByText('Asiento Manual')).toBeDefined();
        expect(screen.getByText(/Registra ajustes contables/)).toBeDefined();
    });

    it('muestra la seccion "¿Que es?"', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        expect(screen.getByText('¿Que es?')).toBeDefined();
    });

    it('muestra los conceptos clave', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        expect(screen.getByText('Conceptos clave')).toBeDefined();
        expect(screen.getByText('Debe / Haber')).toBeDefined();
        expect(screen.getByText('Glosa')).toBeDefined();
    });

    it('muestra los pasos de "Como se usa"', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        expect(screen.getByText('Como se usa')).toBeDefined();
    });

    it('muestra los errores comunes con problema y solucion', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        expect(screen.getByText('Errores comunes')).toBeDefined();
        expect(screen.getByText(/No me deja guardar y dice que el asiento no cuadra/)).toBeDefined();
    });

    it('muestra el tip cuando existe', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        expect(screen.getByText(/Tip:/)).toBeDefined();
    });

    it('NO muestra tip si el modulo no lo tiene', () => {
        render(<AyudaModulo moduloId="libroMayor" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        // libroMayor tiene tip, busquemos uno que no lo tenga
        cleanup();
        render(<AyudaModulo moduloId="cotizacion" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        // Cotizacion NO tiene .tip, asi que no aparece "Tip:"
        expect(screen.queryByText(/Tip:/)).toBeNull();
    });
});

describe('AyudaModulo - cierre del modal', () => {
    it('cierra con tecla Escape', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        expect(screen.getByTestId('ayuda-modulo-modal')).toBeDefined();

        fireEvent.keyDown(window, { key: 'Escape' });
        expect(screen.queryByTestId('ayuda-modulo-modal')).toBeNull();
    });

    it('cierra al click en el overlay (fuera del modal)', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        fireEvent.click(screen.getByTestId('ayuda-modulo-overlay'));
        expect(screen.queryByTestId('ayuda-modulo-modal')).toBeNull();
    });

    it('NO cierra al click DENTRO del modal (stopPropagation)', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        const modal = screen.getByTestId('ayuda-modulo-modal');
        fireEvent.click(modal);
        // Sigue abierto
        expect(screen.getByTestId('ayuda-modulo-modal')).toBeDefined();
    });

    it('cierra con boton X', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        fireEvent.click(screen.getByTestId('ayuda-modulo-cerrar'));
        expect(screen.queryByTestId('ayuda-modulo-modal')).toBeNull();
    });

    it('cierra con boton "Entendido"', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        fireEvent.click(screen.getByText('Entendido'));
        expect(screen.queryByTestId('ayuda-modulo-modal')).toBeNull();
    });

    it('bloquea scroll del body cuando abre', () => {
        const original = document.body.style.overflow;
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        expect(document.body.style.overflow).toBe('hidden');

        fireEvent.click(screen.getByText('Entendido'));
        expect(document.body.style.overflow).toBe(original);
    });
});

describe('AyudaModulo - accesibilidad', () => {
    it('el modal tiene role="dialog" y aria-modal', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        const modal = screen.getByTestId('ayuda-modulo-modal');
        expect(modal.getAttribute('role')).toBe('dialog');
        expect(modal.getAttribute('aria-modal')).toBe('true');
        expect(modal.getAttribute('aria-labelledby')).toBeTruthy();
    });

    it('el aria-labelledby apunta al titulo', () => {
        render(<AyudaModulo moduloId="asientoManual" />);
        fireEvent.click(screen.getByTestId('ayuda-modulo-boton'));
        const modal = screen.getByTestId('ayuda-modulo-modal');
        const labelId = modal.getAttribute('aria-labelledby');
        const titulo = document.getElementById(labelId);
        expect(titulo).toBeDefined();
        expect(titulo.textContent).toBe('Asiento Manual');
    });
});

// =====================================================================
// GLOSARIO HELPERS
// =====================================================================

describe('glosario - obtenerModulo', () => {
    it('devuelve el modulo si existe', () => {
        const mod = obtenerModulo('asientoManual');
        expect(mod).toBeDefined();
        expect(mod.titulo).toBe('Asiento Manual');
    });

    it('devuelve null si no existe', () => {
        expect(obtenerModulo('noExisteEsteModulo')).toBeNull();
    });
});

describe('glosario - listarModulos', () => {
    it('devuelve array ordenado por titulo', () => {
        const lista = listarModulos();
        expect(Array.isArray(lista)).toBe(true);
        expect(lista.length).toBeGreaterThan(5);

        // Verificar orden alfabetico
        for (let i = 1; i < lista.length; i++) {
            const a = lista[i - 1].titulo;
            const b = lista[i].titulo;
            expect(a.localeCompare(b, 'es')).toBeLessThanOrEqual(0);
        }
    });
});

describe('glosario - buscarModulos', () => {
    it('sin texto devuelve todos', () => {
        expect(buscarModulos('').length).toBe(listarModulos().length);
        expect(buscarModulos(null).length).toBe(listarModulos().length);
    });

    it('busca por titulo', () => {
        const r = buscarModulos('Asiento');
        expect(r.length).toBeGreaterThan(0);
        expect(r.some((m) => m.id === 'asientoManual')).toBe(true);
    });

    it('busca por resumen', () => {
        const r = buscarModulos('depreciacion');
        expect(r.some((m) => m.id === 'activoFijo')).toBe(true);
    });

    it('busca por concepto', () => {
        const r = buscarModulos('IVA Credito');
        expect(r.some((m) => m.id === 'cierreF29')).toBe(true);
    });

    it('busqueda case-insensitive', () => {
        const r1 = buscarModulos('FACTURA');
        const r2 = buscarModulos('factura');
        expect(r1.length).toBe(r2.length);
    });

    it('busqueda con texto vacio o solo espacios', () => {
        expect(buscarModulos('   ').length).toBe(listarModulos().length);
    });

    it('busqueda sin matches devuelve array vacio', () => {
        expect(buscarModulos('xqyz123nopuedeexistir').length).toBe(0);
    });
});

describe('glosario - estructura de cada modulo', () => {
    it('cada modulo tiene los campos obligatorios', () => {
        Object.values(glosario).forEach((m) => {
            expect(m.id).toBeDefined();
            expect(m.titulo).toBeTruthy();
            expect(m.resumen).toBeTruthy();
            expect(m.queEs).toBeTruthy();
            expect(m.icono).toBeTruthy();
        });
    });

    it('los conceptos tienen termino y definicion', () => {
        Object.values(glosario).forEach((m) => {
            if (m.conceptos) {
                m.conceptos.forEach((c) => {
                    expect(c.termino).toBeTruthy();
                    expect(c.definicion).toBeTruthy();
                });
            }
        });
    });

    it('los errores tienen problema y solucion', () => {
        Object.values(glosario).forEach((m) => {
            if (m.errores) {
                m.errores.forEach((e) => {
                    expect(e.problema).toBeTruthy();
                    expect(e.solucion).toBeTruthy();
                });
            }
        });
    });

    it('comoUsar son arrays de strings no vacios', () => {
        Object.values(glosario).forEach((m) => {
            if (m.comoUsar) {
                m.comoUsar.forEach((paso) => {
                    expect(typeof paso).toBe('string');
                    expect(paso.length).toBeGreaterThan(0);
                });
            }
        });
    });
});
