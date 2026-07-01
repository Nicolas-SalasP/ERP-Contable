import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, act } from '@testing-library/react';

// jsdom no implementa matchMedia — mock mínimo necesario para TemaContext
const matchMediaMock = vi.fn().mockImplementation((query) => ({
    matches: false,
    media: query,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
}));
Object.defineProperty(window, 'matchMedia', { writable: true, value: matchMediaMock });

import { TemaProvider, useTema } from './TemaContext';

afterEach(cleanup);

const ConsumidorTema = () => {
    const { tema, setTema } = useTema();
    return (
        <div>
            <span data-testid="tema-actual">{tema}</span>
            <button onClick={() => setTema('gris-oscuro')}>oscuro</button>
            <button onClick={() => setTema('claro')}>claro</button>
            <button onClick={() => setTema('sistema')}>sistema</button>
        </div>
    );
};

describe('TemaContext — TemaProvider', () => {
    beforeEach(() => {
        localStorage.clear();
        document.documentElement.classList.remove('dark');
        document.documentElement.style.colorScheme = '';
    });

    it('renderiza hijos correctamente', () => {
        render(
            <TemaProvider>
                <span>contenido</span>
            </TemaProvider>
        );
        expect(screen.getByText('contenido')).toBeTruthy();
    });

    it('tema inicial es "sistema" cuando no hay localStorage', () => {
        render(
            <TemaProvider>
                <ConsumidorTema />
            </TemaProvider>
        );
        expect(screen.getByTestId('tema-actual').textContent).toBe('sistema');
    });

    it('lee tema guardado en localStorage', () => {
        localStorage.setItem('tenri_tema', 'claro');
        render(
            <TemaProvider>
                <ConsumidorTema />
            </TemaProvider>
        );
        expect(screen.getByTestId('tema-actual').textContent).toBe('claro');
    });

    it('setTema("gris-oscuro") agrega clase dark al documentElement', async () => {
        render(<TemaProvider><ConsumidorTema /></TemaProvider>);
        await act(async () => { fireEvent.click(screen.getByText('oscuro')); });
        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(document.documentElement.style.colorScheme).toBe('dark');
    });

    it('setTema("claro") remueve clase dark del documentElement', async () => {
        document.documentElement.classList.add('dark');
        render(<TemaProvider><ConsumidorTema /></TemaProvider>);
        await act(async () => { fireEvent.click(screen.getByText('claro')); });
        expect(document.documentElement.classList.contains('dark')).toBe(false);
        expect(document.documentElement.style.colorScheme).toBe('light');
    });

    it('setTema guarda el valor en localStorage', async () => {
        render(<TemaProvider><ConsumidorTema /></TemaProvider>);
        await act(async () => { fireEvent.click(screen.getByText('claro')); });
        expect(localStorage.getItem('tenri_tema')).toBe('claro');
    });

    it('setTema("sistema") actualiza el estado visible', async () => {
        render(<TemaProvider><ConsumidorTema /></TemaProvider>);
        await act(async () => { fireEvent.click(screen.getByText('oscuro')); });
        await act(async () => { fireEvent.click(screen.getByText('sistema')); });
        expect(screen.getByTestId('tema-actual').textContent).toBe('sistema');
    });

    it('provee la función setTema a los hijos', () => {
        render(<TemaProvider><ConsumidorTema /></TemaProvider>);
        expect(screen.getByText('oscuro')).toBeTruthy();
        expect(screen.getByText('claro')).toBeTruthy();
    });
});

describe('useTema — fuera del provider', () => {
    it('lanza error si se usa fuera de TemaProvider', () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const ComponenteHuerfano = () => { useTema(); return null; };
        expect(() => render(<ComponenteHuerfano />)).toThrow('useTema debe usarse dentro de TemaProvider');
        errorSpy.mockRestore();
    });
});
