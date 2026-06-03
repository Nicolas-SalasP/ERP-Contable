import React from 'react';

export const usePermisos = () => {
    const user = JSON.parse(localStorage.getItem('erp_user') || sessionStorage.getItem('erp_user') || '{}');
    const permisosUsuario = user.permisos || [];

    // Verifica si tiene un permiso exacto
    const tienePermiso = (permiso) => {
        return permisosUsuario.includes(permiso);
    };

    // Verifica si tiene AL MENOS UNO de varios permisos (útil para ocultar un menú padre entero)
    const tieneAlgunPermiso = (arregloPermisos) => {
        return arregloPermisos.some(permiso => permisosUsuario.includes(permiso));
    };

    return { tienePermiso, tieneAlgunPermiso, permisosUsuario };
};

export const Restringir = ({ permiso, children }) => {
    const { tienePermiso } = usePermisos();

    // Si tiene el permiso, renderiza lo que hay dentro. Si no, lo desaparece.
    if (tienePermiso(permiso)) {
        return <>{children}</>;
    }

    return null; 
};