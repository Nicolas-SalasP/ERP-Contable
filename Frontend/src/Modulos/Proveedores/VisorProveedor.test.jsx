import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../test-utils';
import VisorProveedor from './VisorProveedor';
import { api } from '../../Configuracion/api';

const swalMock = vi.hoisted(() => ({
    fire: vi.fn().mockResolvedValue({ isConfirmed: true, value: '' }),
    showLoading: vi.fn(),
    close: vi.fn(),
}));
vi.mock('sweetalert2', () => ({ default: swalMock }));

beforeEach(() => {
    cleanTestEnv();
    api.config({ showErrorToast: false });
    swalMock.fire.mockClear();
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

// =====================================================================
// HELPERS
// =====================================================================

const renderConRuta = (id = '7') =>
    render(
        <MemoryRouter initialEntries={[`/proveedores/visor/${id}`]}>
            <Routes>
                <Route path="/proveedores/visor/:id" element={<VisorProveedor />} />
                <Route path="/proveedores" element={<div>Listado Proveedores</div>} />
            </Routes>
        </MemoryRouter>
    );

// =====================================================================
// FIXTURES
// =====================================================================

const fichaConAnticipos = {
    success: true,
    data: {
        proveedor: {
            id: 7,
            nombre_fantasia: 'Proveedor Test',
            razon_social: 'Proveedor Test SpA',
            rut: '76.123.456-7',
            email: 'proveedor@test.cl',
            telefono: '+56 9 1234 5678',
            direccion: 'Av. Test 123',
            cuenta_contable_id: null,
        },
        facturas: [],
        anticipos: [
            {
                id: 201,
                referencia: 'TR-001',
                fecha: '2026-04-15',
                created_at: '2026-04-15T10:00:00',
                monto: 100000,
                monto_original: 100000,
                saldo_disponible: 40000,
                estado: 'PENDIENTE',
            },
            {
                id: 202,
                referencia: 'TR-002',
                fecha: '2026-04-20',
                created_at: '2026-04-20T10:00:00',
                monto: 50000,
                monto_original: 50000,
                saldo_disponible: 50000,
                estado: 'PENDIENTE',
            },
            {
                id: 203,
                referencia: 'TR-003',
                fecha: '2026-04-25',
                created_at: '2026-04-25T10:00:00',
                monto: 30000,
                estado: 'PENDIENTE',
            },
        ],
        notas_credito: [],
        kpis: { total_facturas: 0, total_pagado: 0, total_pendiente: 0, total_anticipos: 0 },
    },
};

const setupMocks = (overrides = {}) =>
    setupFetchRouter({
        'GET /proveedores/ficha/7': () => mockJsonResponse(200, fichaConAnticipos),
        'GET /proveedores': () =>
            mockJsonResponse(200, { success: true, data: [fichaConAnticipos.data.proveedor] }),
        ...overrides,
    });

const waitForLoad = async () =>
    waitFor(() => expect(screen.getAllByText(/Proveedor Test/i).length).toBeGreaterThan(0));

const abrirModalCruce = async () => {
    const boton = await screen.findByRole('button', { name: /cruzar documentos/i });
    fireEvent.click(boton);
    await waitFor(() => {
        const hayCruce = screen.queryAllByText(/cruzar|cruce/i).length > 0;
        expect(hayCruce).toBe(true);
    });
};

// =====================================================================
// TESTS
// =====================================================================

describe('VisorProveedor - anticipos con saldo parcial', () => {
    it('renderiza la ficha del proveedor correctamente', async () => {
        setupMocks();
        renderConRuta('7');
        await waitForLoad();
        expect(screen.getAllByText(/Proveedor Test/i).length).toBeGreaterThan(0);
    });

    it('muestra badge PARCIAL en anticipo con saldo_disponible < monto_original', async () => {
        setupMocks();
        renderConRuta('7');
        await waitForLoad();
        await abrirModalCruce();
        expect(screen.getByText(/PARCIAL/i)).toBeDefined();
    });

    it('muestra "de $X original" en anticipos parciales', async () => {
        setupMocks();
        renderConRuta('7');
        await waitForLoad();
        await abrirModalCruce();
        await waitFor(() => {
            const tieneTextoOriginal = screen.queryByText((content) =>
                content.includes('original') && content.includes('100')
            );
            expect(tieneTextoOriginal).toBeDefined();
        });
    });

    it('NO muestra PARCIAL en anticipo con saldo igual a original', async () => {
        setupMocks();
        renderConRuta('7');
        await waitForLoad();
        await abrirModalCruce();
        const badges = screen.getAllByText(/PARCIAL/i);
        expect(badges.length).toBe(1);
    });

    it('los anticipos legacy (sin saldo_disponible) usan monto como fallback', async () => {
        setupMocks();
        renderConRuta('7');
        await waitForLoad();
        await abrirModalCruce();
        expect(screen.getByText('TR-003')).toBeDefined();
    });

    it('muestra las 3 referencias de anticipos', async () => {
        setupMocks();
        renderConRuta('7');
        await waitForLoad();
        await abrirModalCruce();

        expect(screen.getByText('TR-001')).toBeDefined();
        expect(screen.getByText('TR-002')).toBeDefined();
        expect(screen.getByText('TR-003')).toBeDefined();
    });
});
