import React from 'react';
import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import ModalRevocarCaf from './ModalRevocarCaf';

afterEach(cleanup);

const cafBase = (overrides = {}) => ({
    id: 5,
    tipo_dte: 33,
    folio_desde: 1,
    folio_hasta: 50,
    folios_usados: 12,
    ...overrides,
});

describe('ModalRevocarCaf', () => {
    it('no renderiza si abierto=false', () => {
        const { container } = render(
            <ModalRevocarCaf abierto={false} caf={cafBase()} onCerrar={vi.fn()} onConfirmar={vi.fn()} />
        );
        expect(container.firstChild).toBeNull();
    });

    it('renderiza warning explicito de irreversibilidad', () => {
        render(
            <ModalRevocarCaf abierto={true} caf={cafBase()} onCerrar={vi.fn()} onConfirmar={vi.fn()} />
        );
        expect(screen.getByText(/irreversible/i)).toBeDefined();
        expect(screen.getByText(/huerfanos/i)).toBeDefined();
    });

    it('boton revocar deshabilitado si motivo < 5 chars', () => {
        render(
            <ModalRevocarCaf abierto={true} caf={cafBase()} onCerrar={vi.fn()} onConfirmar={vi.fn()} />
        );
        fireEvent.change(screen.getByTestId('motivo-textarea'), { target: { value: 'no' } });
        expect(screen.getByTestId('btn-confirmar-revocar').disabled).toBe(true);
    });

    it('boton revocar deshabilitado si motivo supera 200 chars (guard del componente)', () => {
        // El atributo maxLength del JSX previene la entrada en navegadores reales.
        // En jsdom, fireEvent.change ignora maxLength, asi que verificamos el guard
        // de validacion del componente (motivo.length <= MOTIVO_MAX).
        render(
            <ModalRevocarCaf abierto={true} caf={cafBase()} onCerrar={vi.fn()} onConfirmar={vi.fn()} />
        );
        fireEvent.change(screen.getByTestId('motivo-textarea'), {
            target: { value: 'a'.repeat(250) },
        });
        expect(screen.getByTestId('btn-confirmar-revocar').disabled).toBe(true);
    });

    it('confirmar llama onConfirmar con el motivo', () => {
        const onConfirmar = vi.fn();
        render(
            <ModalRevocarCaf abierto={true} caf={cafBase()} onCerrar={vi.fn()} onConfirmar={onConfirmar} />
        );
        fireEvent.change(screen.getByTestId('motivo-textarea'), {
            target: { value: 'motivo de prueba valido' },
        });
        fireEvent.click(screen.getByTestId('btn-confirmar-revocar'));
        expect(onConfirmar).toHaveBeenCalledWith('motivo de prueba valido');
    });

    it('cancelar llama onCerrar', () => {
        const onCerrar = vi.fn();
        render(
            <ModalRevocarCaf abierto={true} caf={cafBase()} onCerrar={onCerrar} onConfirmar={vi.fn()} />
        );
        fireEvent.click(screen.getByTestId('btn-cancelar-revocar'));
        expect(onCerrar).toHaveBeenCalled();
    });
});
