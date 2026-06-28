import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import ModalBancoEdicion from './ModalBancoEdicion';

afterEach(cleanup);

const listaBancos = [
    { id: 1, nombre: 'Banco de Chile' },
    { id: 2, nombre: 'BCI' },
];

const bancoDatos = {
    id: 5,
    banco: 'BCI',
    tipo_cuenta: 'Corriente',
    numero_cuenta: '98765432',
};

describe('ModalBancoEdicion', () => {
    it('no renderiza cuando isOpen=false', () => {
        render(<ModalBancoEdicion isOpen={false} banco={bancoDatos} listaBancos={listaBancos} onChange={vi.fn()} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.queryByText('Editar Cuenta Bancaria')).toBeNull();
    });

    it('no renderiza cuando banco=null', () => {
        render(<ModalBancoEdicion isOpen={true} banco={null} listaBancos={listaBancos} onChange={vi.fn()} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.queryByText('Editar Cuenta Bancaria')).toBeNull();
    });

    it('renderiza el título cuando isOpen=true y banco provisto', () => {
        render(<ModalBancoEdicion isOpen={true} banco={bancoDatos} listaBancos={listaBancos} onChange={vi.fn()} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.getByText('Editar Cuenta Bancaria')).toBeTruthy();
    });

    it('muestra las opciones del catálogo de bancos', () => {
        render(<ModalBancoEdicion isOpen={true} banco={bancoDatos} listaBancos={listaBancos} onChange={vi.fn()} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.getByText('Banco de Chile')).toBeTruthy();
        expect(screen.getByText('BCI')).toBeTruthy();
    });

    it('llama onClose al hacer click en X', () => {
        const onClose = vi.fn();
        render(<ModalBancoEdicion isOpen={true} banco={bancoDatos} listaBancos={listaBancos} onChange={vi.fn()} onClose={onClose} onSubmit={vi.fn()} />);
        const botones = screen.getAllByRole('button');
        const cerrar = botones.find(b => b.title === undefined && !b.type?.includes('submit'));
        fireEvent.click(botones[0]);
        expect(onClose).toHaveBeenCalled();
    });

    it('llama onChange al cambiar el número de cuenta', () => {
        const onChange = vi.fn();
        render(<ModalBancoEdicion isOpen={true} banco={bancoDatos} listaBancos={listaBancos} onChange={onChange} onClose={vi.fn()} onSubmit={vi.fn()} />);
        const input = screen.getByDisplayValue('98765432');
        fireEvent.change(input, { target: { value: '11111111' } });
        expect(onChange).toHaveBeenCalledWith({ ...bancoDatos, numero_cuenta: '11111111' });
    });

    it('llama onSubmit al enviar el formulario', () => {
        const onSubmit = vi.fn(e => e.preventDefault());
        render(<ModalBancoEdicion isOpen={true} banco={bancoDatos} listaBancos={listaBancos} onChange={vi.fn()} onClose={vi.fn()} onSubmit={onSubmit} />);
        fireEvent.click(screen.getByRole('button', { name: /Guardar Cambios/i }));
        expect(onSubmit).toHaveBeenCalled();
    });

    it('llama onClose al hacer click en Cancelar', () => {
        const onClose = vi.fn();
        render(<ModalBancoEdicion isOpen={true} banco={bancoDatos} listaBancos={listaBancos} onChange={vi.fn()} onClose={onClose} onSubmit={vi.fn()} />);
        fireEvent.click(screen.getByRole('button', { name: /Cancelar/i }));
        expect(onClose).toHaveBeenCalled();
    });
});
