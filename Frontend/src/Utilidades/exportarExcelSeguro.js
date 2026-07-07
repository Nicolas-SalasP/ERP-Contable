// Excel interpreta como fórmula cualquier celda que empiece con =,+,-,@,tab o retorno de carro (CSV injection); mismo patrón que FacturaService::escaparCampoCsv() del backend, replicado acá porque el export xlsx del frontend no pasa por ese sanitizador.
const CARACTERES_PELIGROSOS = ['=', '+', '-', '@', '\t', '\r'];

export const sanitizarCeldaExcel = (valor) => {
    if (typeof valor !== 'string' || valor.length === 0) return valor;
    return CARACTERES_PELIGROSOS.includes(valor[0]) ? `'${valor}` : valor;
};

/** Sanitiza todos los valores string de un array de filas (objetos planos) antes de pasarlas a XLSX.utils.json_to_sheet. */
export const sanitizarFilasExcel = (filas) => {
    if (!Array.isArray(filas)) return filas;
    return filas.map((fila) => {
        const filaSegura = {};
        for (const [clave, valor] of Object.entries(fila)) {
            filaSegura[clave] = sanitizarCeldaExcel(valor);
        }
        return filaSegura;
    });
};
