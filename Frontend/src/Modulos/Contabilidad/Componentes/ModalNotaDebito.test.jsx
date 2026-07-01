import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import ModalNotaDebito from './ModalNotaDebito';
import { api } from '../../../Configuracion/api';

const swalMock = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
}));
vi.mock('sweetalert2', () => ({ default: swalMock }));

vi.mock('../../../Configuracion/api', () => ({
    api: {
        post: vi.fn(),
    },
}));

const facturaBase = {
    id: 42,
    numero_factura: 'FV-001',
    monto_bruto: 119000,
    tipo: 'VENTA',
};

beforeEach(() => {
    vi.clearAllMocks();
});

afterEach(() => {
    cleanup();
});

const renderModal = (props = {}) =>
    render(
        <ModalNotaDebito
            isOpen={true}
            onClose={vi.fn()}
            factura={facturaBase}
            onNdEmitida={vi.fn()}
            {...props}
        />
    );

const fillRequired = ({ numeroNd = 'ND-001', razon = 'Cobro de intereses por mora', montoBruto = '10000' } = {}) => {
    if (numeroNd !== null) {
        fireEvent.change(screen.getByPlaceholderText('ND-00001'), { target: { value: numeroNd } });
    }
    if (razon !== null) {
        fireEvent.change(screen.getByPlaceholderText('Ej: Cobro de intereses por mora'), { target: { value: razon } });
    }
    if (montoBruto !== null) {
        const spinbuttons = screen.getAllByRole('spinbutton');
        fireEvent.change(spinbuttons[2], { target: { value: montoBruto } });
    }
};

describe('ModalNotaDebito — render', () => {
    it('no renderiza nada cuando isOpen=false', () => {
        render(
            <ModalNotaDebito isOpen={false} onClose={vi.fn()} factura={facturaBase} onNdEmitida={vi.fn()} />
        );
        expect(screen.queryByText('Emitir Nota de Débito')).toBeNull();
    });

    it('muestra encabezado con número de factura cuando isOpen=true', () => {
        renderModal();
        expect(screen.getByRole('heading', { name: 'Emitir Nota de Débito' })).toBeDefined();
        expect(screen.getByText(/FV-001/)).toBeDefined();
    });

    it('botón Cancelar llama onClose', () => {
        const onClose = vi.fn();
        renderModal({ onClose });
        fireEvent.click(screen.getByRole('button', { name: /Cancelar/i }));
        expect(onClose).toHaveBeenCalledTimes(1);
    });
});

describe('ModalNotaDebito — validaciones', () => {
    it('Swal warning si numeroNd vacío al enviar', async () => {
        renderModal();
        fillRequired({ numeroNd: null });
        fireEvent.submit(screen.getByRole('button', { name: /Emitir Nota de Débito/i }).closest('form'));
        await waitFor(() => {
            expect(swalMock.fire).toHaveBeenCalledWith('Campo requerido', expect.any(String), 'warning');
        });
    });

    it('Swal warning si razon < 5 chars', async () => {
        renderModal();
        fillRequired({ razon: 'ab' });
        fireEvent.submit(screen.getByRole('button', { name: /Emitir Nota de Débito/i }).closest('form'));
        await waitFor(() => {
            expect(swalMock.fire).toHaveBeenCalledWith('Campo requerido', expect.stringContaining('5 caracteres'), 'warning');
        });
    });

    it('Swal warning si montoBruto = 0', async () => {
        renderModal();
        fillRequired({ montoBruto: null });
        fireEvent.submit(screen.getByRole('button', { name: /Emitir Nota de Débito/i }).closest('form'));
        await waitFor(() => {
            expect(swalMock.fire).toHaveBeenCalledWith('Campo requerido', expect.stringContaining('mayor a 0'), 'warning');
        });
    });
});

describe('ModalNotaDebito — cálculo automático', () => {
    it('recalcBruto suma neto+IVA en onBlur del campo IVA', async () => {
        renderModal();
        const spinbuttons = screen.getAllByRole('spinbutton');
        const inputNeto = spinbuttons[0];
        const inputIva = spinbuttons[1];

        fireEvent.change(inputNeto, { target: { value: '100000' } });
        fireEvent.change(inputIva, { target: { value: '19000' } });
        fireEvent.blur(inputIva);

        await waitFor(() => {
            const updated = screen.getAllByRole('spinbutton');
            expect(updated[2].value).toBe('119000');
        });
    });
});

describe('ModalNotaDebito — submit exitoso', () => {
    it('llama api.post con campos correctos y ejecuta onNdEmitida + onClose', async () => {
        api.post.mockResolvedValue({ success: true });
        const onNdEmitida = vi.fn();
        const onClose = vi.fn();
        renderModal({ onNdEmitida, onClose });

        fillRequired({ numeroNd: 'ND-001', razon: 'Cobro de intereses por mora', montoBruto: '10000' });
        fireEvent.click(screen.getByRole('button', { name: /Emitir Nota de Débito/i }));

        await waitFor(() => {
            expect(api.post).toHaveBeenCalledWith(
                `/facturas/${facturaBase.id}/nota-debito`,
                expect.objectContaining({
                    numero_nd: 'ND-001',
                    razon: 'Cobro de intereses por mora',
                    monto_bruto: 10000,
                })
            );
            expect(onNdEmitida).toHaveBeenCalledTimes(1);
            expect(onClose).toHaveBeenCalledTimes(1);
        });
    });

    it('muestra Swal success con número de ND', async () => {
        api.post.mockResolvedValue({ success: true });
        renderModal();

        fillRequired({ numeroNd: 'ND-007', razon: 'Cobro de intereses por mora', montoBruto: '5000' });
        fireEvent.click(screen.getByRole('button', { name: /Emitir Nota de Débito/i }));

        await waitFor(() => {
            expect(swalMock.fire).toHaveBeenCalledWith(
                expect.objectContaining({
                    icon: 'success',
                    title: 'Nota de Débito emitida',
                    text: expect.stringContaining('ND-007'),
                })
            );
        });
    });
});

describe('ModalNotaDebito — error API', () => {
    it('muestra Swal error con mensaje del servidor cuando POST falla', async () => {
        api.post.mockRejectedValue({
            response: { data: { message: 'Folio ya utilizado en este período.' } },
        });
        renderModal();

        fillRequired();
        fireEvent.click(screen.getByRole('button', { name: /Emitir Nota de Débito/i }));

        await waitFor(() => {
            expect(swalMock.fire).toHaveBeenCalledWith(
                'Error',
                'Folio ya utilizado en este período.',
                'error'
            );
        });
    });
});
