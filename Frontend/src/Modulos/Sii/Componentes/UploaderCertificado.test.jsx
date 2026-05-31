import React from 'react';
import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../Servicios/siiApi', () => ({
    default: {
        certificado: {
            subir: vi.fn(),
        },
    },
}));

import siiApi from '../Servicios/siiApi';
import UploaderCertificado from './UploaderCertificado';

afterEach(cleanup);

const archivoPfx = (nombre = 'cert.pfx', sizeBytes = 1024) => {
    const file = new File(['x'.repeat(sizeBytes)], nombre, { type: 'application/x-pkcs12' });
    return file;
};

describe('UploaderCertificado', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('rechaza archivo con extension .txt', () => {
        render(<UploaderCertificado onSubidoExitosamente={vi.fn()} />);

        const input = screen.getByTestId('cert-archivo');
        const archivoMalo = new File(['contenido'], 'notas.txt', { type: 'text/plain' });
        fireEvent.change(input, { target: { files: [archivoMalo] } });

        expect(screen.getByTestId('cert-archivo-error').textContent).toMatch(/\.pfx o \.p12/i);
        expect(screen.getByTestId('cert-submit').disabled).toBe(true);
    });

    it('rechaza archivo mayor a 50 KB', () => {
        render(<UploaderCertificado onSubidoExitosamente={vi.fn()} />);

        const input = screen.getByTestId('cert-archivo');
        const grande = archivoPfx('grande.pfx', 60 * 1024); // 60 KB > 50 KB
        fireEvent.change(input, { target: { files: [grande] } });

        expect(screen.getByTestId('cert-archivo-error').textContent).toMatch(/50 KB/);
        expect(screen.getByTestId('cert-submit').disabled).toBe(true);
    });

    it('boton subir esta deshabilitado sin archivo o sin password', () => {
        render(<UploaderCertificado onSubidoExitosamente={vi.fn()} />);

        const boton = screen.getByTestId('cert-submit');
        expect(boton.disabled).toBe(true);

        // Solo archivo, sin password → sigue deshabilitado
        fireEvent.change(screen.getByTestId('cert-archivo'), { target: { files: [archivoPfx()] } });
        expect(boton.disabled).toBe(true);

        // Agregar password → se habilita
        fireEvent.change(screen.getByTestId('cert-password'), { target: { value: 'algo' } });
        expect(boton.disabled).toBe(false);
    });

    it('submit valido llama siiApi.certificado.subir y onSubidoExitosamente', async () => {
        const onSubidoExitosamente = vi.fn();
        siiApi.certificado.subir.mockResolvedValue({ id: 1, estado: 'activo' });

        render(<UploaderCertificado onSubidoExitosamente={onSubidoExitosamente} />);

        const file = archivoPfx('mi-cert.pfx', 2048);
        fireEvent.change(screen.getByTestId('cert-archivo'), { target: { files: [file] } });
        fireEvent.change(screen.getByTestId('cert-password'), { target: { value: 'mi_pwd' } });
        fireEvent.click(screen.getByTestId('cert-submit'));

        await waitFor(() => {
            expect(siiApi.certificado.subir).toHaveBeenCalledTimes(1);
        });

        const args = siiApi.certificado.subir.mock.calls[0];
        expect(args[0]).toBe(file);
        expect(args[1]).toBe('mi_pwd');

        await waitFor(() => {
            expect(onSubidoExitosamente).toHaveBeenCalledTimes(1);
        });
        expect(onSubidoExitosamente.mock.calls[0][0]).toEqual({ id: 1, estado: 'activo' });
    });
});
