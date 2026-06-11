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
        parametros: { listar: vi.fn() },
        indicadores: { listar: vi.fn(), crear: vi.fn().mockResolvedValue({ success: true }) },
        tablaImpuesto: { listar: vi.fn() },
    },
}));

import ParametrosRrhh from './ParametrosRrhh';
import rrhhApi from '../Servicios/rrhhApi';

const parametro = {
    id: 1, vigente_desde: '2026-01-01', fuente: 'Referencia 2026',
    afp_cotizacion_pct: 10, afp_sis_pct: 1.62, salud_fonasa_pct: 7,
    tope_imponible_uf: 90, afc_indefinido_trabajador_pct: 0.6,
    afc_indefinido_empleador_pct: 2.4, afc_plazo_fijo_empleador_pct: 3,
    afc_tope_imponible_uf: 135.2, imm: 539000,
    gratificacion_tope_mensual_factor: 4.75, cotizacion_adicional_empleador_pct: 1,
    mutual_cotizacion_basica_pct: 0.9,
    afp_comisiones_json: { Modelo: 0.58, Habitat: 1.27 },
};
const indicadores = [{ id: 1, anio: 2026, mes: 6, uf_valor: 39850, utm_valor: 71506, uta_valor: 858072 }];
const tabla = [{ id: 1, orden: 1, desde_utm: 0, hasta_utm: 13.5, tasa: 0, factor_deduccion_utm: 0 }];

beforeEach(() => {
    permisosMock.tienePermiso = () => true;
    rrhhApi.parametros.listar.mockResolvedValue({ data: [parametro] });
    rrhhApi.indicadores.listar.mockResolvedValue({ data: indicadores });
    rrhhApi.tablaImpuesto.listar.mockResolvedValue({ data: tabla });
});

afterEach(cleanup);

describe('ParametrosRrhh', () => {
    it('renderiza el encabezado y las pestañas', async () => {
        render(<ParametrosRrhh />);
        expect(await screen.findByText('Parámetros Previsionales')).toBeDefined();
        expect(screen.getByText('Previsionales')).toBeDefined();
        expect(screen.getByText('Indicadores UF/UTM')).toBeDefined();
        expect(screen.getByText('Tabla Impuesto Único')).toBeDefined();
    });

    it('muestra los valores legales vigentes', async () => {
        render(<ParametrosRrhh />);
        expect(await screen.findByText('Cotización AFP')).toBeDefined();
        expect(screen.getByText('Ingreso Mínimo (IMM)')).toBeDefined();
        // comisiones AFP del JSON
        expect(screen.getByText(/Modelo:/)).toBeDefined();
    });

    it('cambia a la pestaña de indicadores y lista el periodo', async () => {
        render(<ParametrosRrhh />);
        await screen.findByText('Cotización AFP');
        fireEvent.click(screen.getByText('Indicadores UF/UTM'));
        expect(await screen.findByText('Junio 2026')).toBeDefined();
    });

    it('ofrece registrar indicador con permiso de editar', async () => {
        render(<ParametrosRrhh />);
        await screen.findByText('Cotización AFP');
        fireEvent.click(screen.getByText('Indicadores UF/UTM'));
        expect(await screen.findByText('Registrar indicador')).toBeDefined();
    });

    it('muestra el boton de ayuda del modulo', async () => {
        render(<ParametrosRrhh />);
        expect(await screen.findByTestId('ayuda-modulo-boton')).toBeDefined();
    });
});
