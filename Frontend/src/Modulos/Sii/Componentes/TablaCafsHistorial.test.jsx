import React from 'react';
import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, cleanup, within } from '@testing-library/react';
import TablaCafsHistorial from './TablaCafsHistorial';

afterEach(cleanup);

const cafBase = (overrides = {}) => ({
    id: 1,
    tipo_dte: 33,
    folio_desde: 1,
    folio_hasta: 50,
    folio_actual: 5,
    folios_usados: 4,
    folios_huerfanos: 0,
    fecha_autorizacion: '2026-01-15',
    fecha_vencimiento: new Date(Date.now() + 90 * 86400000).toISOString().slice(0, 10),
    sii_idk: '300',
    estado: 'activo',
    ...overrides,
});

describe('TablaCafsHistorial', () => {
    it('renderiza filas con datos de cafs', () => {
        render(
            <TablaCafsHistorial cafs={[cafBase()]} cargando={false} filtroTipo={null} onCambiarFiltro={vi.fn()} onRevocar={vi.fn()} />
        );
        const row = screen.getByTestId('caf-row-1');
        expect(row).toBeDefined();
        // Scope a la fila para evitar colisiones con el dropdown.
        expect(within(row).getByText(/Factura Electronica/i)).toBeDefined();
    });

    it('dropdown filtro dispatcha onCambiarFiltro con tipo numerico', () => {
        const onCambiarFiltro = vi.fn();
        render(
            <TablaCafsHistorial cafs={[]} cargando={false} filtroTipo={null} onCambiarFiltro={onCambiarFiltro} onRevocar={vi.fn()} />
        );

        fireEvent.change(screen.getByTestId('filtro-tipo'), { target: { value: '39' } });

        expect(onCambiarFiltro).toHaveBeenCalledWith(39);
    });

    it('dropdown filtro con "Todos" dispatcha null', () => {
        const onCambiarFiltro = vi.fn();
        render(
            <TablaCafsHistorial cafs={[]} cargando={false} filtroTipo={33} onCambiarFiltro={onCambiarFiltro} onRevocar={vi.fn()} />
        );
        fireEvent.change(screen.getByTestId('filtro-tipo'), { target: { value: '' } });
        expect(onCambiarFiltro).toHaveBeenCalledWith(null);
    });

    it('boton revocar solo visible para cafs activos', () => {
        render(
            <TablaCafsHistorial
                cafs={[cafBase({ id: 1, estado: 'activo' }), cafBase({ id: 2, estado: 'agotado' }), cafBase({ id: 3, estado: 'revocado' })]}
                cargando={false}
                filtroTipo={null}
                onCambiarFiltro={vi.fn()}
                onRevocar={vi.fn()}
            />
        );
        expect(screen.queryByTestId('btn-revocar-1')).not.toBeNull();
        expect(screen.queryByTestId('btn-revocar-2')).toBeNull();
        expect(screen.queryByTestId('btn-revocar-3')).toBeNull();
    });

    it('click en revocar llama onRevocar con el caf', () => {
        const onRevocar = vi.fn();
        const caf = cafBase({ id: 7 });
        render(
            <TablaCafsHistorial cafs={[caf]} cargando={false} filtroTipo={null} onCambiarFiltro={vi.fn()} onRevocar={onRevocar} />
        );
        fireEvent.click(screen.getByTestId('btn-revocar-7'));
        expect(onRevocar).toHaveBeenCalledWith(caf);
    });

    it('empty state si lista vacia', () => {
        render(
            <TablaCafsHistorial cafs={[]} cargando={false} filtroTipo={null} onCambiarFiltro={vi.fn()} onRevocar={vi.fn()} />
        );
        expect(screen.getByTestId('historial-empty')).toBeDefined();
    });
});
