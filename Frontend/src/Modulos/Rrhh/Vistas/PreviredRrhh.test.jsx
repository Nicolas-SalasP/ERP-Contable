import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup } from '@testing-library/react';

const permisosMock = vi.hoisted(() => ({ tienePermiso: () => true }));

vi.mock('../../../Contextos/Permisos', () => ({
    usePermisos: () => permisosMock,
}));

vi.mock('sweetalert2', () => ({
    default: { fire: vi.fn().mockResolvedValue({ isConfirmed: true }) },
}));

vi.mock('../Servicios/rrhhApi', () => ({
    default: {
        previred: {
            previsualizar: vi.fn(),
            descargar: vi.fn().mockResolvedValue({ success: true }),
        },
    },
}));

import PreviredRrhh from './PreviredRrhh';
import rrhhApi from '../Servicios/rrhhApi';

const preview = {
    periodo: '06/2026',
    total_trabajadores: 1,
    columnas: ['RUT', 'AFP_CODIGO', 'LIQUIDO_PAGAR'],
    filas: [{ RUT: '11111111', AFP_CODIGO: '09', LIQUIDO_PAGAR: '800000' }],
};

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    rrhhApi.previred.previsualizar.mockResolvedValue(preview);
});

afterEach(cleanup);

describe('PreviredRrhh', () => {
    it('renderiza encabezado y estado vacio inicial', async () => {
        render(<PreviredRrhh />);
        expect(screen.getByText('Archivo Previred')).toBeDefined();
        expect(screen.getByText(/Selecciona un período y previsualiza/i)).toBeDefined();
    });

    it('muestra los botones de previsualizar y descargar (con permiso)', () => {
        render(<PreviredRrhh />);
        expect(screen.getByText('Previsualizar')).toBeDefined();
        expect(screen.getByText('Descargar CSV')).toBeDefined();
    });

    it('previsualiza y arma la tabla con una fila por trabajador', async () => {
        render(<PreviredRrhh />);
        fireEvent.click(screen.getByText('Previsualizar'));

        expect(await screen.findByText('11111111')).toBeDefined();
        expect(screen.getByText('AFP_CODIGO')).toBeDefined();
        expect(screen.getByText(/1 trabajador\(es\)/)).toBeDefined();
        expect(rrhhApi.previred.previsualizar).toHaveBeenCalled();
    });

    it('descarga el CSV al pulsar el boton', async () => {
        render(<PreviredRrhh />);
        fireEvent.click(screen.getByText('Descargar CSV'));
        expect(rrhhApi.previred.descargar).toHaveBeenCalled();
    });

    it('oculta descargar sin permiso de procesar', () => {
        permisosMock.tienePermiso = () => false;
        render(<PreviredRrhh />);
        expect(screen.queryByText('Descargar CSV')).toBeNull();
    });

    it('muestra el boton de ayuda del modulo', () => {
        render(<PreviredRrhh />);
        expect(screen.getByTestId('ayuda-modulo-boton')).toBeDefined();
    });
});
