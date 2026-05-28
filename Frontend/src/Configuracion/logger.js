const isProd = typeof import.meta !== 'undefined' && import.meta.env?.PROD;

export const logger = {
    log: isProd ? () => {} : (...args) => console.log(...args),
    debug: isProd ? () => {} : (...args) => console.debug(...args),
    info: isProd ? () => {} : (...args) => console.info(...args),
    warn: (...args) => console.warn(...args),
    error: (...args) => console.error(...args),
};

export default logger;
