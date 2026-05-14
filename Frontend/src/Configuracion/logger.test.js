import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { logger } from './logger.js';

describe('logger', () => {
    let logSpy, debugSpy, infoSpy, warnSpy, errorSpy;

    beforeEach(() => {
        logSpy = vi.spyOn(console, 'log').mockImplementation(() => {});
        debugSpy = vi.spyOn(console, 'debug').mockImplementation(() => {});
        infoSpy = vi.spyOn(console, 'info').mockImplementation(() => {});
        warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
        errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    describe('comportamiento en desarrollo (vitest run = !isProd)', () => {
        it('log llama a console.log', () => {
            logger.log('hola');
            expect(logSpy).toHaveBeenCalledWith('hola');
        });

        it('debug llama a console.debug', () => {
            logger.debug('debug info');
            expect(debugSpy).toHaveBeenCalled();
        });

        it('info llama a console.info', () => {
            logger.info('info msg');
            expect(infoSpy).toHaveBeenCalled();
        });

        it('warn llama a console.warn', () => {
            logger.warn('cuidado');
            expect(warnSpy).toHaveBeenCalledWith('cuidado');
        });

        it('error llama a console.error', () => {
            const err = new Error('boom');
            logger.error('Failed:', err);
            expect(errorSpy).toHaveBeenCalledWith('Failed:', err);
        });
    });

    describe('contrato del logger', () => {
        it('expone los 5 metodos esperados', () => {
            expect(typeof logger.log).toBe('function');
            expect(typeof logger.debug).toBe('function');
            expect(typeof logger.info).toBe('function');
            expect(typeof logger.warn).toBe('function');
            expect(typeof logger.error).toBe('function');
        });

        it('puede recibir multiples argumentos como console', () => {
            logger.error('Multi', 'args', { obj: true }, 42);
            expect(errorSpy).toHaveBeenCalledWith('Multi', 'args', { obj: true }, 42);
        });

        it('error y warn efectivamente loggean (no son no-op)', () => {
            errorSpy.mockClear();
            warnSpy.mockClear();

            logger.error('test error');
            logger.warn('test warn');

            expect(errorSpy).toHaveBeenCalled();
            expect(warnSpy).toHaveBeenCalled();
        });
    });
});
