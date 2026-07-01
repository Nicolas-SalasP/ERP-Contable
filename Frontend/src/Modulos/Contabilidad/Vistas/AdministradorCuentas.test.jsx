import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import AdministradorCuentas from './AdministradorCuentas';

// --- mocks ---

vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
    },
}));

vi.mock('sweetalert2', () => ({
    default: {
        fire: vi.fn().mockResolvedValue({ isConfirmed: true }),
    },
}));

vi.mock('../../../Configuracion/logger', () => ({
    logger: { error: vi.fn() },
}));

vi.mock('../../../Componentes/AyudaModulo', () => ({ default: () => null }));

vi.mock('../../../Componentes/EstadoCarga', () => ({ default: () => null }));

vi.mock('../../../Componentes/BotonAccion', () => ({
    default: ({ onClick, children }) => <button onClick={onClick}>{children}</button>,
}));

vi.mock('lucide-react', () => ({
    Plus: () => null,
    Search: () => null,
    Settings: () => null,
    X: () => null,
    Check: () => null,
}));

import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';

// --- fixtures ---

const cuentaGasto = {
    id: 1,
    codigo: '4100',
    nombre: 'Gastos Generales',
    tipo: 'GASTO',
    nivel: 2,
    imputable: 1,
    activo: 1,
    es_gasto_rechazado: 0,
};
const cuentaActivo = {
    id: 2,
    codigo: '1100',
    nombre: 'Caja',
    tipo: 'ACTIVO',
    nivel: 2,
    imputable: 1,
    activo: 1,
    es_gasto_rechazado: 0,
};
const cuentaInactiva = {
    id: 3,
    codigo: '5000',
    nombre: 'Cuenta Inactiva',
    tipo: 'PASIVO',
    nivel: 2,
    imputable: 1,
    activo: 0,
    es_gasto_rechazado: 0,
};

afterEach(cleanup);

// --- suite ---

