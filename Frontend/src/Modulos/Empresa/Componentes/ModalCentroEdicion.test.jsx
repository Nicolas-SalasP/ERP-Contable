import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import ModalCentroEdicion from './ModalCentroEdicion';

afterEach(cleanup);

const centroDatos = { id: 3, codigo: 'VTA', nombre: 'Ventas' };

describe('ModalCentroEdicion', () => {
    it('no renderiza cuando isOpen=false', () => {
        render(<ModalCentroEdicion isOpen={false} centro={centroDatos} onChange={vi.fn()} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.queryByText('Editar Centro de Costo')).toBeNull();
    });

    it('no renderiza cuando centro=null', () => {
        render(<ModalCentroEdicion isOpen={true} centro={null} onChange={vi.fn()} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.queryByText('Editar Centro de Costo')).toBeNull();
    });

    it('renderiza el título cuando isOpen=true y centro provisto', () => {
        render(<ModalCentroEdicion isOpen={true} centro={centroDatos} onChange={vi.fn()} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.getByText('Editar Centro de Costo')).toBeTruthy();
    });

    it('muestra los valores actuales del centro', () => {
        render(<ModalCentroEdicion isOpen={true} centro={centroDatos} onChange={vi.fn()} onClose={vi.fn()} onSubmit={vi.fn()} />);
        expect(screen.getByDisplayValue('VTA')).toBeTruthy();
        expect(screen.getByDisplayValue('Ventas')).toBeTruthy();
    });

    it('llama onClose al hacer click en X', () => {
        const onClose = vi.fn();
        render(<ModalCentroEdicion isOpen={true} centro={centroDatos} onChange={vi.fn()} onClose={onClose} onSubmit={vi.fn()} />);
        fireEvent.click(screen.getAllByRole('button')[0]);
        expect(onClose).toHaveBeenCalled();
    });

    it('llama onChange con codigo en mayúscula', () => {
        const onChange = vi.fn();
        render(<ModalCentroEdicion isOpen={true} centro={centroDatos} onChange={onChange} onClose={vi.fn()} onSubmit={vi.fn()} />);
        fireEvent.change(screen.getByDisplayValue('VTA'), { target: { value: 'adm' } });
        expect(onChange).toHaveBeenCalledWith({ ...centroDatos, codigo: 'ADM' });
    });

    it('llama onChange al cambiar el nombre', () => {
        const onChange = vi.fn();
        render(<ModalCentroEdicion isOpen={true} centro={centroDatos} onChange={onChange} onClose={vi.fn()} onSubmit={vi.fn()} />);
        fireEvent.change(screen.getByDisplayValue('Ventas'), { target: { value: 'Administración' } });
        expect(onChange).toHaveBeenCalledWith({ ...centroDatos, nombre: 'Administración' });
    });

    it('llama onSubmit al hacer click en Guardar', () => {
        const onSubmit = vi.fn(e => e.preventDefault());
        render(<ModalCentroEdicion isOpen={true} centro={centroDatos} onChange={vi.fn()} onClose={vi.fn()} onSubmit={onSubmit} />);
        fireEvent.click(screen.getByRole('button', { name: /Guardar Cambios/i }));
        expect(onSubmit).toHaveBeenCalled();
    });

    it('llama onClose al hacer click en Cancelar', () => {
        const onClose = vi.fn();
        render(<ModalCentroEdicion isOpen={true} centro={centroDatos} onChange={vi.fn()} onClose={onClose} onSubmit={vi.fn()} />);
        fireEvent.click(screen.getByRole('button', { name: /Cancelar/i }));
        expect(onClose).toHaveBeenCalled();
    });
});
