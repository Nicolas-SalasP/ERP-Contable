import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';
import PerfilEmpresaBancos from './PerfilEmpresaBancos';

afterEach(cleanup);

const listaBancos = [
    { id: 1, nombre: 'Banco de Chile' },
    { id: 2, nombre: 'BCI' },
];

const bancosData = [
    { id: 10, banco: 'BCI', tipo_cuenta: 'Corriente', numero_cuenta: '123456' },
    { id: 11, banco: 'Banco de Chile', tipo_cuenta: 'Vista', numero_cuenta: '654321' },
];

const defaultProps = {
    bancos: [],
    listaBancos,
    nuevoBanco: { banco: '', tipo_cuenta: 'Corriente', numero_cuenta: '' },
    onNuevoBancoChange: vi.fn(),
    onAgregarBanco: vi.fn(),
    onEditarBanco: vi.fn(),
    onEliminarBanco: vi.fn(),
};

describe('PerfilEmpresaBancos', () => {
    beforeEach(() => vi.clearAllMocks());

    it('muestra mensaje vacío cuando no hay cuentas', () => {
        render(<PerfilEmpresaBancos {...defaultProps} />);
        expect(screen.getByText(/No hay cuentas registradas/i)).toBeTruthy();
    });

    it('renderiza filas de cuentas bancarias', () => {
        render(<PerfilEmpresaBancos {...defaultProps} bancos={bancosData} />);
        expect(screen.getAllByText('BCI').length).toBeGreaterThan(0);
        expect(screen.getAllByText('Banco de Chile').length).toBeGreaterThan(0);
    });

    it('muestra número de cuenta en formato tipo • numero', () => {
        render(<PerfilEmpresaBancos {...defaultProps} bancos={bancosData} />);
        expect(screen.getByText('Corriente • 123456')).toBeTruthy();
    });

    it('llama onAgregarBanco al enviar el formulario', () => {
        const onAgregarBanco = vi.fn(e => e.preventDefault());
        render(<PerfilEmpresaBancos {...defaultProps} onAgregarBanco={onAgregarBanco} />);
        fireEvent.click(screen.getByRole('button', { name: /Agregar Cuenta/i }));
        expect(onAgregarBanco).toHaveBeenCalled();
    });

    it('llama onEditarBanco al hacer click en editar', () => {
        const onEditarBanco = vi.fn();
        render(<PerfilEmpresaBancos {...defaultProps} bancos={bancosData} onEditarBanco={onEditarBanco} />);
        const botonesEditar = screen.getAllByTitle('Editar');
        fireEvent.click(botonesEditar[0]);
        expect(onEditarBanco).toHaveBeenCalledWith(bancosData[0]);
    });

    it('llama onEliminarBanco al hacer click en eliminar', () => {
        const onEliminarBanco = vi.fn();
        render(<PerfilEmpresaBancos {...defaultProps} bancos={bancosData} onEliminarBanco={onEliminarBanco} />);
        const botonesEliminar = screen.getAllByTitle('Eliminar');
        fireEvent.click(botonesEliminar[0]);
        expect(onEliminarBanco).toHaveBeenCalledWith(bancosData[0].id);
    });

    it('popula opciones del catálogo en el select de institución', () => {
        render(<PerfilEmpresaBancos {...defaultProps} />);
        expect(screen.getByText('Banco de Chile')).toBeTruthy();
        expect(screen.getByText('BCI')).toBeTruthy();
    });

    it('llama onNuevoBancoChange al cambiar el número de cuenta', () => {
        const onNuevoBancoChange = vi.fn();
        render(<PerfilEmpresaBancos {...defaultProps} onNuevoBancoChange={onNuevoBancoChange} />);
        const inputCuenta = screen.getByPlaceholderText('123456789');
        fireEvent.change(inputCuenta, { target: { value: '99887766' } });
        expect(onNuevoBancoChange).toHaveBeenCalledWith(expect.objectContaining({ numero_cuenta: '99887766' }));
    });
});
