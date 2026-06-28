import React from 'react';
import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../../../Componentes/EstadoCarga', () => ({
    default: ({ cargando, mensajeCargando, children }) => {
        if (cargando) return <div>{mensajeCargando}</div>;
        return <div>{children}</div>;
    },
}));

vi.mock('../Componentes/TarjetaCertificadoActivo', () => ({
    default: ({ certificado, onRevocar }) => (
        <div data-testid="tarjeta-certificado">
            {certificado ? <span>cert-activo</span> : <span>sin-certificado</span>}
            {onRevocar && <button onClick={() => onRevocar(certificado)}>Revocar</button>}
        </div>
    ),
}));

vi.mock('../Componentes/UploaderCertificado', () => ({
    default: ({ onSubidoExitosamente }) => (
        <div data-testid="uploader-certificado">
            <button onClick={onSubidoExitosamente}>Subir certificado</button>
        </div>
    ),
}));

vi.mock('../Servicios/siiApi', () => ({
    default: {
        certificado: {
            obtener: vi.fn(),
            revocar: vi.fn(),
        },
    },
}));

import CertificadoSii from './CertificadoSii';
import siiApi from '../Servicios/siiApi';
import Swal from 'sweetalert2';

afterEach(cleanup);
beforeEach(() => {
    vi.clearAllMocks();
});

const certMock = {
    id: 1,
    rut_representante: '12345678-9',
    nombre_representante: 'Juan Pérez',
    expira_en: '2026-12-31',
    estado: 'activo',
};

describe('CertificadoSii', () => {
    it('muestra spinner mientras carga el certificado', () => {
        siiApi.certificado.obtener.mockReturnValue(new Promise(() => {}));
        render(<CertificadoSii />);
        expect(screen.getByText(/Cargando certificado/i)).toBeTruthy();
    });

    it('renderiza el título "Certificado Digital SII"', async () => {
        siiApi.certificado.obtener.mockResolvedValue(null);
        render(<CertificadoSii />);
        expect(screen.getByText(/Certificado Digital SII/i)).toBeTruthy();
    });

    it('muestra uploader cuando no hay certificado (404)', async () => {
        siiApi.certificado.obtener.mockRejectedValue({ status: 404 });
        render(<CertificadoSii />);
        await waitFor(() => {
            expect(screen.getByTestId('uploader-certificado')).toBeTruthy();
        });
    });

    it('muestra Swal de error cuando la API falla con error distinto de 404', async () => {
        siiApi.certificado.obtener.mockRejectedValue({ status: 500, message: 'Error interno del servidor' });
        render(<CertificadoSii />);
        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith(
                expect.objectContaining({ icon: 'error' })
            );
        });
    });

    it('muestra tarjeta de certificado cuando hay uno activo', async () => {
        siiApi.certificado.obtener.mockResolvedValue(certMock);
        render(<CertificadoSii />);
        await waitFor(() => {
            expect(screen.getByText('cert-activo')).toBeTruthy();
        });
    });

    it('muestra sección "Reemplazar certificado" cuando hay uno activo', async () => {
        siiApi.certificado.obtener.mockResolvedValue(certMock);
        render(<CertificadoSii />);
        await waitFor(() => {
            expect(screen.getByText('Reemplazar certificado')).toBeTruthy();
        });
    });

    it('botón "Reemplazar" muestra el uploader de reemplazo', async () => {
        siiApi.certificado.obtener.mockResolvedValue(certMock);
        render(<CertificadoSii />);
        await waitFor(() => screen.getByText('Reemplazar'));

        fireEvent.click(screen.getByText('Reemplazar'));
        expect(screen.getAllByTestId('uploader-certificado').length).toBeGreaterThan(0);
    });

    it('botón "Cancelar" oculta el uploader de reemplazo', async () => {
        siiApi.certificado.obtener.mockResolvedValue(certMock);
        render(<CertificadoSii />);
        await waitFor(() => screen.getByText('Reemplazar'));

        fireEvent.click(screen.getByText('Reemplazar'));
        expect(screen.getByText('Cancelar')).toBeTruthy();
        fireEvent.click(screen.getByText('Cancelar'));
        expect(screen.queryByText('Cancelar')).toBeNull();
    });

    it('subir nuevo certificado recarga la vista', async () => {
        siiApi.certificado.obtener.mockResolvedValue(certMock);
        render(<CertificadoSii />);
        await waitFor(() => screen.getByText('Reemplazar'));

        fireEvent.click(screen.getByText('Reemplazar'));
        const uploaders = screen.getAllByTestId('uploader-certificado');
        const btnSubir = uploaders[uploaders.length - 1].querySelector('button');
        fireEvent.click(btnSubir);

        await waitFor(() => {
            expect(siiApi.certificado.obtener.mock.calls.length).toBeGreaterThanOrEqual(2);
        });
    });

    it('revocar certificado llama siiApi.certificado.revocar', async () => {
        siiApi.certificado.obtener.mockResolvedValue(certMock);
        siiApi.certificado.revocar.mockResolvedValue({ success: true });

        render(<CertificadoSii />);
        await waitFor(() => screen.getByText('Revocar'));

        fireEvent.click(screen.getByText('Revocar'));

        await waitFor(() => {
            expect(siiApi.certificado.revocar).toHaveBeenCalledWith(certMock.id);
        });
    });

    it('muestra Swal de éxito al revocar certificado', async () => {
        siiApi.certificado.obtener.mockResolvedValue(certMock);
        siiApi.certificado.revocar.mockResolvedValue({ success: true });

        render(<CertificadoSii />);
        await waitFor(() => screen.getByText('Revocar'));

        fireEvent.click(screen.getByText('Revocar'));

        await waitFor(() => {
            expect(Swal.fire).toHaveBeenCalledWith(
                expect.objectContaining({ icon: 'success', title: 'Certificado revocado' })
            );
        });
    });
});
