import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, cleanup, waitFor } from '@testing-library/react';

const mockNavigate = vi.fn();
let mockSearchParams = new URLSearchParams();

vi.mock('react-router-dom', () => ({
    useNavigate: () => mockNavigate,
    useSearchParams: () => [mockSearchParams],
}));

const mockTokenLogin = vi.fn();
const mockMarkTokenIssued = vi.fn();

vi.mock('../../Configuracion/api', () => ({
    api: {
        auth: {
            tokenLogin: (...args) => mockTokenLogin(...args),
        },
    },
    markTokenIssued: (...args) => mockMarkTokenIssued(...args),
}));

import SsoCallback from './SsoCallback';

// Remplaza window.location con objeto controlable antes de cada test
beforeEach(() => {
    Object.defineProperty(window, 'location', {
        writable: true,
        value: { replace: vi.fn() },
    });
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
    sessionStorage.clear();
    mockSearchParams = new URLSearchParams();
});

describe('SsoCallback', () => {
    it('muestra spinner de carga cuando hay sso_token y se está procesando', async () => {
        mockSearchParams = new URLSearchParams('sso_token=abc123');
        mockTokenLogin.mockReturnValue(new Promise(() => {}));

        render(<SsoCallback />);
        expect(screen.getByText(/Iniciando sesión automáticamente/i)).toBeTruthy();
    });

    it('muestra error cuando no hay sso_token en la URL', async () => {
        mockSearchParams = new URLSearchParams('');
        render(<SsoCallback />);
        await waitFor(() => {
            expect(screen.getByText(/Token SSO no encontrado en la URL/i)).toBeTruthy();
        });
    });

    it('muestra botón "Ir al login" en estado de error', async () => {
        mockSearchParams = new URLSearchParams('');
        render(<SsoCallback />);
        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Ir al login/i })).toBeTruthy();
        });
    });

    it('muestra error cuando tokenLogin devuelve success=false', async () => {
        mockSearchParams = new URLSearchParams('sso_token=invalid-token');
        mockTokenLogin.mockResolvedValue({ success: false, message: 'Token expirado' });

        render(<SsoCallback />);
        await waitFor(() => {
            expect(screen.getByText(/Token expirado/i)).toBeTruthy();
        });
    });

    it('muestra error cuando tokenLogin devuelve token vacío', async () => {
        mockSearchParams = new URLSearchParams('sso_token=token123');
        mockTokenLogin.mockResolvedValue({ success: true, token: null });

        render(<SsoCallback />);
        await waitFor(() => {
            expect(screen.getByText(/Token inválido o expirado/i)).toBeTruthy();
        });
    });

    it('muestra error cuando no se recibe información del usuario', async () => {
        mockSearchParams = new URLSearchParams('sso_token=token123');
        mockTokenLogin.mockResolvedValue({ success: true, token: 'jwt-token', user: null });

        render(<SsoCallback />);
        await waitFor(() => {
            expect(screen.getByText(/No se recibió información del usuario/i)).toBeTruthy();
        });
    });

    it('muestra error de conexión cuando tokenLogin lanza excepción', async () => {
        mockSearchParams = new URLSearchParams('sso_token=token123');
        mockTokenLogin.mockRejectedValue(new Error('Network error'));

        render(<SsoCallback />);
        await waitFor(() => {
            expect(screen.getByText(/No se pudo completar el acceso automático/i)).toBeTruthy();
        });
    });

    it('guarda el token en sessionStorage cuando login es exitoso', async () => {
        mockSearchParams = new URLSearchParams('sso_token=valid-token');
        mockTokenLogin.mockResolvedValue({
            success: true,
            token: 'jwt-token-abc',
            user: { id: 1, nombre: 'Test User' },
            issued_at: '2025-01-01T00:00:00Z',
        });

        render(<SsoCallback />);

        await waitFor(() => {
            expect(sessionStorage.getItem('erp_token')).toBe('jwt-token-abc');
        });
    });

    it('llama markTokenIssued con el issued_at cuando login es exitoso', async () => {
        mockSearchParams = new URLSearchParams('sso_token=valid-token');
        const issuedAt = '2025-01-01T00:00:00Z';
        mockTokenLogin.mockResolvedValue({
            success: true,
            token: 'jwt-token-abc',
            user: { id: 1, nombre: 'Test User' },
            issued_at: issuedAt,
        });

        render(<SsoCallback />);

        await waitFor(() => {
            expect(mockMarkTokenIssued).toHaveBeenCalledWith(issuedAt);
        });
    });

    it('redirige al inicio cuando login es exitoso', async () => {
        mockSearchParams = new URLSearchParams('sso_token=valid-token');
        mockTokenLogin.mockResolvedValue({
            success: true,
            token: 'jwt-token-abc',
            user: { id: 1, nombre: 'Test User' },
            issued_at: '2025-01-01T00:00:00Z',
        });

        render(<SsoCallback />);

        await waitFor(() => {
            expect(window.location.replace).toHaveBeenCalledWith('/');
        });
    });
});
