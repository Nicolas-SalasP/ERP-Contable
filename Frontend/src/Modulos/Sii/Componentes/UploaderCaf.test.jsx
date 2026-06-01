import React from 'react';
import { describe, it, expect, afterEach, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

import UploaderCaf from './UploaderCaf';

afterEach(cleanup);

const archivoXml = (nombre = 'caf.xml', sizeBytes = 1024) =>
    new File(['x'.repeat(sizeBytes)], nombre, { type: 'application/xml' });

describe('UploaderCaf', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('rechaza archivo no .xml', () => {
        render(<UploaderCaf onSubidoExitosamente={vi.fn()} />);

        fireEvent.change(screen.getByTestId('caf-archivo'), {
            target: { files: [new File(['x'], 'foo.txt', { type: 'text/plain' })] },
        });

        expect(screen.getByTestId('caf-archivo-error').textContent).toMatch(/\.xml/i);
        expect(screen.getByTestId('caf-submit').disabled).toBe(true);
    });

    it('rechaza archivo mayor a 100KB', () => {
        render(<UploaderCaf onSubidoExitosamente={vi.fn()} />);

        const grande = archivoXml('big.xml', 150 * 1024);
        fireEvent.change(screen.getByTestId('caf-archivo'), { target: { files: [grande] } });

        expect(screen.getByTestId('caf-archivo-error').textContent).toMatch(/100 KB/);
        expect(screen.getByTestId('caf-submit').disabled).toBe(true);
    });

    it('boton submit deshabilitado sin archivo', () => {
        render(<UploaderCaf onSubidoExitosamente={vi.fn()} />);
        expect(screen.getByTestId('caf-submit').disabled).toBe(true);
    });

    it('submit exitoso llama onSubidoExitosamente con el archivo', async () => {
        const onSubidoExitosamente = vi.fn().mockResolvedValue({ id: 1, tipo_dte: 33, folio_desde: 1, folio_hasta: 50 });

        render(<UploaderCaf onSubidoExitosamente={onSubidoExitosamente} />);

        const file = archivoXml('mi-caf.xml', 2048);
        fireEvent.change(screen.getByTestId('caf-archivo'), { target: { files: [file] } });

        fireEvent.click(screen.getByTestId('caf-submit'));

        await waitFor(() => {
            expect(onSubidoExitosamente).toHaveBeenCalledTimes(1);
        });
        expect(onSubidoExitosamente.mock.calls[0][0]).toBe(file);
    });
});
