import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import BannerSuscripcion from './BannerSuscripcion';

const authMock = vi.hoisted(() => ({ value: { user: {} } }));
vi.mock('../Contextos/AuthContext', () => ({
    useAuth: () => authMock.value,
}));

afterEach(cleanup);

describe('BannerSuscripcion', () => {
    it('en grace muestra banner ambar con los dias restantes', () => {
        const ends = new Date(Date.now() + 5 * 86400000).toISOString();
        authMock.value = { user: { subscription_status: 'grace', subscription_ends_at: ends } };

        render(<BannerSuscripcion />);

        expect(screen.getByTestId('banner-suscripcion').className).toContain('amber');
        expect(screen.getByText(/vence en 5 días/i)).toBeDefined();
    });

    it('en read_only muestra banner rojo de solo lectura', () => {
        authMock.value = { user: { subscription_status: 'read_only', subscription_ends_at: null } };

        render(<BannerSuscripcion />);

        expect(screen.getByTestId('banner-suscripcion').className).toContain('red');
        expect(screen.getByText(/Solo lectura/i)).toBeDefined();
    });

    it('con suscripcion activa no renderiza nada', () => {
        authMock.value = { user: { subscription_status: 'active' } };

        render(<BannerSuscripcion />);

        expect(screen.queryByTestId('banner-suscripcion')).toBeNull();
    });
});
