import React from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import BarraLateral from './BarraLateral';

const logoutMock = vi.fn();

vi.mock('../../Contextos/AuthContext', () => ({
    useAuth: () => ({
        user: { nombre: 'Usuario Demo' },
        logout: logoutMock,
    }),
}));

vi.mock('../../Contextos/Permisos', () => ({
    usePermisos: () => ({
        tieneAlgunPermiso: () => true,
    }),
}));

const mockMatchMedia = () => {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: vi.fn().mockImplementation((query) => ({
            matches: false,
            media: query,
            onchange: null,
            addListener: vi.fn(),
            removeListener: vi.fn(),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
            dispatchEvent: vi.fn(),
        })),
    });
};

const renderSidebar = (initialPath = '/') => render(
    <MemoryRouter initialEntries={[initialPath]}>
        <BarraLateral isOpen={true} toggleSidebar={vi.fn()} closeSidebar={vi.fn()} />
    </MemoryRouter>
);

describe('BarraLateral', () => {
    beforeEach(() => {
        cleanup();
        vi.clearAllMocks();
        mockMatchMedia();
    });

    afterEach(() => {
        cleanup();
    });

    it('mantiene Dashboard como enlace directo sin convertirlo en submenu', () => {
        renderSidebar('/');

        const dashboardLink = screen.getByRole('link', { name: /^dashboard$/i });

        expect(dashboardLink.getAttribute('href')).toBe('/');
    });

    it('permite abrir y cerrar manualmente un grupo con submenu', () => {
        renderSidebar('/');

        const inventarioButton = screen.getByRole('button', { name: /^inventario$/i });

        expect(inventarioButton.getAttribute('aria-expanded')).toBe('false');

        fireEvent.click(inventarioButton);
        expect(inventarioButton.getAttribute('aria-expanded')).toBe('true');

        fireEvent.click(inventarioButton);
        expect(inventarioButton.getAttribute('aria-expanded')).toBe('false');
    });

    it('mantiene Glosario como enlace directo disponible para navegacion de ayuda', () => {
        renderSidebar('/');

        const glosarioLink = screen
            .getAllByRole('link', { name: /ayuda y glosario/i })
            .find((link) => link.getAttribute('href') === '/glosario');

        expect(glosarioLink).toBeTruthy();
    });
});
