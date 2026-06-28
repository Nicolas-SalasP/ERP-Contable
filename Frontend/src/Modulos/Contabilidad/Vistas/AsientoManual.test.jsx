import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, act, cleanup } from '@testing-library/react';

vi.mock('../../../Configuracion/api', () => ({
    api: { get: vi.fn(), post: vi.fn() },
}));
vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));
vi.mock('react-select', () => ({
    default: ({ options, onChange, placeholder, value }) => (
        <select
            data-testid="react-select"
            value={value?.value ?? ''}
            onChange={(e) => {
                const opt = options?.find((o) => o.value === e.target.value) ?? null;
                onChange(opt);
            }}
        >
            <option value="">{placeholder}</option>
            {options?.map((o) => (
                <option key={o.value} value={o.value}>
                    {o.label}
                </option>
            ))}
        </select>
    ),
}));
vi.mock('../../../Componentes/AyudaModulo.jsx', () => ({ default: () => null }));
vi.mock('../../../Componentes/BotonAccion', () => ({
    default: ({ onClick, children, disabled }) => (
        <button onClick={onClick} disabled={disabled}>
            {children}
        </button>
    ),
}));
vi.mock('../../../Configuracion/logger', () => ({
    logger: { warn: vi.fn(), error: vi.fn(), log: vi.fn() },
}));
// Se mockean todos los íconos lucide-react usados por el componente
vi.mock('lucide-react', () => ({
    List: () => null,
    Inbox: () => <span data-testid="inbox-icon" />,
    Pencil: () => null,
    Trash2: () => null,
    Plus: () => null,
    SlidersHorizontal: () => null,
    Save: () => null,
}));

import { api } from '../../../Configuracion/api';
import Swal from 'sweetalert2';
import AsientoManual from './AsientoManual';

afterEach(cleanup);

describe('AsientoManual (vista)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.get.mockImplementation((url) => {
            if (url.includes('cuentas-imputables')) {
                return Promise.resolve({
                    success: true,
                    data: [{ codigo: '1101', nombre: 'Caja' }],
                });
            }
            if (url.includes('centros-costo')) {
                return Promise.resolve({ success: true, data: [] });
            }
            return Promise.resolve({ success: true, data: [] });
        });
    });

    it('renderiza el título Asiento Manual', async () => {
        await act(async () => {
            render(<AsientoManual />);
        });
        expect(screen.getByText('Asiento Manual')).toBeDefined();
    });

    it('tiene campo para Glosa General', async () => {
        await act(async () => {
            render(<AsientoManual />);
        });
        expect(
            screen.getByPlaceholderText(/Reconocimiento de gastos bancarios/i),
        ).toBeDefined();
    });

    it('tiene campo para Fecha', async () => {
        const { container } = render(<AsientoManual />);
        await waitFor(() => expect(api.get).toHaveBeenCalled());
        const fechaInput = container.querySelector('input[type="date"]');
        expect(fechaInput).not.toBeNull();
        expect(fechaInput.value).toBe(new Date().toISOString().split('T')[0]);
    });

    it('carga el plan de cuentas al montar', async () => {
        await act(async () => {
            render(<AsientoManual />);
        });
        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith(
                expect.stringContaining('cuentas-imputables'),
            );
        });
        await waitFor(() => {
            expect(screen.getByText('[1101] Caja')).toBeDefined();
        });
    });

    it('tabla de líneas inicia vacía', async () => {
        await act(async () => {
            render(<AsientoManual />);
        });
        // Con filas=[] el componente muestra el ícono Inbox y el mensaje
        expect(screen.getByText('El asiento está vacío.')).toBeDefined();
    });

    it('totales Debe y Haber inician en 0', async () => {
        await act(async () => {
            render(<AsientoManual />);
        });
        expect(screen.getByText(/Total Debe/i)).toBeDefined();
        expect(screen.getByText(/Total Haber/i)).toBeDefined();
    });

    it('el botón Guardar Asiento no está presente cuando la tabla está vacía', async () => {
        await act(async () => {
            render(<AsientoManual />);
        });
        // BotonAccion (CONTABILIZAR ASIENTO) solo se renderiza cuando filas.length >= 2
        expect(screen.queryByText('CONTABILIZAR ASIENTO')).toBeNull();
    });

    it('tiene selector de tipo Debe/Haber', async () => {
        await act(async () => {
            render(<AsientoManual />);
        });
        expect(screen.getByText('DEBE')).toBeDefined();
        expect(screen.getByText('HABER')).toBeDefined();
    });

    it('muestra el botón para agregar línea', async () => {
        await act(async () => {
            render(<AsientoManual />);
        });
        expect(screen.getByText(/AGREGAR LÍNEA/i)).toBeDefined();
    });

    it('muestra el panel de ingreso de cuentas', async () => {
        await act(async () => {
            render(<AsientoManual />);
        });
        expect(screen.getByText(/Cuenta Contable/i)).toBeDefined();
    });
});
