import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

const permisosMock = vi.hoisted(() => ({ tienePermiso: () => true }));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => permisosMock,
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

// El plan de cuentas se carga vía api.get; lo dejamos vacío para usar el respaldo manual
// (no renderiza el BuscadorCuentaContable, que requiere su propia carga).
vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn(() => Promise.resolve({ success: true, data: [] })) },
}));

vi.mock('../Servicios/rrhhApi', () => ({
    default: {
        mapeoContable: { listar: vi.fn(), guardar: vi.fn(), eliminar: vi.fn() },
        centralizacion: { ejecutar: vi.fn() },
    },
}));

import CentralizacionRrhh from './CentralizacionRrhh';
import rrhhApi from '../Servicios/rrhhApi';

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    rrhhApi.mapeoContable.listar.mockResolvedValue({
        data: [],
        tipos_requeridos: ['GASTO_REMUNERACIONES', 'PASIVO_LIQUIDO_PAGAR'],
        todos_los_tipos: ['GASTO_REMUNERACIONES', 'GASTO_LEYES_SOCIALES', 'PASIVO_LIQUIDO_PAGAR'],
    });
});

afterEach(cleanup);

describe('CentralizacionRrhh', () => {
    it('avisa cuando faltan cuentas obligatorias por mapear', async () => {
        render(<CentralizacionRrhh />);
        expect(await screen.findByText(/Faltan 2 cuenta\(s\) obligatoria\(s\)/)).toBeDefined();
    });

    it('deshabilita el boton de centralizar si el mapeo esta incompleto', async () => {
        render(<CentralizacionRrhh />);
        const boton = await screen.findByRole('button', { name: /Centralizar período/ });
        expect(boton.hasAttribute('disabled')).toBe(true);
    });

    it('renderiza las categorias del mapeo contable', async () => {
        render(<CentralizacionRrhh />);
        expect(await screen.findByText('Gasto: Remuneraciones')).toBeDefined();
        expect(screen.getByText('Pasivo: Líquido por pagar')).toBeDefined();
    });

    it('muestra el boton de ayuda del modulo', async () => {
        render(<CentralizacionRrhh />);
        await screen.findByText('Gasto: Remuneraciones');
        expect(screen.getByTestId('ayuda-modulo-boton')).toBeDefined();
    });
});
