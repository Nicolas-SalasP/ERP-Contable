import React from 'react';
import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';

const sentryMock = vi.hoisted(() => ({
    captureException: vi.fn(),
    captureMessage: vi.fn(),
}));
vi.mock('../Configuracion/sentry', () => sentryMock);

import ErrorBoundary from './ErrorBoundary';

const Bomba = () => {
    throw new Error('boom');
};

afterEach(() => {
    cleanup();
    sentryMock.captureException.mockClear();
});

describe('ErrorBoundary', () => {
    it('renderiza los hijos cuando no hay error', () => {
        render(<ErrorBoundary><div>contenido ok</div></ErrorBoundary>);
        expect(screen.getByText('contenido ok')).toBeDefined();
        expect(screen.queryByTestId('error-boundary')).toBeNull();
    });

    it('muestra el fallback con botones cuando un hijo lanza un error', () => {
        const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

        render(<ErrorBoundary><Bomba /></ErrorBoundary>);

        expect(screen.getByTestId('error-boundary')).toBeDefined();
        expect(screen.getByText(/Algo salió mal/i)).toBeDefined();
        expect(screen.getByRole('button', { name: /Reintentar/i })).toBeDefined();
        expect(screen.getByRole('button', { name: /Recargar página/i })).toBeDefined();

        spy.mockRestore();
    });

    it('muestra el mensaje personalizado del prop mensaje', () => {
        const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

        render(<ErrorBoundary mensaje="Error específico del módulo"><Bomba /></ErrorBoundary>);

        expect(screen.getByText('Error específico del módulo')).toBeDefined();

        spy.mockRestore();
    });

    it('reporta el error a captureException cuando un hijo lanza', () => {
        const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

        render(<ErrorBoundary><Bomba /></ErrorBoundary>);

        expect(sentryMock.captureException).toHaveBeenCalledWith(
            expect.any(Error),
            expect.objectContaining({ extra: expect.objectContaining({ componentStack: expect.anything() }) }),
        );

        spy.mockRestore();
    });

    it('reintentar resetea el boundary y vuelve a renderizar los hijos', () => {
        let debeThrow = true;
        const BombaControlada = () => {
            if (debeThrow) throw new Error('error controlado');
            return <div>recuperado</div>;
        };

        const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

        render(<ErrorBoundary><BombaControlada /></ErrorBoundary>);

        expect(screen.getByTestId('error-boundary')).toBeDefined();

        debeThrow = false;
        fireEvent.click(screen.getByRole('button', { name: /Reintentar/i }));

        expect(screen.getByText('recuperado')).toBeDefined();
        expect(screen.queryByTestId('error-boundary')).toBeNull();

        spy.mockRestore();
    });

    it('recargar página llama window.location.reload', () => {
        const spy = vi.spyOn(console, 'error').mockImplementation(() => {});
        const reloadSpy = vi.fn();
        Object.defineProperty(window, 'location', { writable: true, value: { reload: reloadSpy } });

        render(<ErrorBoundary><Bomba /></ErrorBoundary>);
        fireEvent.click(screen.getByRole('button', { name: /Recargar página/i }));

        expect(reloadSpy).toHaveBeenCalled();
        spy.mockRestore();
    });
});
