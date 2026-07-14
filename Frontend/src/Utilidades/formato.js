/** Formateadores de moneda compartidos (es-CL). Mismo patrón que Modulos/Rrhh/Utilidades/formato.js. */

const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });

/** Formatea un monto en pesos chilenos: $1.234.567 (CLP no tiene decimales). */
export const formatearMoneda = (valor) => clp.format(Number(valor || 0));

/**
 * Formatea una fecha-calendario (sin hora significativa) en formato es-CL DD-MM-AAAA.
 *
 * `new Date(iso)` interpreta cualquier string ISO como UTC (con o sin sufijo "Z" — Eloquent
 * serializa columnas `date`/`datetime` como "AAAA-MM-DDT00:00:00.000000Z"), y en timezones detrás
 * de UTC (Chile) eso corre la fecha mostrada un día para atrás. Se toman solo los primeros 10
 * caracteres (AAAA-MM-DD, sin importar qué sufijo de hora/timezone traiga) y se construye la fecha
 * con el constructor `new Date(anio, mes, dia)`, que es local, no UTC.
 */
export const formatFecha = (iso) => {
    if (!iso) return '—';
    const [anio, mes, dia] = iso.slice(0, 10).split('-').map(Number);
    if (!anio || !mes || !dia) return '—';
    const d = new Date(anio, mes - 1, dia);
    if (isNaN(d.getTime())) return '—';
    return d.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit', year: 'numeric' });
};
