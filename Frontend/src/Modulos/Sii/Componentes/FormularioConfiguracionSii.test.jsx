import React from 'react';
import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

// Mockear Swal para suprimir el warning de produccion durante submits.
vi.mock('sweetalert2', () => ({
    default: {
        fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
    },
}));

import FormularioConfiguracionSii from './FormularioConfiguracionSii';

afterEach(cleanup);

const configBase = {
    giro_emisor: 'Giro original',
    codigo_actividad_sii: 471910,
    comuna: 'Santiago',
    ciudad: 'Santiago',
    resolucion_sii_numero: 80,
    resolucion_sii_fecha: '2024-01-15',
    ambiente_sii: 'certificacion',
    email_intercambio_sii: 'inter@empresa.cl',
    rut_representante_legal: '12345678-5',
};

describe('FormularioConfiguracionSii', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza los 9 campos del formulario', () => {
        render(<FormularioConfiguracionSii configuracion={configBase} onSubmit={vi.fn()} guardando={false} />);

        expect(screen.getByLabelText(/Giro/i)).toBeDefined();
        expect(screen.getByLabelText(/Codigo Actividad SII/i)).toBeDefined();
        expect(screen.getByLabelText(/^Comuna$/i)).toBeDefined();
        expect(screen.getByLabelText(/^Ciudad$/i)).toBeDefined();
        expect(screen.getByLabelText(/Numero de Resolucion/i)).toBeDefined();
        expect(screen.getByLabelText(/Fecha de Resolucion/i)).toBeDefined();
        expect(screen.getByLabelText(/Ambiente SII/i)).toBeDefined();
        expect(screen.getByLabelText(/Email de Intercambio/i)).toBeDefined();
        expect(screen.getByLabelText(/RUT Representante Legal/i)).toBeDefined();
    });

    it('bloquea submit si email es malformado', async () => {
        const onSubmit = vi.fn();
        render(<FormularioConfiguracionSii configuracion={configBase} onSubmit={onSubmit} guardando={false} />);

        fireEvent.change(screen.getByLabelText(/Email de Intercambio/i), {
            target: { value: 'no-es-email' },
        });
        fireEvent.submit(screen.getByTestId('form-sii-configuracion'));

        await waitFor(() => {
            expect(screen.getByText(/Formato de email invalido/i)).toBeDefined();
        });
        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('bloquea submit si el RUT representante legal tiene DV incorrecto', async () => {
        const onSubmit = vi.fn();
        render(<FormularioConfiguracionSii configuracion={configBase} onSubmit={onSubmit} guardando={false} />);

        fireEvent.change(screen.getByLabelText(/RUT Representante Legal/i), {
            target: { value: '11111111-9' }, // DV correcto es 1, no 9
        });
        fireEvent.submit(screen.getByTestId('form-sii-configuracion'));

        await waitFor(() => {
            expect(screen.getByText(/RUT chileno invalido/i)).toBeDefined();
        });
        expect(onSubmit).not.toHaveBeenCalled();
    });

    it('al cambiar ambiente a produccion pide confirmacion Swal antes de submit', async () => {
        const Swal = (await import('sweetalert2')).default;
        const onSubmit = vi.fn();

        render(<FormularioConfiguracionSii configuracion={configBase} onSubmit={onSubmit} guardando={false} />);

        fireEvent.change(screen.getByLabelText(/Ambiente SII/i), { target: { value: 'produccion' } });
        fireEvent.submit(screen.getByTestId('form-sii-configuracion'));

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalled();
        });

        await waitFor(() => {
            expect(onSubmit).toHaveBeenCalledTimes(1);
        });

        const llamada = Swal.fire.mock.calls[0][0];
        expect(llamada.icon).toBe('warning');
        expect(String(llamada.html ?? llamada.title ?? '')).toMatch(/produccion/i);
    });

    it('submit valido llama onSubmit con el payload normalizado', async () => {
        const onSubmit = vi.fn();
        render(<FormularioConfiguracionSii configuracion={configBase} onSubmit={onSubmit} guardando={false} />);

        // Cambiar giro y submit (ambiente sigue siendo certificacion → no Swal de produccion).
        fireEvent.change(screen.getByLabelText(/Giro/i), { target: { value: 'Giro actualizado' } });
        fireEvent.submit(screen.getByTestId('form-sii-configuracion'));

        await waitFor(() => {
            expect(onSubmit).toHaveBeenCalledTimes(1);
        });

        const payload = onSubmit.mock.calls[0][0];
        expect(payload.giro_emisor).toBe('Giro actualizado');
        expect(payload.ambiente_sii).toBe('certificacion');
        expect(payload.codigo_actividad_sii).toBe(471910);
        // empresa_id NO debe estar en el payload (mass-assignment prevention).
        expect(payload.empresa_id).toBeUndefined();
    });
});
