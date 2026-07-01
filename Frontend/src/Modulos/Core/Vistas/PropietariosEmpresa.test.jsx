import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('../Servicios/propietariosApi', () => ({
    propietariosApi: {
        listar: vi.fn(),
        crear: vi.fn(),
        actualizar: vi.fn(),
        eliminar: vi.fn(),
    },
}));

vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({
    default: () => null,
}));

vi.mock('../../../Componentes/Skeleton', () => ({
    TablaSkeleton: () => <div data-testid="tabla-skeleton">Cargando...</div>,
}));

vi.mock('../../../Componentes/EstadoVacio', () => ({
    EstadoVacio: ({ mensaje }) => <div data-testid="estado-vacio">{mensaje}</div>,
}));

vi.mock('../../../Componentes/ConfirmacionInline', () => ({
    BotonEliminar: ({ onConfirmar }) => (
        <button onClick={onConfirmar} data-testid="btn-eliminar">Eliminar</button>
    ),
}));

import PropietariosEmpresa from './PropietariosEmpresa';
import { propietariosApi } from '../Servicios/propietariosApi';

afterEach(cleanup);
beforeEach(() => {
    vi.clearAllMocks();
});

const propietariosMock = [
    { id: 1, rut: '12.345.678-5', nombre: 'Juan Pérez Silva', porcentaje_participacion: 60 },
    { id: 2, rut: '98.765.432-1', nombre: 'María González', porcentaje_participacion: 40 },
];

describe('PropietariosEmpresa', () => {
    it('renderiza el título "Propietarios de la Empresa"', async () => {
        propietariosApi.listar.mockResolvedValue([]);
        render(<PropietariosEmpresa />);
        expect(screen.getByText('Propietarios de la Empresa')).toBeTruthy();
    });

    it('carga la lista al montar el componente', async () => {
        propietariosApi.listar.mockResolvedValue([]);
        render(<PropietariosEmpresa />);
        await waitFor(() => {
            expect(propietariosApi.listar).toHaveBeenCalledTimes(1);
        });
    });

    it('muestra estado vacío cuando no hay propietarios registrados', async () => {
        propietariosApi.listar.mockResolvedValue([]);
        render(<PropietariosEmpresa />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-vacio')).toBeTruthy();
            expect(screen.getByText('Sin propietarios registrados.')).toBeTruthy();
        });
    });

    it('muestra filas con datos de propietarios', async () => {
        propietariosApi.listar.mockResolvedValue(propietariosMock);
        render(<PropietariosEmpresa />);
        await waitFor(() => {
            expect(screen.getByText('12.345.678-5')).toBeTruthy();
            expect(screen.getByText('Juan Pérez Silva')).toBeTruthy();
            expect(screen.getByText('98.765.432-1')).toBeTruthy();
            expect(screen.getByText('María González')).toBeTruthy();
        });
    });

    it('muestra el formulario de agregar propietario siempre visible', async () => {
        propietariosApi.listar.mockResolvedValue([]);
        render(<PropietariosEmpresa />);
        expect(screen.getByRole('button', { name: /Agregar propietario/i })).toBeTruthy();
        expect(screen.getByPlaceholderText('12345678-5')).toBeTruthy();
        expect(screen.getByPlaceholderText('Juan Pérez Silva')).toBeTruthy();
    });

    it('envío del formulario llama propietariosApi.crear con los datos ingresados', async () => {
        propietariosApi.listar.mockResolvedValue([]);
        propietariosApi.crear.mockResolvedValue({ id: 3 });
        render(<PropietariosEmpresa />);

        fireEvent.change(screen.getByPlaceholderText('12345678-5'), { target: { value: '11.111.111-1' } });
        fireEvent.change(screen.getByPlaceholderText('Juan Pérez Silva'), { target: { value: 'Carlos López' } });
        fireEvent.change(screen.getByPlaceholderText('50.00'), { target: { value: '100' } });
        fireEvent.click(screen.getByRole('button', { name: /Agregar propietario/i }));

        await waitFor(() => {
            expect(propietariosApi.crear).toHaveBeenCalledWith(
                expect.objectContaining({
                    rut: '11.111.111-1',
                    nombre: 'Carlos López',
                    porcentaje_participacion: 100,
                })
            );
        });
    });
});
