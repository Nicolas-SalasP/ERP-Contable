const validarRutChile = (valor) => {
    const limpio = valor.replace(/[^0-9kK]/g, '').toUpperCase();
    if (limpio.length < 2) return false;

    const cuerpo = limpio.slice(0, -1);
    const dv = limpio.slice(-1);

    let suma = 0;
    let multiplo = 2;
    for (let i = cuerpo.length - 1; i >= 0; i--) {
        suma += multiplo * cuerpo.charAt(i);
        multiplo = (multiplo + 1 === 8) ? 2 : multiplo + 1;
    }

    const res = 11 - (suma % 11);
    let dvCalc = res === 11 ? '0' : res === 10 ? 'K' : res.toString();

    return dvCalc === dv;
};

const validarRucPeru = (valor) => {
    const limpio = valor.replace(/\D/g, '');
    if (limpio.length !== 11) return false;

    const factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    let suma = 0;
    for (let i = 0; i < 10; i++) {
        suma += parseInt(limpio.charAt(i)) * factores[i];
    }

    const residuo = suma % 11;
    const dvCalc = 11 - residuo;
    let dvFinal = dvCalc;
    if (dvCalc === 10) dvFinal = 0;
    if (dvCalc === 11) dvFinal = 1;

    return dvFinal === parseInt(limpio.charAt(10));
};

const validarCuitArgentina = (valor) => {
    const limpio = valor.replace(/\D/g, '');
    if (limpio.length !== 11) return false;

    const factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    let suma = 0;
    for (let i = 0; i < 10; i++) {
        suma += parseInt(limpio.charAt(i)) * factores[i];
    }

    const residuo = suma % 11;
    let dv = 11 - residuo;
    if (dv === 11) dv = 0;
    if (dv === 10) dv = 9;

    return dv === parseInt(limpio.charAt(10));
};

const validarNifSpania = (valor) => {
    const str = valor.replace(/[^0-9A-Z]/g, '').toUpperCase();
    if (!/^[0-9]{8}[A-Z]$/.test(str)) return false;

    const letras = "TRWAGMYFPDXBNJZSQVHLCKE";
    const numero = parseInt(str.slice(0, 8));
    const letra = str.slice(-1);

    return letras.charAt(numero % 23) === letra;
};

export const validarIdentificador = (numero, paisIso) => {
    if (!numero) return false;

    switch (paisIso) {
        case 'CL': return validarRutChile(numero);
        case 'PE': return validarRucPeru(numero);
        case 'AR': return validarCuitArgentina(numero);
        case 'ES': return validarNifSpania(numero);
        case 'US': return numero.replace(/\D/g, '').length === 9;
        case 'DK': return numero.replace(/\D/g, '').length === 8;
        case 'BR': return numero.replace(/\D/g, '').length === 14;
        case 'MX': {
            const mxLen = numero.replace(/[^0-9A-Z]/gi, '').length;
            return mxLen >= 12 && mxLen <= 13;
        }
        default: return true;
    }
};

export const formatearIdentificador = (numero, paisIso) => {
    const limpio = numero.replace(/[^0-9kK]/g, '').toUpperCase();
    if (!limpio) return '';

    switch (paisIso) {
        case 'CL': {
            if (limpio.length <= 1) return limpio;
            const cuerpo = limpio.slice(0, -1);
            const dv = limpio.slice(-1);
            return `${cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, ".")}-${dv}`;
        }

        case 'US':
            if (limpio.length > 2) return `${limpio.slice(0, 2)}-${limpio.slice(2, 9)}`;
            return limpio;

        case 'AR':
            if (limpio.length > 10) return `${limpio.slice(0, 2)}-${limpio.slice(2, 10)}-${limpio.slice(10, 11)}`;
            return limpio;

        case 'BR':
            return limpio;

        default: return numero;  // preservar input tal cual para países sin formato específico
    }
};

/**
 * Enmascara un identificador fiscal para su presentación en listados (Ley 21.719).
 * Para RUT chileno (CL): muestra los 2 primeros dígitos del cuerpo y el DV;
 * enmascara el resto con asteriscos, preservando los puntos del formato.
 *   Ejemplo: '12.345.678-5' → '12.***.**8-5'
 * Para otros países o entradas inválidas: devuelve el valor sin cambios.
 *
 * @param {string} valor  - Número de identificador (con o sin formato).
 * @param {string} pais   - ISO del país (por defecto 'CL').
 * @returns {string}
 */
export const enmascararIdentificador = (valor, pais = 'CL') => {
    if (!valor) return valor;

    if (pais !== 'CL') return valor;

    // Solo enmascarar RUTs válidos.
    if (!validarIdentificador(valor, 'CL')) return valor;

    // Obtener la representación canónica con puntos y guión.
    const formateado = formatearIdentificador(valor, 'CL');

    const guion = formateado.lastIndexOf('-');
    if (guion < 0) return valor;

    const cuerpo = formateado.slice(0, guion); // e.g. '12.345.678'
    const dvConGuion = formateado.slice(guion); // e.g. '-5'

    // Necesitamos al menos 3 caracteres en el cuerpo para mostrar inicio + final.
    if (cuerpo.length < 3) return formateado;

    const inicio = cuerpo.slice(0, 2);               // '12'
    const ultimoDigito = cuerpo.slice(-1);            // '8'
    // Centro: todo entre los 2 primeros chars y el último char del cuerpo.
    const centro = cuerpo.slice(2, -1);               // '.345.67'
    const centroEnmascarado = centro.replace(/\d/g, '*'); // '.***.**'

    return `${inicio}${centroEnmascarado}${ultimoDigito}${dvConGuion}`;
};
