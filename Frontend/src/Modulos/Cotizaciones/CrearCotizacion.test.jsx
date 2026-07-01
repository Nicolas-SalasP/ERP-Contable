import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { screen, fireEvent, waitFor, cleanup } from '@testing-library/react';
import { renderWithRouter, mockJsonResponse, setupFetchRouter, cleanTestEnv } from '../../test-utils';
import CrearCotizacion from './CrearCotizacion';

vi.mock('./Componentes/FilaItemCotizacion', () => ({
    default: ({ item, onUpdate, onRemove }) => (
        <tr data-testid={`fila-item-${item.id}`}>
            <td>
                <input
                    data-testid={`nombre-${item.id}`}
                    value={item.productoNombre}
                    onChange={(e) => onUpdate('productoNombre', e.target.value)}
                />
                <input
                    data-testid={`cantidad-${item.id}`}
                    type="number"
                    value={item.cantidad}
                    onChange={(e) => onUpdate('cantidad', e.target.value)}
                />
                <input
                    data-testid={`precio-${item.id}`}
                    type="number"
                    value={item.precioUnitario}
                    onChange={(e) => onUpdate('precioUnitario', e.target.value)}
                />
                <button onClick={onRemove}>Eliminar</button>
            </td>
        </tr>
    ),
}));

vi.mock('../../Componentes/AyudaModulo', () => ({ default: () => null }));

const CLIENTES = [
    { id: 1, razon_social: 'Empresa Test SpA', rut: '76.111.111-1' },
    { id: 2, razon_social: 'Otra Empresa Ltda', rut: '76.222.222-2' },
];

beforeEach(() => {
    cleanTestEnv();
});

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

function montar(clientes = CLIENTES) {
    setupFetchRouter({
        'GET /clientes': () => mockJsonResponse(200, { success: true, data: clientes }),
    });
    return renderWithRouter(<CrearCotizacion />);
}

describe('CrearCotizacion — render', () => {
    it('muestra el título Nueva Cotización', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText('Nueva Cotización')).toBeTruthy()
        );
    });

    it('muestra el campo de fecha de emisión', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText(/Fecha Emisión/i)).toBeTruthy()
        );
    });

    it('muestra el botón para agregar línea', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText(/AGREGAR LÍNEA/i)).toBeTruthy()
        );
    });

    it('muestra el botón de envío Generar Cotización', async () => {
        montar();
        await waitFor(() =>
            expect(screen.getByText(/Generar Cotización/i)).toBeTruthy()
        );
    });
});

describe('CrearCotizacion — clientes', () => {
    it('carga clientes desde GET /clientes al montar', async () => {
        montar();
        // Esperar que el componente se monte y llame a la API
        await waitFor(() => expect(screen.getByText('Nueva Cotización')).toBeTruthy());

        // Escribir en el input y abrir el dropdown
        const inputCliente = screen.getByPlaceholderText(/Buscar por RUT/i);
        fireEvent.change(inputCliente, { target: { value: 'Empresa' } });
        fireEvent.focus(inputCliente);

        await waitFor(() =>
            expect(screen.getByText('Empresa Test SpA')).toBeTruthy()
        );
    });

    it('muestra dropdown al escribir en el buscador de cliente', async () => {
        montar();
        await waitFor(() => expect(screen.getByText('Nueva Cotización')).toBeTruthy());

        const inputCliente = screen.getByPlaceholderText(/Buscar por RUT/i);
        fireEvent.focus(inputCliente);
        fireEvent.change(inputCliente, { target: { value: 'Otra' } });

        await waitFor(() =>
            expect(screen.getByText('Otra Empresa Ltda')).toBeTruthy()
        );
    });
});

describe('CrearCotizacion — items', () => {
    it('agrega una nueva fila al hacer click en Agregar Línea', async () => {
        montar();
        await waitFor(() => expect(screen.getByText(/AGREGAR LÍNEA/i)).toBeTruthy());

        const itemsAntes = screen.getAllByTestId(/fila-item-/);
        fireEvent.click(screen.getByText(/AGREGAR LÍNEA/i));

        await waitFor(() =>
            expect(screen.getAllByTestId(/fila-item-/).length).toBe(itemsAntes.length + 1)
        );
    });
});

describe('CrearCotizacion — validación y submit', () => {
    it('muestra modal de error si se envía sin seleccionar cliente', async () => {
        montar();
        await waitFor(() => expect(screen.getByText(/Generar Cotización/i)).toBeTruthy());

        fireEvent.click(screen.getByText(/Generar Cotización/i));

        await waitFor(() =>
            expect(screen.getByText(/Debe seleccionar un cliente/i)).toBeTruthy()
        );
    });

    it('llama POST /cotizaciones con cliente y detalles al confirmar', async () => {
        let bodyEnviado = null;

        setupFetchRouter({
            'GET /clientes': () => mockJsonResponse(200, { success: true, data: CLIENTES }),
            'POST /cotizaciones': (body) => {
                bodyEnviado = body;
                return mockJsonResponse(200, { success: true, data: { id: 99 } });
            },
        });

        renderWithRouter(<CrearCotizacion />);
        await waitFor(() => expect(screen.getByText('Nueva Cotización')).toBeTruthy());

        // Seleccionar cliente
        const inputCliente = screen.getByPlaceholderText(/Buscar por RUT/i);
        fireEvent.focus(inputCliente);
        fireEvent.change(inputCliente, { target: { value: 'Empresa Test' } });
        await waitFor(() => screen.getByText('Empresa Test SpA'));
        fireEvent.click(screen.getByText('Empresa Test SpA'));

        // Enviar
        fireEvent.click(screen.getByText(/Generar Cotización/i));

        await waitFor(() => expect(bodyEnviado).not.toBeNull(), { timeout: 2000 });
        expect(bodyEnviado.cliente_id).toBe(1);
        expect(Array.isArray(bodyEnviado.detalles)).toBe(true);
    });

    it('muestra modal de éxito con número de cotización tras submit exitoso', async () => {
        setupFetchRouter({
            'GET /clientes': () => mockJsonResponse(200, { success: true, data: CLIENTES }),
            'POST /cotizaciones': () => mockJsonResponse(200, { success: true, data: { id: 42 } }),
        });

        renderWithRouter(<CrearCotizacion />);
        await waitFor(() => expect(screen.getByText('Nueva Cotización')).toBeTruthy());

        // Seleccionar cliente
        const inputCliente = screen.getByPlaceholderText(/Buscar por RUT/i);
        fireEvent.focus(inputCliente);
        fireEvent.change(inputCliente, { target: { value: 'Empresa Test' } });
        await waitFor(() => screen.getByText('Empresa Test SpA'));
        fireEvent.click(screen.getByText('Empresa Test SpA'));

        fireEvent.click(screen.getByText(/Generar Cotización/i));

        await waitFor(() =>
            expect(screen.getByText(/Cotización Generada/i)).toBeTruthy(),
            { timeout: 2000 }
        );
        expect(screen.getByText(/cotización #42/i)).toBeTruthy();
    });
});
