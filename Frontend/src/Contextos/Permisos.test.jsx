import React from 'react';
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';
import { usePermisos, Restringir } from './Permisos';

afterEach(() => {
    cleanup();
    localStorage.clear();
    sessionStorage.clear();
});

// Componente auxiliar que expone el resultado del hook en el DOM
const ConsumidorPermisos = ({ permiso, arreglo, modulo }) => {
    const { tienePermiso, tieneAlgunPermiso, tieneModulo, permisosUsuario } = usePermisos();
    return (
        <div>
            {permiso !== undefined && (
                <span data-testid="tiene-permiso">{String(tienePermiso(permiso))}</span>
            )}
            {arreglo !== undefined && (
                <span data-testid="tiene-alguno">{String(tieneAlgunPermiso(arreglo))}</span>
            )}
            {modulo !== undefined && (
                <span data-testid="tiene-modulo">{String(tieneModulo(modulo))}</span>
            )}
            <span data-testid="cantidad">{permisosUsuario.length}</span>
        </div>
    );
};

const guardarUsuario = (datos) => {
    localStorage.setItem('erp_user', JSON.stringify(datos));
};

describe('usePermisos — tienePermiso', () => {
    it('retorna true cuando el permiso existe en la lista del usuario', () => {
        guardarUsuario({ permisos: ['clientes.ver', 'clientes.crear'] });
        render(<ConsumidorPermisos permiso="clientes.ver" />);
        expect(screen.getByTestId('tiene-permiso').textContent).toBe('true');
    });

    it('retorna false cuando el permiso no existe en la lista del usuario', () => {
        guardarUsuario({ permisos: ['clientes.ver'] });
        render(<ConsumidorPermisos permiso="clientes.eliminar" />);
        expect(screen.getByTestId('tiene-permiso').textContent).toBe('false');
    });

    it('retorna false cuando el usuario no tiene ningun permiso', () => {
        guardarUsuario({ permisos: [] });
        render(<ConsumidorPermisos permiso="clientes.ver" />);
        expect(screen.getByTestId('tiene-permiso').textContent).toBe('false');
    });

    it('retorna false cuando no hay datos de usuario en storage', () => {
        render(<ConsumidorPermisos permiso="cualquier.permiso" />);
        expect(screen.getByTestId('tiene-permiso').textContent).toBe('false');
    });

    it('lee desde sessionStorage si localStorage esta vacio', () => {
        sessionStorage.setItem('erp_user', JSON.stringify({ permisos: ['rrhh.ver'] }));
        render(<ConsumidorPermisos permiso="rrhh.ver" />);
        expect(screen.getByTestId('tiene-permiso').textContent).toBe('true');
    });
});

describe('usePermisos — tieneAlgunPermiso', () => {
    it('retorna true si al menos uno de los permisos del arreglo esta incluido', () => {
        guardarUsuario({ permisos: ['clientes.ver'] });
        render(<ConsumidorPermisos arreglo={['clientes.ver', 'clientes.crear']} />);
        expect(screen.getByTestId('tiene-alguno').textContent).toBe('true');
    });

    it('retorna false si ninguno de los permisos del arreglo esta incluido', () => {
        guardarUsuario({ permisos: ['rrhh.ver'] });
        render(<ConsumidorPermisos arreglo={['clientes.ver', 'clientes.crear']} />);
        expect(screen.getByTestId('tiene-alguno').textContent).toBe('false');
    });

    it('retorna false con arreglo vacio', () => {
        guardarUsuario({ permisos: ['clientes.ver'] });
        render(<ConsumidorPermisos arreglo={[]} />);
        expect(screen.getByTestId('tiene-alguno').textContent).toBe('false');
    });
});

describe('usePermisos — tieneModulo', () => {
    it('retorna true cuando los modulos incluyen "*" (sin restriccion de plan)', () => {
        guardarUsuario({ permisos: [], module_keys: ['*'] });
        render(<ConsumidorPermisos modulo="inventario" />);
        expect(screen.getByTestId('tiene-modulo').textContent).toBe('true');
    });

    it('retorna true cuando module_keys esta vacio (admin local sin SSO)', () => {
        guardarUsuario({ permisos: [], module_keys: [] });
        render(<ConsumidorPermisos modulo="cualquier_modulo" />);
        expect(screen.getByTestId('tiene-modulo').textContent).toBe('true');
    });

    it('retorna true cuando el modulo esta incluido en la lista del plan', () => {
        guardarUsuario({ permisos: [], module_keys: ['contabilidad', 'rrhh'] });
        render(<ConsumidorPermisos modulo="rrhh" />);
        expect(screen.getByTestId('tiene-modulo').textContent).toBe('true');
    });

    it('retorna false cuando el modulo no esta en la lista del plan', () => {
        guardarUsuario({ permisos: [], module_keys: ['contabilidad'] });
        render(<ConsumidorPermisos modulo="inventario" />);
        expect(screen.getByTestId('tiene-modulo').textContent).toBe('false');
    });
});

describe('usePermisos — permisosUsuario', () => {
    it('expone el arreglo de permisos del usuario', () => {
        guardarUsuario({ permisos: ['a', 'b', 'c'] });
        render(<ConsumidorPermisos />);
        expect(screen.getByTestId('cantidad').textContent).toBe('3');
    });

    it('devuelve arreglo vacio si el JSON del storage es invalido', () => {
        localStorage.setItem('erp_user', 'no-es-json');
        render(<ConsumidorPermisos />);
        expect(screen.getByTestId('cantidad').textContent).toBe('0');
    });
});

describe('Restringir — componente de acceso condicional', () => {
    it('renderiza children cuando el usuario tiene el permiso', () => {
        guardarUsuario({ permisos: ['facturas.ver'] });
        render(
            <Restringir permiso="facturas.ver">
                <span>contenido restringido</span>
            </Restringir>
        );
        expect(screen.getByText('contenido restringido')).toBeTruthy();
    });

    it('no renderiza nada cuando el usuario no tiene el permiso', () => {
        guardarUsuario({ permisos: [] });
        render(
            <Restringir permiso="facturas.eliminar">
                <span>contenido oculto</span>
            </Restringir>
        );
        expect(screen.queryByText('contenido oculto')).toBeNull();
    });

    it('renderiza sin crash cuando permisos esta vacio (sin usuario en storage)', () => {
        const { container } = render(
            <Restringir permiso="algo">
                <span>hijo</span>
            </Restringir>
        );
        expect(container.firstChild).toBeNull();
    });
});
