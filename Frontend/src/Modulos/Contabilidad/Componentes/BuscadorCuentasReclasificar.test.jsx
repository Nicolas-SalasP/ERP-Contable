import React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';

vi.mock('lucide-react', () => ({
    Search: () => null,
}));

import BuscadorCuentasReclasificar from './BuscadorCuentasReclasificar';

afterEach(cleanup);

const cuentasMock = [
    { codigo: '1101', nombre: 'Caja' },
    { codigo: '1102', nombre: 'Banco' },
    { codigo: '4101', nombre: 'Ventas' },
    { codigo: '5101', nombre: 'Costo de Ventas' },
];

describe('BuscadorCuentasReclasificar', () => {
    it('renderiza el campo de búsqueda', () => {
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="" onChange={vi.fn()} />
        );
        expect(screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i)).toBeTruthy();
    });

    it('al enfocar el campo muestra la lista de cuentas', () => {
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="" onChange={vi.fn()} />
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        fireEvent.focus(input);
        expect(screen.getByText('Caja')).toBeTruthy();
        expect(screen.getByText('Banco')).toBeTruthy();
    });

    it('filtra cuentas al escribir el código', () => {
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="" onChange={vi.fn()} />
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        fireEvent.focus(input);
        fireEvent.change(input, { target: { value: '1101' } });
        expect(screen.getByText('Caja')).toBeTruthy();
        expect(screen.queryByText('Ventas')).toBeNull();
    });

    it('filtra cuentas al escribir el nombre (case insensitive)', () => {
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="" onChange={vi.fn()} />
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        fireEvent.focus(input);
        fireEvent.change(input, { target: { value: 'caja' } });
        expect(screen.getByText('Caja')).toBeTruthy();
        expect(screen.queryByText('Banco')).toBeNull();
    });

    it('muestra "No se encontraron cuentas" cuando no hay resultados', () => {
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="" onChange={vi.fn()} />
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        fireEvent.focus(input);
        fireEvent.change(input, { target: { value: 'ZZZZZ' } });
        expect(screen.getByText('No se encontraron cuentas')).toBeTruthy();
    });

    it('seleccionar una cuenta llama onChange con el código', () => {
        const onChange = vi.fn();
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="" onChange={onChange} />
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        fireEvent.focus(input);
        fireEvent.click(screen.getByText('Caja'));
        expect(onChange).toHaveBeenCalledWith('1101');
    });

    it('seleccionar cuenta muestra el código y nombre en el input', () => {
        const onChange = vi.fn();
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="" onChange={onChange} />
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        fireEvent.focus(input);
        fireEvent.click(screen.getByText('Caja'));
        expect(input.value).toBe('1101 - Caja');
    });

    it('seleccionar cuenta cierra el dropdown', () => {
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="" onChange={vi.fn()} />
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        fireEvent.focus(input);
        fireEvent.click(screen.getByText('Caja'));
        expect(screen.queryByText('Banco')).toBeNull();
    });

    it('cuando valor está establecido, muestra código-nombre en el input', () => {
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="1102" onChange={vi.fn()} />
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        expect(input.value).toBe('1102 - Banco');
    });

    it('al escribir con valor previo llama onChange con string vacío', () => {
        const onChange = vi.fn();
        render(
            <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="1101" onChange={onChange} />
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        fireEvent.change(input, { target: { value: 'nuevo texto' } });
        expect(onChange).toHaveBeenCalledWith('');
    });

    it('click fuera del componente cierra el dropdown', () => {
        render(
            <div>
                <BuscadorCuentasReclasificar cuentas={cuentasMock} valor="" onChange={vi.fn()} />
                <div data-testid="exterior">Exterior</div>
            </div>
        );
        const input = screen.getByPlaceholderText(/Escriba código o nombre de la cuenta/i);
        fireEvent.focus(input);
        expect(screen.getByText('Caja')).toBeTruthy();

        fireEvent.mouseDown(screen.getByTestId('exterior'));
        expect(screen.queryByText('Caja')).toBeNull();
    });
});
