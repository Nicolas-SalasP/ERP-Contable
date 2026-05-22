import { describe, it, expect } from 'vitest';
import { validarIdentificador, formatearIdentificador } from './identificadores';

describe('validarIdentificador - Chile (RUT)', () => {
    describe('casos validos', () => {
        const rutsValidos = [
            '76.111.111-6',
            '76111111-6',
            '761111116',
            '11.111.111-1',
            '12345678-5',
            '7-8',
            '13.521.054-4',
            '76.543.210-3',
            '1000005-K',
        ];

        rutsValidos.forEach((rut) => {
            it(`acepta "${rut}"`, () => {
                expect(validarIdentificador(rut, 'CL')).toBe(true);
            });
        });

        it('acepta DV K en minuscula', () => {
            expect(validarIdentificador('1000005-k', 'CL')).toBe(true);
        });
    });

    describe('casos invalidos', () => {
        it('rechaza string vacio', () => {
            expect(validarIdentificador('', 'CL')).toBe(false);
        });

        it('rechaza null/undefined', () => {
            expect(validarIdentificador(null, 'CL')).toBe(false);
            expect(validarIdentificador(undefined, 'CL')).toBe(false);
        });

        it('rechaza RUT con DV incorrecto', () => {
            expect(validarIdentificador('76.111.111-5', 'CL')).toBe(false);
        });

        it('rechaza RUT con cuerpo demasiado corto', () => {
            expect(validarIdentificador('1', 'CL')).toBe(false);
        });

        it('rechaza RUT con solo numeros sin DV', () => {
            expect(validarIdentificador('7611111', 'CL')).toBe(false);
        });
    });

    describe('robustez', () => {
        it('ignora puntos y guiones del formato (toma el contenido)', () => {
            expect(validarIdentificador('76.111.111-6', 'CL')).toBe(
                validarIdentificador('761111116', 'CL')
            );
            expect(validarIdentificador('76.111.111-6', 'CL')).toBe(true);
        });

        it('acepta K en mayuscula y minuscula con el mismo resultado', () => {
            expect(validarIdentificador('1000005-K', 'CL')).toBe(
                validarIdentificador('1000005-k', 'CL')
            );
            expect(validarIdentificador('1000005-K', 'CL')).toBe(true);
        });
    });
});

describe('validarIdentificador - otros paises', () => {
    it('US: acepta 9 digitos', () => {
        expect(validarIdentificador('123456789', 'US')).toBe(true);
        expect(validarIdentificador('12345678', 'US')).toBe(false);
    });

    it('DK: acepta 8 digitos', () => {
        expect(validarIdentificador('12345678', 'DK')).toBe(true);
        expect(validarIdentificador('1234567', 'DK')).toBe(false);
    });

    it('BR: acepta 14 digitos', () => {
        expect(validarIdentificador('12345678901234', 'BR')).toBe(true);
        expect(validarIdentificador('1234567890123', 'BR')).toBe(false);
    });

    it('pais desconocido: pasa por defecto (true)', () => {
        expect(validarIdentificador('cualquier-cosa', 'XX')).toBe(true);
    });

    it('numero vacio: rechaza en cualquier pais', () => {
        expect(validarIdentificador('', 'CL')).toBe(false);
        expect(validarIdentificador('', 'US')).toBe(false);
    });
});

describe('formatearIdentificador - Chile (RUT)', () => {
    it('agrega puntos y guion al RUT', () => {
        expect(formatearIdentificador('761111111', 'CL')).toBe('76.111.111-1');
    });

    it('respeta el guion existente si el input ya tiene formato', () => {
        expect(formatearIdentificador('76.111.111-1', 'CL')).toBe('76.111.111-1');
    });

    it('uppercase la K del DV', () => {
        expect(formatearIdentificador('13521054k', 'CL')).toBe('13.521.054-K');
    });

    it('input vacio devuelve string vacio', () => {
        expect(formatearIdentificador('', 'CL')).toBe('');
    });

    it('input de un solo caracter devuelve igual', () => {
        expect(formatearIdentificador('1', 'CL')).toBe('1');
    });

    it('ignora caracteres no validos', () => {
        expect(formatearIdentificador('76abc111c111-1', 'CL')).toBe('76.111.111-1');
    });
});

describe('formatearIdentificador - otros paises', () => {
    it('US: formato XX-XXXXXXX', () => {
        expect(formatearIdentificador('123456789', 'US')).toBe('12-3456789');
    });

    it('AR: formato XX-XXXXXXXX-X', () => {
        expect(formatearIdentificador('12345678901', 'AR')).toBe('12-34567890-1');
    });

    it('pais desconocido: devuelve limpio sin formato', () => {
        expect(formatearIdentificador('12345', 'XX')).toBe('12345');
    });
});
