import { describe, it, expect } from 'vitest';
import { formatPesos, formatNumero, formatFecha, nombreMes, colorEstado, MESES } from './formato';

describe('formato RRHH', () => {
    it('formatPesos muestra pesos chilenos sin decimales', () => {
        const out = formatPesos(1000000);
        expect(out).toContain('1.000.000');
        expect(out).toContain('$');
    });

    it('formatPesos trata null/undefined como 0', () => {
        expect(formatPesos(null)).toContain('0');
        expect(formatPesos(undefined)).toContain('0');
    });

    it('formatNumero respeta los decimales pedidos', () => {
        expect(formatNumero(1234.5, 2)).toContain('1.234');
        expect(formatNumero(1234.5, 2)).toContain('50');
    });

    it('formatFecha entrega fecha legible y maneja vacios', () => {
        expect(formatFecha('2026-06-15')).toContain('2026');
        expect(formatFecha(null)).toBe('—');
        expect(formatFecha('no-es-fecha')).toBe('—');
    });

    it('nombreMes traduce el numero de mes', () => {
        expect(nombreMes(6)).toBe('Junio');
        expect(nombreMes(1)).toBe('Enero');
        expect(MESES).toHaveLength(12);
    });

    it('colorEstado asigna clases por estado conocido y default', () => {
        expect(colorEstado('EMITIDA')).toContain('emerald');
        expect(colorEstado('ANULADA')).toContain('red');
        expect(colorEstado('TERMINADO')).toContain('slate');
        expect(colorEstado('ESTADO_RARO')).toContain('slate'); // fallback
    });
});
