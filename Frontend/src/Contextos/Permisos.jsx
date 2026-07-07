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

    // Vacio o '*' = sin restriccion de plan; además del match exacto acepta subclave "key.algo" porque el backend casi nunca define la clave "bare" de un dominio grande, solo subclaves (rrhh.empleados, etc.) -- sin esto, RutaPorModulo bloqueaba rutas enteras a usuarios con permisos correctos.
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