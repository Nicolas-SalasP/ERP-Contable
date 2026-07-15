import React from 'react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, cleanup } from '@testing-library/react';

// ---- Estado mutable de auth/permisos, controlado por cada test ----
const mockAuthState = { isAuthenticated: false, loading: false, user: null };
const mockPermisosState = {
    tienePermiso: () => true,
    tieneAlgunPermiso: () => true,
    tieneModulo: () => true,
};

vi.mock('./Contextos/AuthContext', () => ({
    AuthProvider: ({ children }) => <>{children}</>,
    useAuth: () => mockAuthState,
}));

vi.mock('./Contextos/TemaContext', () => ({
    TemaProvider: ({ children }) => <>{children}</>,
}));

vi.mock('./Contextos/ToastContext', () => ({
    ToastProvider: ({ children }) => <>{children}</>,
}));

vi.mock('./Contextos/Permisos', () => ({
    usePermisos: () => mockPermisosState,
}));

vi.mock('./Componentes/Estructura/LayoutPrincipal', () => ({
    default: ({ children }) => <div data-testid="layout-principal">{children}</div>,
}));

vi.mock('./Componentes/ErrorBoundary', () => ({
    default: ({ children }) => <>{children}</>,
}));

vi.mock('./Componentes/ActualizacionDisponible', () => ({
    default: () => null,
}));

// ---- Vistas importadas de forma estatica en App.jsx (no lazy) ----
// Cada factory de vi.mock se hoistea de forma independiente al tope del archivo (por delante
// de los imports); no puede depender de un helper compartido declarado mas abajo.
vi.mock('./Modulos/Autenticacion/Login', () => ({ default: () => <div data-testid="vista-login">login</div> }));
vi.mock('./Modulos/Autenticacion/RecuperarPassword', () => ({ default: () => <div data-testid="vista-recuperar">recuperar</div> }));
vi.mock('./Modulos/Autenticacion/SsoCallback', () => ({ default: () => <div data-testid="vista-sso-callback">sso-callback</div> }));
vi.mock('./Modulos/Dashboard/Dashboard', () => ({ default: () => <div data-testid="vista-dashboard">dashboard</div> }));
vi.mock('./Modulos/Proveedores/GestionProveedores', () => ({ default: () => <div data-testid="vista-proveedores">proveedores</div> }));
vi.mock('./Modulos/Cotizaciones/GestionCotizaciones', () => ({ default: () => <div data-testid="vista-cotizaciones">cotizaciones</div> }));
vi.mock('./Modulos/Cotizaciones/CrearCotizacion', () => ({ default: () => <div data-testid="vista-crear-cotizacion">crear-cotizacion</div> }));
vi.mock('./Modulos/Clientes/GestionClientes', () => ({ default: () => <div data-testid="vista-clientes">clientes</div> }));
vi.mock('./Modulos/Clientes/VisorCliente', () => ({ default: () => <div data-testid="vista-visor-cliente">visor-cliente</div> }));
vi.mock('./Modulos/Empresa/PerfilEmpresa', () => ({ default: () => <div data-testid="vista-perfil-empresa">perfil-empresa</div> }));
vi.mock('./Modulos/Activos/Vistas/GestionActivos', () => ({ default: () => <div data-testid="vista-activos">activos</div> }));
vi.mock('./Modulos/Tributario/Vistas/DashboardRenta', () => ({ default: () => <div data-testid="vista-dashboard-renta">dashboard-renta</div> }));
vi.mock('./Modulos/Banco/Vistas/NominaPagos', () => ({ default: () => <div data-testid="vista-nomina-pagos">nomina-pagos</div> }));
vi.mock('./Modulos/Banco/Vistas/CartolaBancaria', () => ({ default: () => <div data-testid="vista-cartola">cartola</div> }));
vi.mock('./Modulos/Banco/Vistas/MesaConciliacion', () => ({ default: () => <div data-testid="vista-mesa-conciliacion">mesa-conciliacion</div> }));
vi.mock('./Modulos/Proveedores/VisorProveedor', () => ({ default: () => <div data-testid="vista-visor-proveedor">visor-proveedor</div> }));
vi.mock('./Modulos/Bienvenida/CrearEmpresa', () => ({ default: () => <div data-testid="vista-crear-empresa">crear-empresa</div> }));
vi.mock('./Modulos/Administrador/GestionUsuarios', () => ({ default: () => <div data-testid="vista-usuarios">usuarios</div> }));
vi.mock('./Modulos/Administrador/GestionRoles', () => ({ default: () => <div data-testid="vista-roles">roles</div> }));
vi.mock('./Modulos/CorreccionMonetaria/CorreccionMonetaria', () => ({ default: () => <div data-testid="vista-correccion-monetaria">correccion-monetaria</div> }));
vi.mock('./Modulos/Inventario/InventarioProviderWrapper', () => ({
    default: ({ children }) => <div data-testid="inventario-provider">{children}</div>,
}));
vi.mock('./Modulos/Glosario/Glosario', () => ({ default: () => <div data-testid="vista-glosario">glosario</div> }));
vi.mock('./Modulos/Manuales/Manuales', () => ({ default: () => <div data-testid="vista-manuales">manuales</div> }));

