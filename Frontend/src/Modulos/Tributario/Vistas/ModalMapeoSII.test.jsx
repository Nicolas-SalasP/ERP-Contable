import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

// vi.mock ANTES de los imports del módulo bajo test
vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
    },
}));
vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: false }) },
}));
vi.mock('../../../Configuracion/logger', () => ({
    logger: { log: vi.fn(), error: vi.fn(), warn: vi.fn() },
}));
vi.mock('../../../Componentes/EstadoCarga', () => ({
    default: ({ cargando }) =>
        cargando ? <div data-testid="estado-carga">Cargando...</div> : null,
}));
vi.mock('../../../Componentes/BotonAccion', () => ({
    default: ({ children, type, cargando }) => (
        <button type={type ?? 'button'} disabled={cargando}>
            {cargando ? 'Procesando...' : children}
        </button>
    ),
}));

import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';
import ModalMapeoSII from './ModalMapeoSII';

afterEach(cleanup);

const respuestaVacia = {
    success: true,
    data: { mapeadas: [], disponibles: [], conceptos: {} },
};

const respuestaConDatos = {
    success: true,
    data: {
        mapeadas: [
            { id: 1, codigo_cuenta: '4100', nombre: 'Ventas', concepto_sii: 'INGRESO_GIRO' },
        ],
        disponibles: [
            { codigo: '5100', nombre: 'Costos de Venta' },
        ],
        conceptos: {
            INGRESO_GIRO: 'Ingresos del Giro',
            GASTO_DIRECTO: 'Gastos Directos',
        },
    },
};

describe('ModalMapeoSII', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('renderiza el título "Mapeo del Plan de Cuentas (SII)"', async () => {
        api.get.mockResolvedValue(respuestaVacia);
        render(<ModalMapeoSII onClose={vi.fn()} />);
        expect(screen.getByText(/Mapeo del Plan de Cuentas \(SII\)/i)).toBeTruthy();
    });

    it('muestra "No hay cuentas vinculadas." cuando mapeadas está vacío', async () => {
        api.get.mockResolvedValue(respuestaVacia);
        render(<ModalMapeoSII onClose={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getByText('No hay cuentas vinculadas.')).toBeTruthy();
        });
    });

    it('muestra las cuentas mapeadas cuando la api retorna datos', async () => {
        api.get.mockResolvedValue(respuestaConDatos);
        render(<ModalMapeoSII onClose={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getByText('Ventas')).toBeTruthy();
            expect(screen.getByText('4100')).toBeTruthy();
        });
    });

    it('muestra el concepto SII asignado a cada cuenta mapeada', async () => {
        api.get.mockResolvedValue(respuestaConDatos);
        render(<ModalMapeoSII onClose={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getAllByText('Ingresos del Giro').length).toBeGreaterThan(0);
        });
    });

    it('llama onClose al hacer click en el botón cerrar del header', async () => {
        api.get.mockResolvedValue(respuestaVacia);
        const onClose = vi.fn();
        render(<ModalMapeoSII onClose={onClose} />);
        await waitFor(() => screen.getByText('No hay cuentas vinculadas.'));
        // El botón X del header tiene aria-label="Cerrar" (nombre accesible exacto)
        const botonCerrar = screen.getByRole('button', { name: 'Cerrar' });
        fireEvent.click(botonCerrar);
        expect(onClose).toHaveBeenCalled();
    });

    it('muestra cuentas disponibles en el selector al cargar', async () => {
        api.get.mockResolvedValue(respuestaConDatos);
        render(<ModalMapeoSII onClose={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getByText('5100 - Costos de Venta')).toBeTruthy();
        });
    });

    it('muestra opciones de concepto SII en el selector', async () => {
        api.get.mockResolvedValue(respuestaConDatos);
        render(<ModalMapeoSII onClose={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getAllByText('Gastos Directos').length).toBeGreaterThan(0);
        });
    });

    it('muestra botón "Eliminar" para cada cuenta mapeada', async () => {
        api.get.mockResolvedValue(respuestaConDatos);
        render(<ModalMapeoSII onClose={vi.fn()} />);
        await waitFor(() => {
            expect(screen.getByRole('button', { name: /Eliminar/i })).toBeTruthy();
        });
    });
});
