import { describe, it, expect } from 'vitest';
import { formatearMoneda, formatFecha } from './formato';

describe('formato compartido', () => {
    it('formatearMoneda muestra pesos chilenos sin decimales', () => {
        const out = formatearMoneda(1000000);
        expect(out).toContain('1.000.000');
        expect(out).toContain('$');
    });

    it('formatFecha entrega fecha legible y maneja vacios', () => {
        expect(formatFecha('2026-06-15')).toContain('2026');
        expect(formatFecha(null)).toBe('—');
        expect(formatFecha('no-es-fecha')).toBe('—');
    });

    it('formatFecha no corre un dia para atras con fecha simple YYYY-MM-DD (regresion)', () => {
        expect(formatFecha('2026-01-05')).toMatch(/^05.01.2026$/);
        expect(formatFecha('2026-12-31')).toMatch(/^31.12.2026$/);
    });

    // Bug real encontrado en QA Playwright 2026-07-12: Eloquent serializa columnas date/datetime
    // como "AAAA-MM-DDT00:00:00.000000Z" (UTC). El fix anterior solo cubria fechas SIN "T", asi
    // que este caso (el mas comun en la practica, viene de la API) seguia mostrando un dia atras
    // en timezones detras de UTC (Chile UTC-3/-4). Ver Modulos/Rrhh/Utilidades/formato.js.
    it('formatFecha no corre un dia para atras con datetime UTC de Eloquent (regresion QA)', () => {
        expect(formatFecha('2026-01-05T00:00:00.000000Z')).toMatch(/^05.01.2026$/);
        expect(formatFecha('2026-07-12T00:00:00.000000Z')).toMatch(/^12.07.2026$/);
    });

    it('formatFecha sigue funcionando con datetime completo (con hora local)', () => {
        expect(formatFecha('2026-06-15T10:30:00')).toMatch(/^15.06.2026$/);
    });
});
