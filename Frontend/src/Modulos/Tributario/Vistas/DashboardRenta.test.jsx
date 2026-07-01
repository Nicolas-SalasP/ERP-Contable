import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';

// vi.mock ANTES de los imports del módulo bajo test
vi.mock('../../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));
vi.mock('../../../Componentes/AyudaModulo', () => ({ default: () => null }));
vi.mock('../../../Componentes/EstadoCarga', () => ({
    default: ({ cargando, error, mensajeCargando, onReintentar }) => {
        if (cargando) return <div data-testid="estado-carga">{mensajeCargando ?? 'Cargando...'}</div>;
        if (error) return (
            <div data-testid="estado-error">
                {error}
                {onReintentar && <button onClick={onReintentar}>Reintentar</button>}
            </div>
        );
        return null;
    },
}));
vi.mock('./ModalMapeoSII', () => ({
    default: ({ onClose }) => (
        <div data-testid="modal-mapeo">
            <button onClick={onClose}>Cerrar mapeo</button>
        </div>
    ),
}));
vi.mock('jspdf', () => ({
    default: vi.fn(() => ({
        setFontSize: vi.fn(),
        setTextColor: vi.fn(),
        text: vi.fn(),
        save: vi.fn(),
        lastAutoTable: { finalY: 100 },
    })),
}));
vi.mock('jspdf-autotable', () => ({ default: vi.fn() }));
vi.mock('@e965/xlsx', () => ({
    utils: { aoa_to_sheet: vi.fn(() => ({})), book_new: vi.fn(() => ({})), book_append_sheet: vi.fn() },
    writeFile: vi.fn(),
}));

import { api } from '../../../Configuracion/api';
import DashboardRenta from './DashboardRenta';

afterEach(cleanup);

const respuestaRentaOk = {
    success: true,
    data: {
        ingresos: { ventas_netas: 10000000, otros_ingresos: 500000 },
        gastos: { costos_directos: 4000000, depreciacion: 200000, remuneraciones: 800000 },
        correccion_monetaria: { aplica: true, ejecutada: false, periodos: 0, ingreso_cm: 0, gasto_cm: 0 },
        resultado: { base_imponible: 5500000, impuesto_renta: 1375000, tasa_impuesto: 25 },
        regimen_tributario: '14_A',
        regla_calculo: 'DEVENGADO',
        anio_comercial: 2025,
        anio_tributario: 2026,
        tasa_impuesto: 25,
        creditos: { ppm_acumulado: 0 },
    },
};

describe('DashboardRenta', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('muestra spinner de carga mientras se obtienen los datos', () => {
        api.get.mockReturnValue(new Promise(() => {})); // promesa pendiente
        render(<DashboardRenta />);
        expect(screen.getByTestId('estado-carga')).toBeTruthy();
    });

    it('muestra "Operación Renta" una vez que la api responde con datos', async () => {
        api.get.mockResolvedValue(respuestaRentaOk);
        render(<DashboardRenta />);
        await waitFor(() => {
            expect(screen.getByText('Operación Renta')).toBeTruthy();
        });
    });

    it('muestra el régimen tributario correcto para 14_A', async () => {
        api.get.mockResolvedValue(respuestaRentaOk);
        render(<DashboardRenta />);
        await waitFor(() => {
            expect(screen.getByText(/14 A \(General Semi Integrado\)/i)).toBeTruthy();
        });
    });

    it('muestra el aviso de cálculo orientativo', async () => {
        api.get.mockResolvedValue(respuestaRentaOk);
        render(<DashboardRenta />);
        await waitFor(() => {
            expect(screen.getByText(/Cálculo orientativo/i)).toBeTruthy();
        });
    });

    it('muestra estado de error cuando la api falla', async () => {
        api.get.mockResolvedValue({ success: false });
        render(<DashboardRenta />);
        await waitFor(() => {
            expect(screen.getByTestId('estado-error')).toBeTruthy();
        });
    });

    it('el botón "Mapear Cuentas" abre el ModalMapeoSII', async () => {
        api.get.mockResolvedValue(respuestaRentaOk);
        render(<DashboardRenta />);
        await waitFor(() => screen.getByText('Operación Renta'));
        fireEvent.click(screen.getByRole('button', { name: /Mapear Cuentas/i }));
        expect(screen.getByTestId('modal-mapeo')).toBeTruthy();
    });

    it('cerrar el ModalMapeoSII lo oculta del DOM', async () => {
        api.get.mockResolvedValue(respuestaRentaOk);
        render(<DashboardRenta />);
        await waitFor(() => screen.getByText('Operación Renta'));
        fireEvent.click(screen.getByRole('button', { name: /Mapear Cuentas/i }));
        expect(screen.getByTestId('modal-mapeo')).toBeTruthy();
        fireEvent.click(screen.getByRole('button', { name: /Cerrar mapeo/i }));
        expect(screen.queryByTestId('modal-mapeo')).toBeNull();
    });

    it('el botón "Guía Tributaria" abre el panel de información', async () => {
        api.get.mockResolvedValue(respuestaRentaOk);
        render(<DashboardRenta />);
        await waitFor(() => screen.getByText('Operación Renta'));
        fireEvent.click(screen.getByRole('button', { name: /Guía Tributaria/i }));
        expect(screen.getByText(/Ficha Técnica/i)).toBeTruthy();
    });
});
