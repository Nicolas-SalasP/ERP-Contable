/** Formateadores de moneda compartidos (es-CL). Mismo patrón que Modulos/Rrhh/Utilidades/formato.js. */

const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });

/** Formatea un monto en pesos chilenos: $1.234.567 (CLP no tiene decimales). */
export const formatearMoneda = (valor) => clp.format(Number(valor || 0));
