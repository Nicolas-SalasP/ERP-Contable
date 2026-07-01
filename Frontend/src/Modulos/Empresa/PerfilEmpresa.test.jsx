import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/react';

vi.mock('./Hooks/usePerfilEmpresa', () => ({
    usePerfilEmpresa: vi.fn(),
}));

vi.mock('../../Configuracion/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        upload: vi.fn(),
    },
    API_BASE_URL: 'http://localhost:8001/api',
}));

vi.mock('sweetalert2', () => ({
    default: {
        fire: vi.fn().mockResolvedValue({ isConfirmed: false }),
    },
}));

vi.mock('../../Utilidades/identificadores', () => ({
    validarIdentificador: vi.fn(() => true),
}));

vi.mock('../../Componentes/AyudaModulo', () => ({
    default: () => null,
}));

vi.mock('../../Componentes/EstadoCarga', () => ({
    default: ({ mensajeCargando }) => (
        <div data-testid="estado-carga">{mensajeCargando}</div>
    ),
}));

vi.mock('./Componentes/PerfilEmpresaGeneral', () => ({
    default: ({ formData, onSubmit }) => (
        <div data-testid="perfil-general">
            <span>{formData.razon_social}</span>
            <span>{formData.rut}</span>
            <button onClick={onSubmit} data-testid="btn-guardar">Guardar</button>
        </div>
    ),
}));

vi.mock('./Componentes/PerfilEmpresaBancos', () => ({
    default: () => <div data-testid="perfil-bancos">Bancos</div>,
}));

vi.mock('./Componentes/PerfilEmpresaCentros', () => ({
    default: () => <div data-testid="perfil-centros">Centros</div>,
}));

vi.mock('./Componentes/ModalBancoEdicion', () => ({
    default: () => null,
}));

vi.mock('./Componentes/ModalCentroEdicion', () => ({
    default: () => null,
}));

import PerfilEmpresa from './PerfilEmpresa';
import { usePerfilEmpresa } from './Hooks/usePerfilEmpresa';

afterEach(cleanup);

const mockHookBase = {
    formData: {
        rut: '76.123.456-7',
        razon_social: 'Empresa Test SpA',
        direccion: 'Av. Siempre Viva 742',
        email: 'test@empresa.cl',
        telefono: '+56 9 1234 5678',
        logo_path: '',
        color_primario: '#10b981',
        regimen_tributario: '14_D3',
    },
    setFormData: vi.fn(),
    bancos: [],
    setBancos: vi.fn(),
    centros: [],
    setCentros: vi.fn(),
    listaBancos: [],
    loading: false,
    recargar: vi.fn(),
};

beforeEach(() => {
    vi.clearAllMocks();
    usePerfilEmpresa.mockReturnValue(mockHookBase);
});

describe('PerfilEmpresa', () => {
    it('renderiza sin crash', () => {
        render(<PerfilEmpresa />);
        expect(screen.getByText('Mi Empresa')).toBeTruthy();
    });

    it('muestra spinner mientras loading es verdadero', () => {
        usePerfilEmpresa.mockReturnValue({ ...mockHookBase, loading: true });
        render(<PerfilEmpresa />);
        expect(screen.getByTestId('estado-carga')).toBeTruthy();
        expect(screen.getByText('Cargando perfil...')).toBeTruthy();
    });

    it('muestra razon_social y RUT de la empresa cuando loading es falso', () => {
        render(<PerfilEmpresa />);
        expect(screen.getByText('Empresa Test SpA')).toBeTruthy();
        expect(screen.getByText('76.123.456-7')).toBeTruthy();
    });

    it('muestra los tres tabs de navegación', () => {
        render(<PerfilEmpresa />);
        expect(screen.getByText(/Información & Logo/i)).toBeTruthy();
        expect(screen.getByText(/Cuentas Bancarias/i)).toBeTruthy();
        expect(screen.getByText(/Centros de Costo/i)).toBeTruthy();
    });

    it('al hacer click en "Cuentas Bancarias" muestra el componente de bancos', async () => {
        render(<PerfilEmpresa />);
        fireEvent.click(screen.getByText(/Cuentas Bancarias/i));
        await waitFor(() => {
            expect(screen.getByTestId('perfil-bancos')).toBeTruthy();
        });
    });
});
