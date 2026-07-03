import React from 'react';

export const usePermisos = () => {
    let user = {};
    try {
        const raw = localStorage.getItem('erp_user') || sessionStorage.getItem('erp_user');
        if (raw) user = JSON.parse(raw);
    } catch {
        user = {};
    }
    const permisosUsuario = user.permisos || [];
    const modulosUsuario = user.module_keys || [];

    // Verifica si tiene un permiso exacto
    const tienePermiso = (permiso) => {
        return permisosUsuario.includes(permiso);
    };

    // Verifica si tiene AL MENOS UNO de varios permisos (útil para ocultar un menú padre entero)
    const tieneAlgunPermiso = (arregloPermisos) => {
        return arregloPermisos.some(permiso => permisosUsuario.includes(permiso));
    };

    // Verifica si el plan del usuario incluye un modulo. Vacio o '*' = sin restriccion
    // de plan (admins locales sin SSO ven todo).
    //
    // ModuloPermisos::MAP (backend) casi nunca define una clave "bare" para
    // dominios grandes -- solo subclaves granulares (rrhh.empleados,
    // inventario.dashboard, sii.dte, etc). RutaPorModulo en App.jsx protege
    // rutas enteras con modulo="rrhh"/"inventario"/"sii" (la clave del
    // dominio, no de una subclave puntual), asi que ademas del match exacto
    // se acepta cualquier subclave "key.algo" -- si no, cualquier usuario con
    // module_keys real de plan (no vacio) queda bloqueado de esas rutas por
    // completo pese a tener todos los permisos correctos.
    const tieneModulo = (key) => {
        if (!modulosUsuario.length || modulosUsuario.includes('*')) return true;
        if (modulosUsuario.includes(key)) return true;
        return modulosUsuario.some((m) => m.startsWith(`${key}.`));
    };

    return { tienePermiso, tieneAlgunPermiso, tieneModulo, permisosUsuario };
};

export const Restringir = ({ permiso, children }) => {
    const { tienePermiso } = usePermisos();

    // Si tiene el permiso, renderiza lo que hay dentro. Si no, lo desaparece.
    if (tienePermiso(permiso)) {
        return <>{children}</>;
    }

    return null; 
};