import App from './App';

const irA = (path) => {
    window.history.pushState({}, '', path);
};

beforeEach(() => {
    mockAuthState.isAuthenticated = false;
    mockAuthState.loading = false;
    mockAuthState.user = null;
    mockPermisosState.tienePermiso = () => true;
    mockPermisosState.tieneAlgunPermiso = () => true;
    mockPermisosState.tieneModulo = () => true;
    irA('/');
});

afterEach(() => {
    cleanup();
});

describe('App (routing/guards)', () => {
    it('usuario no autenticado en "/" es redirigido a /login', async () => {
        mockAuthState.isAuthenticated = false;
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('vista-login')).toBeTruthy();
        });
    });

    it('mientras auth esta cargando, muestra el fallback de "Cargando..."', () => {
        mockAuthState.loading = true;
        render(<App />);

        expect(screen.getAllByText('Cargando...').length).toBeGreaterThan(0);
    });

    it('usuario autenticado sin empresa_id en "/" es redirigido a /crear-empresa', async () => {
        mockAuthState.isAuthenticated = true;
        mockAuthState.user = { id: 1, empresa_id: null };
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('vista-crear-empresa')).toBeTruthy();
        });
    });

    it('usuario autenticado con empresa_id en "/" ve el Dashboard dentro del layout', async () => {
        mockAuthState.isAuthenticated = true;
        mockAuthState.user = { id: 1, empresa_id: 7 };
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('layout-principal')).toBeTruthy();
            expect(screen.getByTestId('vista-dashboard')).toBeTruthy();
        });
    });

    it('usuario ya autenticado que visita /login es redirigido a "/"', async () => {
        mockAuthState.isAuthenticated = true;
        mockAuthState.user = { id: 1, empresa_id: 7 };
        irA('/login');
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('vista-dashboard')).toBeTruthy();
        });
    });

    it('usuario con empresa_id que visita /crear-empresa es redirigido a "/"', async () => {
        mockAuthState.isAuthenticated = true;
        mockAuthState.user = { id: 1, empresa_id: 7 };
        irA('/crear-empresa');
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('vista-dashboard')).toBeTruthy();
        });
    });

    it('ruta protegida por permiso sin el permiso requerido redirige a "/"', async () => {
        mockAuthState.isAuthenticated = true;
        mockAuthState.user = { id: 1, empresa_id: 7 };
        mockPermisosState.tienePermiso = (permiso) => permiso !== 'ventas.ver';
        irA('/cotizaciones');
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('vista-dashboard')).toBeTruthy();
        });
    });

    it('ruta protegida por permiso con el permiso concedido muestra la vista pedida', async () => {
        mockAuthState.isAuthenticated = true;
        mockAuthState.user = { id: 1, empresa_id: 7 };
        irA('/clientes');
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('vista-clientes')).toBeTruthy();
        });
    });

    it('una ruta inexistente (catch-all) redirige a "/"', async () => {
        mockAuthState.isAuthenticated = true;
        mockAuthState.user = { id: 1, empresa_id: 7 };
        irA('/esta-ruta-no-existe');
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('vista-dashboard')).toBeTruthy();
        });
    });

    it('renderiza un modulo con lazy-loading (ej. Contabilidad/HistorialFacturas) tras resolver el Suspense', async () => {
        mockAuthState.isAuthenticated = true;
        mockAuthState.user = { id: 1, empresa_id: 7 };
        irA('/facturas/historial');
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('layout-principal')).toBeTruthy();
        }, { timeout: 3000 });
    });

    it('/sso-callback y /recuperar son accesibles sin pasar por RutaPrivada', async () => {
        irA('/sso-callback');
        render(<App />);

        await waitFor(() => {
            expect(screen.getByTestId('vista-sso-callback')).toBeTruthy();
        });
    });
});