describe('AdministradorCuentas', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.get.mockResolvedValue({ success: true, data: [cuentaGasto, cuentaActivo, cuentaInactiva] });
    });

    // -----------------------------------------------------------------------
    // Group 1 — render y carga inicial
    // -----------------------------------------------------------------------
    describe('render y carga inicial', () => {
        it('renderiza el título Plan de Cuentas', () => {
            render(<AdministradorCuentas />);
            // El h2 contiene "Configuración: Plan de Cuentas"
            expect(screen.getAllByText(/Plan de Cuentas/i)[0]).toBeTruthy();
        });

        it('carga cuentas al montar', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() =>
                expect(api.get).toHaveBeenCalledWith('/contabilidad/plan-cuentas')
            );
        });

        it('muestra las cuentas cargadas en la tabla', async () => {
            render(<AdministradorCuentas />);
            // El nombre aparece tanto en la tarjeta móvil (h3) como en la fila desktop (td)
            await waitFor(() =>
                expect(screen.getAllByText('Gastos Generales').length).toBeGreaterThan(0)
            );
        });
    });

    // -----------------------------------------------------------------------
    // Group 2 — filtros
    // -----------------------------------------------------------------------
    describe('filtros', () => {
        it('filtro de búsqueda por nombre filtra cuentas', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() =>
                expect(screen.getAllByText('Gastos Generales').length).toBeGreaterThan(0)
            );

            const input = screen.getByPlaceholderText(/Buscar por código o nombre/i);
            fireEvent.change(input, { target: { value: 'Caja' } });

            await waitFor(() => {
                expect(screen.queryAllByText('Caja').length).toBeGreaterThan(0);
                expect(screen.queryAllByText('Gastos Generales').length).toBe(0);
            });
        });

        it('filtro de búsqueda por código filtra cuentas', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() =>
                expect(screen.getAllByText('Gastos Generales').length).toBeGreaterThan(0)
            );

            const input = screen.getByPlaceholderText(/Buscar por código o nombre/i);
            fireEvent.change(input, { target: { value: '4100' } });

            await waitFor(() => {
                expect(screen.queryAllByText('Gastos Generales').length).toBeGreaterThan(0);
                expect(screen.queryAllByText('Caja').length).toBe(0);
            });
        });

        it('filtro por tipo GASTO muestra solo cuentas GASTO', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() =>
                expect(screen.getAllByText('Gastos Generales').length).toBeGreaterThan(0)
            );

            // getAllByRole('combobox') devuelve [filtroTipo, filtroEstado] en ese orden (JSX líneas 175 y 189)
            const selects = screen.getAllByRole('combobox');
            fireEvent.change(selects[0], { target: { value: 'GASTO' } });

            await waitFor(() => {
                expect(screen.queryAllByText('Gastos Generales').length).toBeGreaterThan(0);
                expect(screen.queryAllByText('Caja').length).toBe(0);
            });
        });

        it('filtro INACTIVA muestra solo cuentas inactivas', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() =>
                expect(screen.getAllByText('Gastos Generales').length).toBeGreaterThan(0)
            );

            const selects = screen.getAllByRole('combobox');
            fireEvent.change(selects[1], { target: { value: 'INACTIVA' } });

            await waitFor(() => {
                expect(screen.queryAllByText('Cuenta Inactiva').length).toBeGreaterThan(0);
                expect(screen.queryAllByText('Gastos Generales').length).toBe(0);
            });
        });
    });

    // -----------------------------------------------------------------------
    // Group 3 — lógica es_gasto_rechazado
    // -----------------------------------------------------------------------
    describe('lógica es_gasto_rechazado', () => {
        it('abrirEdicion popula formEdit con es_gasto_rechazado', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() =>
                expect(screen.getAllByText('Gastos Generales').length).toBeGreaterThan(0)
            );

            // Botones "Configurar": índices 0-2 tarjetas móvil, 3-5 tabla desktop.
            // Índice 0 → cuentaGasto (tipo=GASTO, es_gasto_rechazado=0)
            const btns = screen.getAllByText('Configurar');
            fireEvent.click(btns[0]);

            // El modal abre y la sección "Gasto Rechazado" es visible porque tipo=GASTO
            await waitFor(() =>
                expect(screen.getAllByText(/Gasto Rechazado/i).length).toBeGreaterThan(0)
            );

            // Hay 3 checkboxes en el modal: imputable, activo, es_gasto_rechazado (último)
            const checkboxes = screen.getAllByRole('checkbox');
            const gastoRechazadoCheckbox = checkboxes[checkboxes.length - 1];
            // cuentaGasto.es_gasto_rechazado = 0 → checkbox debe estar desmarcado
            expect(gastoRechazadoCheckbox.checked).toBe(false);
        });

        it('el toggle es_gasto_rechazado solo aparece para cuentas tipo GASTO', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() =>
                expect(screen.getAllByText('Gastos Generales').length).toBeGreaterThan(0)
            );

            // Abrir modal para cuentaGasto (tipo GASTO) → sección visible
            const btns = screen.getAllByText('Configurar');
            fireEvent.click(btns[0]);

            await waitFor(() =>
                expect(screen.getAllByText(/Gasto Rechazado/i).length).toBeGreaterThan(0)
            );

            // Cerrar modal con "Cancelar"
            fireEvent.click(screen.getByText('Cancelar'));

            await waitFor(() =>
                expect(screen.queryAllByText(/Gasto Rechazado/i).length).toBe(0)
            );

            // Abrir modal para cuentaActivo (tipo ACTIVO, índice 1 en lista móvil)
            const btnsAfterClose = screen.getAllByText('Configurar');
            fireEvent.click(btnsAfterClose[1]);

            // El modal abre (título "Propiedades de Cuenta") pero sin sección Gasto Rechazado
            await waitFor(() =>
                expect(screen.getAllByText(/Propiedades de Cuenta/i).length).toBeGreaterThan(0)
            );

            expect(screen.queryAllByText(/Gasto Rechazado/i).length).toBe(0);
        });
    });

    // -----------------------------------------------------------------------
    // Group 4 — validaciones
    // -----------------------------------------------------------------------
    describe('validaciones', () => {
        it('guardarCambios llama Swal.fire si código está vacío', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() => expect(api.get).toHaveBeenCalled());

            // Abrir modal de creación (formBase.codigo = '' y formBase.nombre = '')
            fireEvent.click(screen.getByText('Nueva Cuenta'));

            // BotonAccion mockeado muestra "Crear Cuenta" cuando cuentaEditando=null
            await waitFor(() => screen.getByText('Crear Cuenta'));
            fireEvent.click(screen.getByText('Crear Cuenta'));

            await waitFor(() =>
                expect(Swal.fire).toHaveBeenCalledWith(
                    expect.objectContaining({ icon: 'warning' })
                )
            );
        });

        it('guardarCambios no llama api.post si validación falla', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() => expect(api.get).toHaveBeenCalled());

            fireEvent.click(screen.getByText('Nueva Cuenta'));

            await waitFor(() => screen.getByText('Crear Cuenta'));
            fireEvent.click(screen.getByText('Crear Cuenta'));

            await waitFor(() => expect(Swal.fire).toHaveBeenCalled());

            expect(api.post).not.toHaveBeenCalled();
        });
    });

    // -----------------------------------------------------------------------
    // Group 5 — getTipoColor (indirecto vía badge renderizado)
    // -----------------------------------------------------------------------
    describe('getTipoColor', () => {
        it('el badge de tipo GASTO tiene clases de color naranja', async () => {
            render(<AdministradorCuentas />);
            await waitFor(() =>
                expect(screen.getAllByText('Gastos Generales').length).toBeGreaterThan(0)
            );

            // getTipoColor('GASTO') devuelve 'bg-orange-100 text-orange-800 border-orange-200'.
            // El texto "GASTO" aparece en el span de tipo: tarjeta móvil + celda desktop.
            const gastoSpans = screen.getAllByText('GASTO');
            expect(gastoSpans.length).toBeGreaterThan(0);
            expect(gastoSpans[0].className).toMatch(/orange/);
        });
    });
});
