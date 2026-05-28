import React from 'react';
import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../Servicios/siiApi', () => ({
    default: {
        configuracion: {
            obtener: vi.fn(),
            actualizar: vi.fn(),
        },
    },
}));

import Swal from 'sweetalert2';
import siiApi from '../Servicios/siiApi';
import ConfiguracionSii from './ConfiguracionSii';

afterEach(cleanup);

const configEjemplo = {
    giro_emisor: 'Original',
    codigo_actividad_sii: 471910,
    comuna: 'Santiago',
    ciudad: 'Santiago',
    resolucion_sii_numero: 80,
    resolucion_sii_fecha: '2024-01-15',
    ambiente_sii: 'certificacion',
    email_intercambio_sii: 'a@b.cl',
    rut_representante_legal: '12345678-5',
};

describe('ConfiguracionSii (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('render inicial llama siiApi.configuracion.obtener una vez', async () => {
        siiApi.configuracion.obtener.mockResolvedValue(configEjemplo);

        render(<ConfiguracionSii />);

        await waitFor(() => {
            expect(siiApi.configuracion.obtener).toHaveBeenCalledTimes(1);
        });
    });

    it('tras actualizar exitosamente muestra Swal de exito (toast)', async () => {
        siiApi.configuracion.obtener.mockResolvedValue(configEjemplo);
        siiApi.configuracion.actualizar.mockResolvedValue({ ...configEjemplo, giro_emisor: 'Nuevo' });

        render(<ConfiguracionSii />);

        await waitFor(() => {
            expect(siiApi.configuracion.obtener).toHaveBeenCalled();
        });

        // El form debe estar renderizado tras la carga.
        await waitFor(() => {
            expect(screen.getByTestId('form-sii-configuracion')).toBeDefined();
        });

        fireEvent.submit(screen.getByTestId('form-sii-configuracion'));

        await waitFor(() => {
            expect(siiApi.configuracion.actualizar).toHaveBeenCalledTimes(1);
        });

        await waitFor(() => {
            // La ultima invocacion a Swal debe ser el toast de exito.
            const llamadas = Swal.fire.mock.calls.map((c) => c[0]);
            const tieneExito = llamadas.some((c) => c?.icon === 'success' && c?.toast === true);
            expect(tieneExito).toBe(true);
        });
    });

    it('si el backend devuelve null (error 422), NO se muestra toast de exito', async () => {
        siiApi.configuracion.obtener.mockResolvedValue(configEjemplo);
        siiApi.configuracion.actualizar.mockResolvedValue(null);

        render(<ConfiguracionSii />);

        await waitFor(() => {
            expect(screen.getByTestId('form-sii-configuracion')).toBeDefined();
        });

        fireEvent.submit(screen.getByTestId('form-sii-configuracion'));

        await waitFor(() => {
            expect(siiApi.configuracion.actualizar).toHaveBeenCalled();
        });

        // Ningun Swal con icon: 'success'+toast:true debe haberse invocado.
        const llamadas = Swal.fire.mock.calls.map((c) => c[0]);
        const tieneExito = llamadas.some((c) => c?.icon === 'success' && c?.toast === true);
        expect(tieneExito).toBe(false);
    });
});
