import React from 'react';
import useEstadoSii from '../Hooks/useEstadoSii';

const ESTILO_POR_ESTADO = {
    BORRADOR:              'bg-gray-200 text-gray-800',
    FOLIO_RESERVADO:       'bg-gray-200 text-gray-800',
    XML_GENERADO:          'bg-blue-50 text-blue-700',
    FIRMADO:               'bg-blue-100 text-blue-800',
    ENVIADO_SII:           'bg-yellow-100 text-yellow-800 animate-pulse',
    EN_PROCESO_SII:        'bg-yellow-100 text-yellow-800 animate-pulse',
    ACEPTADO:              'bg-green-100 text-green-800',
    ACEPTADO_CON_REPAROS:  'bg-yellow-200 text-yellow-900',
    RECHAZADO:             'bg-red-100 text-red-800',
    REEMITIDO:             'bg-purple-100 text-purple-800',
    ANULADO_CON_NC:        'bg-purple-200 text-purple-900',
    ANULADO_FALLO_INTERNO: 'bg-red-200 text-red-900',
};

/**
 * F6.3 — Badge inline que muestra el estado SII de una factura, con polling
 * automatico via useEstadoSii. Reusable desde cualquier vista (listados,
 * detalles, modales).
 *
 * @param {{ facturaId: number }} props
 */
export function EstadoSiiBadge({ facturaId }) {
    const { data, cargando, error } = useEstadoSii(facturaId);

    if (cargando) {
        return (
            <span
                className="inline-block px-2 py-1 bg-gray-100 text-gray-500 text-xs rounded"
                data-testid="estado-sii-cargando"
            >
                Cargando...
            </span>
        );
    }

    if (error) {
        return (
            <span
                className="inline-block px-2 py-1 bg-red-50 text-red-700 text-xs rounded"
                title={error?.message ?? 'Error de conexion'}
                data-testid="estado-sii-error"
            >
                Error
            </span>
        );
    }

    if (!data?.tiene_dte) {
        return (
            <span
                className="inline-block px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded"
                data-testid="estado-sii-sin-dte"
            >
                Sin DTE
            </span>
        );
    }

    const estilo = ESTILO_POR_ESTADO[data.estado] ?? 'bg-gray-100 text-gray-600';

    return (
        <span
            className={`inline-block px-2 py-1 text-xs rounded ${estilo}`}
            title={data.estado_glosa_humana ?? data.estado}
            data-testid={`estado-sii-${data.estado}`}
        >
            {data.estado}
            {data.folio != null && (
                <span className="ml-1 opacity-75">#{data.folio}</span>
            )}
        </span>
    );
}

export default EstadoSiiBadge;
