import React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import RhrrhResumen from './RhrrhResumen';

afterEach(() => {
    cleanup();
});

const renderConRouter = (ui) => render(<MemoryRouter>{ui}</MemoryRouter>);

describe('RhrrhResumen — encabezado', () => {
    it('siempre muestra el título del módulo', () => {
        renderConRouter(<RhrrhResumen datos={null} />);
        expect(screen.getByText('RRHH — Remuneraciones')).toBeDefined();
    });
});

describe('RhrrhResumen — sin pendientes', () => {
    it('muestra mensaje de ok cuando datos es null', () => {
        renderConRouter(<RhrrhResumen datos={null} />);
        expect(screen.getByText('Sin liquidaciones pendientes este mes')).toBeDefined();
    });

    it('muestra mensaje de ok cuando liquidaciones_pendientes es 0', () => {
        const datos = { liquidaciones_pendientes: 0, total_liquido_pendiente: 0 };
        renderConRouter(<RhrrhResumen datos={datos} />);
        expect(screen.getByText('Sin liquidaciones pendientes este mes')).toBeDefined();
    });

    it('no muestra el enlace a liquidaciones cuando no hay pendientes', () => {
        renderConRouter(<RhrrhResumen datos={null} />);
        expect(screen.queryByText('Ver liquidaciones')).toBeNull();
    });
});

describe('RhrrhResumen — con pendientes', () => {
    const datosPendientes = {
        liquidaciones_pendientes: 5,
        total_liquido_pendiente: 3450000,
        mes_referencia: '2025-07',
    };

    it('no muestra el mensaje de ok cuando hay pendientes', () => {
        renderConRouter(<RhrrhResumen datos={datosPendientes} />);
        expect(screen.queryByText('Sin liquidaciones pendientes este mes')).toBeNull();
    });

    it('muestra la cantidad de liquidaciones pendientes en plural', () => {
        renderConRouter(<RhrrhResumen datos={datosPendientes} />);
        expect(screen.getByText(/5 liquidaciones pendientes/)).toBeDefined();
    });

    it('usa singular cuando solo hay 1 liquidación pendiente', () => {
        const datos = { liquidaciones_pendientes: 1, total_liquido_pendiente: 800000 };
        renderConRouter(<RhrrhResumen datos={datos} />);
        expect(screen.getByText(/1 liquidación pendiente/)).toBeDefined();
    });

    it('muestra la etiqueta "Total a pagar"', () => {
        renderConRouter(<RhrrhResumen datos={datosPendientes} />);
        expect(screen.getByText('Total a pagar')).toBeDefined();
    });

    it('muestra el enlace a liquidaciones', () => {
        renderConRouter(<RhrrhResumen datos={datosPendientes} />);
        expect(screen.getByText('Ver liquidaciones')).toBeDefined();
    });

    it('muestra el mes de referencia formateado cuando está presente', () => {
        renderConRouter(<RhrrhResumen datos={datosPendientes} />);
        // "2025-07" debe convertirse a algo que contenga "julio" y "2025"
        const mesTexto = screen.getByText(/julio/i);
        expect(mesTexto).toBeDefined();
        expect(mesTexto.textContent).toContain('2025');
    });

    it('no muestra mes de referencia cuando no viene en datos', () => {
        const datos = { liquidaciones_pendientes: 2, total_liquido_pendiente: 1000000 };
        renderConRouter(<RhrrhResumen datos={datos} />);
        expect(screen.queryByText(/Mes:/)).toBeNull();
    });
});
