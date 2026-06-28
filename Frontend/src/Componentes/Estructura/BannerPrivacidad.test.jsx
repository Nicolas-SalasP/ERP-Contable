import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import BannerPrivacidad from './BannerPrivacidad';

vi.mock('../../Modulos/Privacidad/privacidadApi', () => ({
    default: {
        miConsentimiento: vi.fn(),
        obtenerPolitica: vi.fn(),
        aceptar: vi.fn(),
        revocar: vi.fn(),
    },
}));

import privacidadApi from '../../Modulos/Privacidad/privacidadApi';

afterEach(cleanup);

describe('BannerPrivacidad', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('muestra el banner cuando el consentimiento no ha sido aceptado', async () => {
        privacidadApi.miConsentimiento.mockResolvedValue({ data: { aceptada: false, version: null } });
        privacidadApi.obtenerPolitica.mockResolvedValue({ data: { titulo: 'Política de Privacidad', contenido: 'Contenido.' } });

        render(<BannerPrivacidad />);

        await waitFor(() => {
            expect(screen.getByTestId('banner-privacidad')).toBeDefined();
        });
    });

    it('no renderiza el banner cuando el consentimiento ya fue aceptado', async () => {
        privacidadApi.miConsentimiento.mockResolvedValue({ data: { aceptada: true, version: '1.0' } });
        privacidadApi.obtenerPolitica.mockResolvedValue({ data: { titulo: 'Política', contenido: 'X' } });

        render(<BannerPrivacidad />);

        await waitFor(() => {}, { timeout: 100 });

        expect(screen.queryByTestId('banner-privacidad')).toBeNull();
    });

    it('oculta el banner al hacer clic en Aceptar', async () => {
        privacidadApi.miConsentimiento.mockResolvedValue({ data: { aceptada: false, version: null } });
        privacidadApi.obtenerPolitica.mockResolvedValue({ data: { titulo: 'Política', contenido: 'X' } });
        privacidadApi.aceptar.mockResolvedValue({ success: true });

        render(<BannerPrivacidad />);

        await waitFor(() => {
            expect(screen.getByTestId('banner-privacidad')).toBeDefined();
        });

        fireEvent.click(screen.getByTestId('btn-aceptar-privacidad'));

        await waitFor(() => {
            expect(screen.queryByTestId('banner-privacidad')).toBeNull();
        });

        expect(privacidadApi.aceptar).toHaveBeenCalledOnce();
    });

    it('no hay checkbox pre-seleccionado en el banner', async () => {
        privacidadApi.miConsentimiento.mockResolvedValue({ data: { aceptada: false, version: null } });
        privacidadApi.obtenerPolitica.mockResolvedValue({ data: { titulo: 'Política', contenido: 'X' } });

        render(<BannerPrivacidad />);

        await waitFor(() => {
            expect(screen.getByTestId('banner-privacidad')).toBeDefined();
        });

        const checkboxes = screen.queryAllByRole('checkbox');
        expect(checkboxes.length).toBe(0);
    });

    it('no renderiza nada cuando la API falla (resilience)', async () => {
        privacidadApi.miConsentimiento.mockRejectedValue(new Error('Network error'));
        privacidadApi.obtenerPolitica.mockRejectedValue(new Error('Network error'));

        render(<BannerPrivacidad />);

        await waitFor(() => {}, { timeout: 200 });

        expect(screen.queryByTestId('banner-privacidad')).toBeNull();
    });

    it('ocultar con Mas tarde no llama a la API', async () => {
        privacidadApi.miConsentimiento.mockResolvedValue({ data: { aceptada: false, version: null } });
        privacidadApi.obtenerPolitica.mockResolvedValue({ data: { titulo: 'Política', contenido: 'X' } });

        render(<BannerPrivacidad />);

        await waitFor(() => {
            expect(screen.getByTestId('banner-privacidad')).toBeDefined();
        });

        fireEvent.click(screen.getByTestId('btn-mas-tarde'));

        expect(screen.queryByTestId('banner-privacidad')).toBeNull();
        expect(privacidadApi.aceptar).not.toHaveBeenCalled();
    });

    it('expandir política muestra el contenido completo', async () => {
        privacidadApi.miConsentimiento.mockResolvedValue({ data: { aceptada: false, version: null } });
        privacidadApi.obtenerPolitica.mockResolvedValue({
            data: { titulo: 'Política de Privacidad', contenido: 'Texto completo de la política.' },
        });

        render(<BannerPrivacidad />);

        await waitFor(() => {
            expect(screen.getByTestId('banner-privacidad')).toBeDefined();
        });

        expect(screen.queryByText('Texto completo de la política.')).toBeNull();

        fireEvent.click(screen.getByText('Leer política completa'));
        expect(screen.getByText('Texto completo de la política.')).toBeTruthy();

        fireEvent.click(screen.getByText('Ocultar'));
        expect(screen.queryByText('Texto completo de la política.')).toBeNull();
    });
});
