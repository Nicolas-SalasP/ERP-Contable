import { describe, it, expect } from 'vitest';
import { obtenerModulo } from '../../Utilidades/glosario';

const MODULOS_RRHH = [
    'empleadosRrhh', 'contratosRrhh', 'liquidacionesRrhh', 'finiquitosRrhh',
    'parametrosRrhh', 'centralizacionRrhh', 'previredRrhh',
];

describe('glosario RRHH', () => {
    it('cada vista RRHH tiene su entrada de ayuda en el glosario', () => {
        MODULOS_RRHH.forEach((id) => {
            const modulo = obtenerModulo(id);
            expect(modulo, `falta el modulo ${id}`).toBeTruthy();
            expect(modulo.titulo).toBeTruthy();
            expect(modulo.queEs).toBeTruthy();
            expect(modulo.id).toBe(id);
        });
    });

    it('las entradas RRHH traen pasos de uso y errores comunes', () => {
        MODULOS_RRHH.forEach((id) => {
            const modulo = obtenerModulo(id);
            expect(Array.isArray(modulo.comoUsar)).toBe(true);
            expect(modulo.comoUsar.length).toBeGreaterThan(0);
            expect(Array.isArray(modulo.errores)).toBe(true);
        });
    });
});
