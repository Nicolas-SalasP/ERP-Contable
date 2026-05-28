import React from 'react';
import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn() },
}));

import Swal from 'sweetalert2';
import TarjetaCertificadoActivo from './TarjetaCertificadoActivo';

afterEach(cleanup);

const certEnDias = (dias) => {
    const fin = new Date(Date.now() + dias * 24 * 60 * 60 * 1000).toISOString();
    return {
        id: 1,
        subject_rut: '76086428-5',
        subject_common_name: 'Empresa Test',
        issuer_common_name: 'E-CertChile',
        valido_desde: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString(),
        valido_hasta: fin,
        fingerprint_sha256: 'a'.repeat(64),
        estado: 'activo',
    };
};

describe('TarjetaCertificadoActivo', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza placeholder cuando certificado=null', () => {
        render(<TarjetaCertificadoActivo certificado={null} onRevocar={vi.fn()} />);
        expect(screen.getByTestId('tarjeta-cert-placeholder')).toBeDefined();
        expect(screen.getByText(/Sin certificado digital/i)).toBeDefined();
    });

    it('badge VIGENTE (verde) si dias para vencer > 60', () => {
        render(<TarjetaCertificadoActivo certificado={certEnDias(120)} onRevocar={vi.fn()} />);
        const badge = screen.getByTestId('badge-vigencia');
        expect(badge.textContent).toMatch(/Vigente/i);
        expect(badge.className).toMatch(/emerald/);
    });

    it('badge ROJO si dias para vencer entre 0 y 7', () => {
        render(<TarjetaCertificadoActivo certificado={certEnDias(3)} onRevocar={vi.fn()} />);
        const badge = screen.getByTestId('badge-vigencia');
        expect(badge.textContent).toMatch(/Vence en/i);
        expect(badge.className).toMatch(/rose/);
    });

    it('badge GRIS "Vencido" si certificado expiro', () => {
        render(<TarjetaCertificadoActivo certificado={certEnDias(-5)} onRevocar={vi.fn()} />);
        const badge = screen.getByTestId('badge-vigencia');
        expect(badge.textContent).toMatch(/Vencido/i);
        expect(badge.className).toMatch(/slate/);
    });

    it('boton revocar pide confirmacion Swal antes de invocar onRevocar', async () => {
        Swal.fire.mockResolvedValue({ isConfirmed: true });
        const onRevocar = vi.fn();
        const cert = certEnDias(90);

        render(<TarjetaCertificadoActivo certificado={cert} onRevocar={onRevocar} />);
        fireEvent.click(screen.getByTestId('boton-revocar'));

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalled();
        });

        await waitFor(() => {
            expect(onRevocar).toHaveBeenCalledTimes(1);
        });
        expect(onRevocar.mock.calls[0][0]).toBe(cert);
    });

    it('si el usuario cancela el Swal, onRevocar NO se invoca', async () => {
        Swal.fire.mockResolvedValue({ isConfirmed: false });
        const onRevocar = vi.fn();

        render(<TarjetaCertificadoActivo certificado={certEnDias(90)} onRevocar={onRevocar} />);
        fireEvent.click(screen.getByTestId('boton-revocar'));

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalled();
        });
        expect(onRevocar).not.toHaveBeenCalled();
    });
});
