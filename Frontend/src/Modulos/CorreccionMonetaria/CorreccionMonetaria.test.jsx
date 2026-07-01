import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, fireEvent, cleanup } from '@testing-library/react';
import React from 'react';

// Mocks de módulos ANTES del import del componente bajo prueba
vi.mock('../../Configuracion/api', () => ({
    api: { get: vi.fn() },
}));

vi.mock('../../Componentes/AyudaModulo', () => ({
    default: () => <span data-testid="ayuda-modulo" />,
}));

vi.mock('./Componentes/TabIndicesIpc', () => ({
    default: ({ anioInicial }) => (
        <div data-testid="tab-indices">TabIndicesIpc {anioInicial}</div>
    ),
}));

vi.mock('./Componentes/TabConfiguracion', () => ({
    default: () => <div data-testid="tab-config">TabConfiguracion</div>,
}));

vi.mock('./Componentes/TabSimulador', () => ({
    default: () => <div data-testid="tab-simulador">TabSimulador</div>,
}));

vi.mock('./Componentes/TabHistorial', () => ({
    default: () => <div data-testid="tab-historial">TabHistorial</div>,
}));

import { api } from '../../Configuracion/api';
import CorreccionMonetaria from './CorreccionMonetaria';

const configActiva = {
    aplica_cm: true,
    modalidad: 'mensual',
    nombre_mes_cierre: null,
};

const configInactiva = {
    aplica_cm: false,
    modalidad: 'anual',
    nombre_mes_cierre: 'Diciembre',
};

describe('CorreccionMonetaria', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    afterEach(() => {
        cleanup();
    });

    it('renderiza sin crash', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        const { container } = render(<CorreccionMonetaria />);
        expect(container).toBeDefined();
    });

    it('muestra el título del módulo', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        render(<CorreccionMonetaria />);
        expect(screen.getByText('Corrección Monetaria')).toBeDefined();
    });

    it('muestra spinner/estado de carga al montar antes de que la API responda', () => {
        // La promesa no resuelve → loadingConfig = true
        api.get.mockReturnValue(new Promise(() => {}));

        render(<CorreccionMonetaria />);

        // Mientras carga, las insignias de config NO deben aparecer
        expect(screen.queryByText('Activa')).toBeNull();
        expect(screen.queryByText(/No aplica/)).toBeNull();
    });

    it('carga la configuración desde la API al montar', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        render(<CorreccionMonetaria />);

        await waitFor(() => {
            expect(api.get).toHaveBeenCalledWith('/correccion-monetaria/configuracion');
        });
    });

    it('muestra badge "Activa" cuando config.aplica_cm es true', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        render(<CorreccionMonetaria />);

        await waitFor(() => {
            expect(screen.getByText('Activa')).toBeDefined();
        });
    });

    it('muestra badge "No aplica (14D8)" cuando config.aplica_cm es false', async () => {
        api.get.mockResolvedValue({ success: true, data: configInactiva });

        render(<CorreccionMonetaria />);

        await waitFor(() => {
            expect(screen.getByText('No aplica (14D8)')).toBeDefined();
        });
    });

    it('muestra modalidad "Mensual" en el badge de modalidad', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        render(<CorreccionMonetaria />);

        await waitFor(() => {
            expect(screen.getByText(/Modalidad: Mensual/)).toBeDefined();
        });
    });

    it('muestra modalidad anual con nombre de mes cuando modalidad es anual', async () => {
        api.get.mockResolvedValue({ success: true, data: configInactiva });

        render(<CorreccionMonetaria />);

        await waitFor(() => {
            expect(screen.getByText(/Anual \(Diciembre\)/)).toBeDefined();
        });
    });

    it('no muestra badges de config si la API no retorna datos (success false)', async () => {
        api.get.mockResolvedValue({ success: false });

        render(<CorreccionMonetaria />);

        await waitFor(() => {
            expect(api.get).toHaveBeenCalled();
        });

        expect(screen.queryByText('Activa')).toBeNull();
        expect(screen.queryByText(/No aplica/)).toBeNull();
    });

    it('maneja error de API sin colapsar la UI', async () => {
        api.get.mockRejectedValue(new Error('500 Internal Server Error'));

        render(<CorreccionMonetaria />);

        await waitFor(() => {
            expect(api.get).toHaveBeenCalled();
        });

        // El título debe seguir visible
        expect(screen.getByText('Corrección Monetaria')).toBeDefined();
        // No se muestran badges de config
        expect(screen.queryByText('Activa')).toBeNull();
    });

    it('muestra las cuatro pestañas de navegación', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        render(<CorreccionMonetaria />);

        expect(screen.getByText('Índices IPC')).toBeDefined();
        expect(screen.getByText('Configuración')).toBeDefined();
        expect(screen.getByText('Simulador')).toBeDefined();
        expect(screen.getByText('Historial')).toBeDefined();
    });

    it('la pestaña activa por defecto es "Índices IPC"', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        render(<CorreccionMonetaria />);

        expect(screen.getByTestId('tab-indices')).toBeDefined();
        expect(screen.queryByTestId('tab-config')).toBeNull();
        expect(screen.queryByTestId('tab-simulador')).toBeNull();
        expect(screen.queryByTestId('tab-historial')).toBeNull();
    });

    it('cambia a la pestaña Configuración al hacer click', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        render(<CorreccionMonetaria />);

        fireEvent.click(screen.getByText('Configuración'));

        expect(screen.getByTestId('tab-config')).toBeDefined();
        expect(screen.queryByTestId('tab-indices')).toBeNull();
    });

    it('cambia a la pestaña Simulador al hacer click', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        render(<CorreccionMonetaria />);

        fireEvent.click(screen.getByText('Simulador'));

        expect(screen.getByTestId('tab-simulador')).toBeDefined();
    });

    it('cambia a la pestaña Historial al hacer click', async () => {
        api.get.mockResolvedValue({ success: true, data: configActiva });

        render(<CorreccionMonetaria />);

        fireEvent.click(screen.getByText('Historial'));

        expect(screen.getByTestId('tab-historial')).toBeDefined();
    });
});
