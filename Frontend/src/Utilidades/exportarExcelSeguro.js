// Excel/Sheets interpreta como fórmula cualquier celda que empiece con =, +, -, @,
// tab o retorno de carro. Un dato de texto libre ingresado por un usuario (razón
// social, glosa, descripción) puede contener esos caracteres sin que sea intencional
// -- si se exporta tal cual, se ejecuta como fórmula al abrir el archivo en Excel.
// Mismo patrón que FacturaService::escaparCampoCsv() en el backend (PHP), replicado
// aquí porque las exportaciones xlsx del frontend no pasan por ese sanitizador.
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
