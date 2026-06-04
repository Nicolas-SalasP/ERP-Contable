import React from 'react';
import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import ErrorBoundary from './ErrorBoundary';

const Bomba = () => {
    throw new Error('boom');
};

afterEach(cleanup);

describe('ErrorBoundary', () => {
    it('renderiza los hijos cuando no hay error', () => {
        render(<ErrorBoundary><div>contenido ok</div></ErrorBoundary>);
        expect(screen.getByText('contenido ok')).toBeDefined();
        expect(screen.queryByTestId('error-boundary')).toBeNull();
    });

    it('muestra el fallback con boton recargar cuando un hijo lanza un error', () => {
        const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

        render(<ErrorBoundary><Bomba /></ErrorBoundary>);

        expect(screen.getByTestId('error-boundary')).toBeDefined();
        expect(screen.getByText(/Algo salió mal/i)).toBeDefined();
        expect(screen.getByRole('button', { name: /Recargar/i })).toBeDefined();

        spy.mockRestore();
    });
});
