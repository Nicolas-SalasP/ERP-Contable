import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, act } from '@testing-library/react';

// jsdom no implementa matchMedia — mock necesario para TemaContext
const matchMediaMock = vi.fn().mockImplementation((query) => ({
    matches: false,
    media: query,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    addListener: vi.fn(),
    removeListener: vi.fn(),
}));
Object.defineProperty(window, 'matchMedia', { writable: true, value: matchMediaMock });

// useTema re-exporta desde TemaContext; el proveedor también viene de ahí
import { useTema, TemaProvider } from '../Contextos/TemaContext';

afterEach(() => {
    cleanup();
    localStorage.clear();
    document.documentElement.classList.remove('dark');
    document.documentElement.style.colorScheme = '';
});

const ConsumidorTema = () => {
    const { tema, setTema } = useTema();
    return (
        <div>
            <span data-testid="tema">{tema}</span>
            <button onClick={() => setTema('gris-oscuro')}>oscuro</button>
            <button onClick={() => setTema('claro')}>claro</button>
            <button onClick={() => setTema('sistema')}>sistema</button>
        </div>
    );
};

const renderConProvider = (temaInicial) => {
    if (temaInicial) localStorage.setItem('tenri_tema', temaInicial);
    return render(
        <TemaProvider>
            <ConsumidorTema />
        </TemaProvider>
    );
};

describe('useTema — inicialización', () => {
    it('inicia con tema "sistema" cuando localStorage esta vacio', () => {
        renderConProvider();
        expect(screen.getByTestId('tema').textContent).toBe('sistema');
    });

    it('inicia con el tema guardado en localStorage', () => {
        renderConProvider('claro');
        expect(screen.getByTestId('tema').textContent).toBe('claro');
    });

    it('inicia con "gris-oscuro" si ese tema estaba guardado', () => {
        renderConProvider('gris-oscuro');
        expect(screen.getByTestId('tema').textContent).toBe('gris-oscuro');
    });
});

describe('useTema — setTema y clases del DOM', () => {
    it('setTema("gris-oscuro") agrega la clase dark a document.documentElement', async () => {
        renderConProvider();
        await act(async () => { fireEvent.click(screen.getByText('oscuro')); });
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('setTema("claro") remueve la clase dark de document.documentElement', async () => {
        document.documentElement.classList.add('dark');
        renderConProvider();
        await act(async () => { fireEvent.click(screen.getByText('claro')); });
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('setTema("gris-oscuro") actualiza el estado reactivo visible', async () => {
        renderConProvider();
        await act(async () => { fireEvent.click(screen.getByText('oscuro')); });
        expect(screen.getByTestId('tema').textContent).toBe('gris-oscuro');
    });

    it('setTema("claro") actualiza el estado reactivo visible', async () => {
        renderConProvider('gris-oscuro');
        await act(async () => { fireEvent.click(screen.getByText('claro')); });
        expect(screen.getByTestId('tema').textContent).toBe('claro');
    });

    it('setTema persiste el valor en localStorage', async () => {
        renderConProvider();
        await act(async () => { fireEvent.click(screen.getByText('claro')); });
        expect(localStorage.getItem('tenri_tema')).toBe('claro');
    });

    it('setTema("sistema") vuelve a leer preferencia del sistema', async () => {
        renderConProvider('gris-oscuro');
        await act(async () => { fireEvent.click(screen.getByText('oscuro')); });
        await act(async () => { fireEvent.click(screen.getByText('sistema')); });
        expect(screen.getByTestId('tema').textContent).toBe('sistema');
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
