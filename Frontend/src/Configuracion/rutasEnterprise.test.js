import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';

const readFrontendFile = (relativePath) => fs.readFileSync(
    path.resolve(process.cwd(), relativePath),
    'utf8'
);

describe('Fase 21 - rutas enterprise frontend', () => {
    it('mantiene alineado el menu de Glosario con una ruta real en App.jsx', () => {
        const app = readFrontendFile('src/App.jsx');
        const sidebar = readFrontendFile('src/Componentes/Estructura/BarraLateral.jsx');

        expect(sidebar).toContain("path: '/glosario'");
        expect(app).toContain("import Glosario from './Modulos/Glosario/Glosario'");
        expect(app).toContain('path="/glosario"');
        expect(app).toContain('<LayoutPrincipal><Glosario /></LayoutPrincipal>');
    });

    it('mantiene Inventario como modulo protegido y redirecciona la ruta base al dashboard', () => {
        const app = readFrontendFile('src/App.jsx');

        expect(app).toContain('path="/inventario"');
        expect(app).toContain('to="/inventario/dashboard"');
        expect(app).toContain('RutaProtegidaAlgunPermiso permisos={permisosLecturaInventario}');
    });

    it('mantiene permisos granulares para rutas sensibles SII/DTE en frontend', () => {
        const app = readFrontendFile('src/App.jsx');

        expect(app).toContain('permiso="sii.configuracion.ver"');
        expect(app).toContain('permiso="sii.certificado.ver"');
        expect(app).toContain('permiso="sii.caf.ver"');
        expect(app).toContain('permiso="sii.dte.ver"');
    });

    it('mantiene el sidebar sin forzar abierto el grupo activo por clase visual', () => {
        const sidebar = readFrontendFile('src/Componentes/Estructura/BarraLateral.jsx');

        expect(sidebar).not.toContain('openMenu === group.id || active');
        expect(sidebar).toContain('const open = openMenu === group.id');
        expect(sidebar).toContain('aria-expanded={open}');
        expect(sidebar).toContain('aria-controls={`menu-${group.id}`}');
    });
});
