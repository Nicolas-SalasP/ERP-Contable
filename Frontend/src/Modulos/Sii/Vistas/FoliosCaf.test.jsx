import React from 'react';
import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../Servicios/siiApi', () => ({
    default: {
        caf: {
            listar: vi.fn(),
            saldos: vi.fn(),
            subir: vi.fn(),
            revocar: vi.fn(),
            mostrar: vi.fn(),
        },
    },
}));

import Swal from 'sweetalert2';
import siiApi from '../Servicios/siiApi';
import FoliosCaf from './FoliosCaf';

afterEach(cleanup);

describe('FoliosCaf (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        siiApi.caf.listar.mockResolvedValue({ data: [] });
        siiApi.caf.saldos.mockResolvedValue({ data: {} });
    });

    it('render inicial llama saldos y listar una vez', async () => {
        render(<FoliosCaf />);

        await waitFor(() => {
            expect(siiApi.caf.saldos).toHaveBeenCalledTimes(1);
            expect(siiApi.caf.listar).toHaveBeenCalledTimes(1);
        });
    });

    it('tras subir exitoso muestra Swal de exito (toast)', async () => {
        siiApi.caf.subir.mockResolvedValue({ id: 7, tipo_dte: 33, folio_desde: 1, folio_hasta: 50 });

        render(<FoliosCaf />);

        await waitFor(() => {
            expect(screen.getByTestId('uploader-caf')).toBeDefined();
        });

        const file = new File(['<x/>'], 'caf.xml', { type: 'application/xml' });
        fireEvent.change(screen.getByTestId('caf-archivo'), { target: { files: [file] } });
        fireEvent.click(screen.getByTestId('caf-submit'));

        await waitFor(() => {
            expect(siiApi.caf.subir).toHaveBeenCalledTimes(1);
        });

        await waitFor(() => {
            const llamadas = Swal.fire.mock.calls.map((c) => c[0]);
            const tieneExito = llamadas.some((c) => c?.icon === 'success' && c?.toast === true);
            expect(tieneExito).toBe(true);
        });
    });

    it('tras revocar exitoso muestra Swal de exito y refresca', async () => {
        siiApi.caf.listar.mockResolvedValueOnce({
            data: [{
                id: 9, tipo_dte: 33, folio_desde: 1, folio_hasta: 50, folio_actual: 1,
                folios_usados: 0, folios_huerfanos: 0,
                fecha_autorizacion: '2026-01-15',
                fecha_vencimiento: new Date(Date.now() + 90 * 86400000).toISOString().slice(0, 10),
                sii_idk: '300', estado: 'activo',
            }],
        });
        siiApi.caf.revocar.mockResolvedValue(null);

        render(<FoliosCaf />);

        await waitFor(() => {
            expect(screen.getByTestId('btn-revocar-9')).toBeDefined();
        });

        fireEvent.click(screen.getByTestId('btn-revocar-9'));

        await waitFor(() => {
            expect(screen.getByTestId('modal-revocar')).toBeDefined();
        });

        fireEvent.change(screen.getByTestId('motivo-textarea'), {
            target: { value: 'razon valida de prueba' },
        });
        fireEvent.click(screen.getByTestId('btn-confirmar-revocar'));

        await waitFor(() => {
            expect(siiApi.caf.revocar).toHaveBeenCalledWith(9, 'razon valida de prueba');
        });

        await waitFor(() => {
            const llamadas = Swal.fire.mock.calls.map((c) => c[0]);
            const tieneExito = llamadas.some((c) => c?.icon === 'success' && c?.toast === true);
            expect(tieneExito).toBe(true);
        });
    });
});
