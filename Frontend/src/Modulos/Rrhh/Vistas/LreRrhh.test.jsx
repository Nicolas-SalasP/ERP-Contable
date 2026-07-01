import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: vi.fn(() => ({ tienePermiso: () => true })),
}));
vi.mock('../Servicios/rrhhApi', () => ({
    default: {
        lre: {
            listar: vi.fn(),
            generar: vi.fn(),
            validar: vi.fn(),
            confirmar: vi.fn(),
            confirmarDt: vi.fn(),
            descargar: vi.fn(),
        },
    },
}));
vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({ default: () => null }));
vi.mock('../../../Componentes/Skeleton', () => ({
    TablaSkeleton: () => <tr><td>Cargando...</td></tr>,
}));
// EstadoVacio dentro de <tbody> necesita estar en <tr><td>
vi.mock('../../../Componentes/EstadoVacio', () => ({
    EstadoVacio: ({ mensaje }) => (
        <tr><td colSpan="6">{mensaje}</td></tr>
    ),
}));
// El componente usa m.valor (no m.value); se ajusta para que el selector renderice correctamente
vi.mock('../Utilidades/formato', () => ({
    MESES: [
        { valor: 1, label: 'Enero' },
        { valor: 2, label: 'Febrero' },
    ],
    nombreMes: (m) => ['', 'Enero', 'Febrero'][m] ?? String(m),
    formatFecha: (f) => f || '-',
}));

import { usePermisos } from '../../../Contextos/Permisos';
import rrhhApi from '../Servicios/rrhhApi';
import LreRrhh from './LreRrhh';

afterEach(cleanup);

describe('LreRrhh (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        usePermisos.mockReturnValue({ tienePermiso: () => true });
        rrhhApi.lre.listar.mockResolvedValue([]);
    });

    it('renderiza el título del módulo LRE', async () => {
        await act(async () => {
            render(<LreRrhh />);
        });
        expect(screen.getByText(/Libro de Remuneraciones Electrónico/i)).toBeDefined();
    });

    it('muestra estado vacío cuando no hay historial', async () => {
        rrhhApi.lre.listar.mockResolvedValue([]);
        await act(async () => {
            render(<LreRrhh />);
        });
        await waitFor(() => {
            expect(screen.getByText('Sin registros LRE aún.')).toBeDefined();
        });
    });

    it('muestra historial cuando listar retorna datos', async () => {
        rrhhApi.lre.listar.mockResolvedValue([
            {
                id: 10,
                anio: 2024,
                mes: 1,
                estado: 'GENERADO',
                cantidad_trabajadores: 5,
                numero_confirmacion_dt: null,
                confirmado_at: null,
            },
        ]);
        await act(async () => {
            render(<LreRrhh />);
        });
        await waitFor(() => {
            expect(screen.getByText('Enero 2024')).toBeDefined();
        });
    });

    it('BadgeEstado muestra GENERADO', async () => {
        rrhhApi.lre.listar.mockResolvedValue([
            {
                id: 11,
                anio: 2024,
                mes: 1,
                estado: 'GENERADO',
                cantidad_trabajadores: 3,
                numero_confirmacion_dt: null,
                confirmado_at: null,
            },
        ]);
        await act(async () => {
            render(<LreRrhh />);
        });
        await waitFor(() => {
            const badges = screen.getAllByText('GENERADO');
            expect(badges.length).toBeGreaterThan(0);
        });
    });

    it('BadgeEstado muestra VALIDADO', async () => {
        rrhhApi.lre.listar.mockResolvedValue([
            {
                id: 12,
                anio: 2024,
                mes: 2,
                estado: 'VALIDADO',
                cantidad_trabajadores: 7,
                numero_confirmacion_dt: null,
                confirmado_at: null,
            },
        ]);
        await act(async () => {
            render(<LreRrhh />);
        });
        await waitFor(() => {
            const badges = screen.getAllByText('VALIDADO');
            expect(badges.length).toBeGreaterThan(0);
        });
    });

    it('botón Actualizar llama a listar', async () => {
        rrhhApi.lre.listar.mockResolvedValue([]);
        await act(async () => {
            render(<LreRrhh />);
        });
        rrhhApi.lre.listar.mockClear();

        await act(async () => {
            fireEvent.click(screen.getByText('Actualizar'));
        });

        expect(rrhhApi.lre.listar).toHaveBeenCalledTimes(1);
    });

    it('sin permiso no renderiza acciones de generación', async () => {
        usePermisos.mockReturnValue({ tienePermiso: () => false });
        rrhhApi.lre.listar.mockResolvedValue([]);
        await act(async () => {
            render(<LreRrhh />);
        });
        // puedeProcesar=false → botón Generar LRE no se renderiza
        expect(screen.queryByText(/Generar LRE/i)).toBeNull();
    });

    it('muestra el selector de año actual', async () => {
        await act(async () => {
            render(<LreRrhh />);
        });
        const anioActual = new Date().getFullYear();
        expect(screen.getByDisplayValue(String(anioActual))).toBeDefined();
    });

    it('muestra el selector de mes', async () => {
        await act(async () => {
            render(<LreRrhh />);
        });
        const selectores = screen.getAllByRole('combobox');
        expect(selectores.length).toBeGreaterThanOrEqual(2);
        expect(screen.getByText('Enero')).toBeDefined();
        expect(screen.getByText('Febrero')).toBeDefined();
    });
});
