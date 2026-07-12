/** Formateadores compartidos del módulo RRHH (es-CL). */

import { formatFecha } from '../../../Utilidades/formato';

const clp = new Intl.NumberFormat('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });

export const formatPesos = (valor) => clp.format(Number(valor || 0));

export const formatNumero = (valor, decimales = 2) =>
    new Intl.NumberFormat('es-CL', { minimumFractionDigits: decimales, maximumFractionDigits: decimales })
        .format(Number(valor || 0));

// El fix anterior local (agregar T00:00:00 solo si el string no traía hora) no cubría el caso real
// más común: Eloquent serializa columnas date/datetime como "AAAA-MM-DDT00:00:00.000000Z", que SÍ
// trae "T" pero sigue siendo UTC — se seguía mostrando un día atrás. Ver Utilidades/formato.js.
export { formatFecha };

export const MESES = [
    { valor: 1, label: 'Enero' }, { valor: 2, label: 'Febrero' }, { valor: 3, label: 'Marzo' },
    { valor: 4, label: 'Abril' }, { valor: 5, label: 'Mayo' }, { valor: 6, label: 'Junio' },
    { valor: 7, label: 'Julio' }, { valor: 8, label: 'Agosto' }, { valor: 9, label: 'Septiembre' },
    { valor: 10, label: 'Octubre' }, { valor: 11, label: 'Noviembre' }, { valor: 12, label: 'Diciembre' },
];

export const nombreMes = (mes) => MESES.find((m) => m.valor === Number(mes))?.label ?? mes;

/** Badge de estado: color por estado de liquidación/finiquito/contrato/empleado. */
export const colorEstado = (estado) => {
    const mapa = {
        BORRADOR: 'bg-slate-100 text-slate-700',
        EMITIDA: 'bg-emerald-100 text-emerald-700',
        PAGADA: 'bg-blue-100 text-blue-700',
        ANULADA: 'bg-red-100 text-red-700',
        FIRMADO: 'bg-emerald-100 text-emerald-700',
        PAGADO: 'bg-blue-100 text-blue-700',
        VIGENTE: 'bg-emerald-100 text-emerald-700',
        TERMINADO: 'bg-slate-200 text-slate-600',
        SUSPENDIDO: 'bg-amber-100 text-amber-700',
        ACTIVO: 'bg-emerald-100 text-emerald-700',
        INACTIVO: 'bg-slate-200 text-slate-600',
        LICENCIA: 'bg-amber-100 text-amber-700',
        PENDIENTE: 'bg-amber-100 text-amber-700',
        APROBADA: 'bg-emerald-100 text-emerald-700',
        RECHAZADA: 'bg-red-100 text-red-700',
    };
    return mapa[estado] || 'bg-slate-100 text-slate-700';
};
