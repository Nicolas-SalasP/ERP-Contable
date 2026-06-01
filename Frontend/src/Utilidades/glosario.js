/**
 * Glosario centralizado de modulos.
 *
 * Cada entrada describe un modulo en lenguaje simple para usuarios que NO
 * son contadores. Editar este archivo cambia el contenido en TODA la app
 * sin necesidad de tocar componentes.
 *
 * Estructura de cada modulo:
 *   id:        identificador unico (string)
 *   titulo:    nombre amigable
 *   icono:     emoji o caracter visual para la tarjeta del glosario
 *   resumen:   1-2 lineas, lo que aparece en el header de la modal
 *   queEs:     que es el modulo / concepto (parrafo)
 *   conceptos: array de { termino, definicion } - conceptos clave
 *   comoUsar:  array de pasos (strings) - como se usa el modulo
 *   errores:   array de { problema, solucion } - errores comunes
 *   tip:       (opcional) consejo destacado al final
 *
 * Tono: hablarle de tu, sin acentos argentinos (es-CL), simple y directo.
 * Evitar jerga contable salvo que se explique en el momento.
 */

export const glosario = {
    // ================================================================
    // CONTABILIDAD
    // ================================================================
    asientoManual: {
        id: 'asientoManual',
        titulo: 'Asiento Manual',
        icono: '📒',
        resumen: 'Registra ajustes contables o traspasos entre cuentas.',
        queEs:
            'Un asiento contable es un registro que dice "de donde sale" y "a donde entra" la plata. ' +
            'Cada asiento siempre tiene dos lados: el DEBE (lo que entra a una cuenta) y el HABER ' +
            '(lo que sale de otra). Ambos lados tienen que sumar exactamente lo mismo, sino el asiento ' +
            'no esta cuadrado. Eso se llama "partida doble" y es la regla de oro de la contabilidad.',
        conceptos: [
            {
                termino: 'Debe / Haber',
                definicion:
                    'Son las dos columnas del asiento. El Debe registra los movimientos de entrada ' +
                    '(aumentos de activo, disminuciones de pasivo). El Haber, los de salida. La suma de ambos debe ser igual.',
            },
            {
                termino: 'Cuenta contable',
                definicion:
                    'Una categoria donde se anota la plata. Ej: "Caja", "Banco Estado", "Sueldos por pagar". ' +
                    'Cada cuenta tiene un codigo y un nombre.',
            },
            {
                termino: 'Glosa',
                definicion:
                    'Una descripcion breve del movimiento. Ej: "Pago de luz mes de abril". ' +
                    'Sirve para entender despues que fue cada cosa.',
            },
            {
                termino: 'Centro de costo',
                definicion:
                    'Una subdivision de la empresa para saber a que area pertenece cada gasto. ' +
                    'Ej: "Ventas Santiago", "Bodega Concepcion". Es opcional pero ayuda al analisis.',
            },
        ],
        comoUsar: [
            'Selecciona la fecha contable del movimiento.',
            'Escribe una glosa general (ej: "Ajuste de inventario abril").',
            'Agrega filas: por cada una, elige una cuenta, marca si es Debe o Haber, y pon el monto.',
            'Asegurate de que el total del Debe sea igual al total del Haber. El sistema te avisa si no cuadra.',
            'Haz click en "Guardar Asiento". Queda registrado y aparece en el Libro Mayor.',
        ],
        errores: [
            {
                problema: 'No me deja guardar y dice que el asiento no cuadra.',
                solucion:
                    'Revisa que la suma del Debe sea exactamente igual a la del Haber. ' +
                    'Si hay diferencia, falta o sobra una linea.',
            },
            {
                problema: 'No aparece la cuenta que busco.',
                solucion:
                    'Probablemente la cuenta no esta marcada como "imputable". Ve a Plan de Cuentas ' +
                    'y verifica que la cuenta tenga el check de imputable activado. Solo las imputables aceptan movimientos.',
            },
        ],
        tip:
            'Si haces un asiento mal, NO lo edites directamente: registra otro asiento que lo anule ' +
            '(con Debe y Haber invertidos) y luego haz el correcto. Asi queda la trazabilidad.',
    },

    registroFactura: {
        id: 'registroFactura',
        titulo: 'Registro de Factura',
        icono: '🧾',
        resumen: 'Ingresa facturas de compra al sistema y genera su asiento automaticamente.',
        queEs:
            'Cuando un proveedor te emite una factura por algo que compraste, hay que registrarla en el sistema. ' +
            'El registro hace dos cosas: 1) Guarda la factura para tu control y para el SII. ' +
            '2) Genera automaticamente el asiento contable correspondiente (debita la cuenta de gasto/activo, ' +
            'acredita la cuenta del proveedor y separa el IVA).',
        conceptos: [
            {
                termino: 'Neto, IVA y Bruto',
                definicion:
                    'El Neto es el monto antes de impuestos. El IVA es el 19% que se suma. El Bruto es el total ' +
                    '(Neto + IVA). El sistema calcula automaticamente uno cuando ingresas el otro.',
            },
            {
                termino: 'Tipo de documento',
                definicion:
                    'Puede ser Factura (compra normal), Boleta, Nota de Credito (anula o reduce una factura emitida) ' +
                    'o Nota de Debito (aumenta una factura emitida).',
            },
            {
                termino: 'Cuenta destino',
                definicion:
                    'Es la cuenta contable que recibe el gasto o activo. Si compraste insumos, podria ser "Materiales de oficina". ' +
                    'Si compraste un equipo, podria ser "Activo fijo - Equipos".',
            },
            {
                termino: 'Codigo unico SII',
                definicion:
                    'Un numero unico que identifica la factura ante el SII. El sistema lo genera automaticamente ' +
                    'si no lo ingresas tu.',
            },
        ],
        comoUsar: [
            'Selecciona el proveedor que emitio la factura.',
            'Ingresa el numero de factura tal como aparece en el documento.',
            'Pon la fecha de emision y la fecha de vencimiento (cuando se tiene que pagar).',
            'Ingresa el monto neto. El IVA y el bruto se calculan solos.',
            'Elige la cuenta destino donde se imputa el gasto o activo.',
            'Opcionalmente asocia un centro de costo o un proyecto de activos.',
            'Guarda. El asiento contable queda hecho automaticamente.',
        ],
        errores: [
            {
                problema: 'Me sale "Configuracion Contable Incompleta".',
                solucion:
                    'Verifica que existan las cuentas de IVA (353350), del Proveedor (352105) y la cuenta de destino ' +
                    'en tu plan de cuentas. Si falta alguna, agregala desde Plan de Cuentas.',
            },
            {
                problema: 'El sistema dice "Numero de factura ya existe".',
                solucion:
                    'Ya registraste antes una factura con ese numero del mismo proveedor. ' +
                    'Verifica en el Historial. No se puede repetir.',
            },
            {
                problema: 'Quiero registrar una Nota de Credito.',
                solucion:
                    'Cambia el tipo de documento a "Nota de Credito" y asocia la factura original. ' +
                    'El monto no puede ser mayor al de la factura original.',
            },
        ],
        tip:
            'Si te equivocaste despues de guardar, NO borres la factura: anulala con el boton ' +
            '"Anular". Eso genera un asiento inverso y mantiene la trazabilidad para auditoria.',
    },

    cierreF29: {
        id: 'cierreF29',
        titulo: 'Cierre Tributario F29',
        icono: '📊',
        resumen: 'Calcula el IVA a pagar o devolver al SII cada mes.',
        queEs:
            'El Formulario 29 es la declaracion mensual de IVA ante el SII. Resume todo el IVA que pagaste en ' +
            'compras (IVA Credito Fiscal) y todo el IVA que cobraste en ventas (IVA Debito Fiscal). ' +
            'La diferencia es lo que tienes que pagar o lo que el SII te tiene que devolver.',
        conceptos: [
            {
                termino: 'IVA Debito Fiscal',
                definicion:
                    'El IVA que cobraste a tus clientes cuando vendiste. Es plata que recaudaste para el SII.',
            },
            {
                termino: 'IVA Credito Fiscal',
                definicion:
                    'El IVA que pagaste a tus proveedores cuando compraste. Es plata que el SII te reconoce como pagada.',
            },
            {
                termino: 'Remanente',
                definicion:
                    'Cuando el IVA Credito es mayor que el IVA Debito, queda un saldo a tu favor llamado remanente. ' +
                    'Se acumula y se usa el mes siguiente.',
            },
            {
                termino: 'PPM (Pago Provisional Mensual)',
                definicion:
                    'Un anticipo del impuesto a la renta que se paga mensualmente segun tus ventas. ' +
                    'Tambien se declara en el F29.',
            },
        ],
        comoUsar: [
            'Selecciona el periodo (mes y año) que vas a cerrar.',
            'Revisa que todas las facturas del mes esten registradas y contabilizadas.',
            'Haz click en "Simular Cierre". El sistema calcula los totales SIN guardar nada.',
            'Verifica los numeros. Si algo no calza, revisa el detalle de facturas.',
            'Si todo esta OK, confirma el cierre. Se genera el asiento y queda registrado el F29 del mes.',
        ],
        errores: [
            {
                problema: 'Los numeros no coinciden con los del SII.',
                solucion:
                    'Lo mas comun: hay facturas registradas pero NO contabilizadas (sin asiento). ' +
                    'Ve al Historial de Facturas y verifica que todas tengan asiento.',
            },
            {
                problema: 'El sistema dice "El periodo ya esta cerrado".',
                solucion:
                    'Cada periodo solo se cierra una vez. Si necesitas modificarlo, debes pedir a un superadmin ' +
                    'que reabra el periodo (esto es una accion delicada y queda registrada).',
            },
        ],
        tip:
            'Cierra el F29 ANTES del dia 12 del mes siguiente, que es el plazo del SII. ' +
            'Hacelo unos dias antes para tener tiempo de corregir errores.',
    },

    libroMayor: {
        id: 'libroMayor',
        titulo: 'Libro Mayor',
        icono: '📚',
        resumen: 'Muestra todos los movimientos contables por cuenta.',
        queEs:
            'El Libro Mayor es el resumen historico de cada cuenta contable. Por cada cuenta podes ver ' +
            'todos los movimientos (entradas y salidas), las fechas, las glosas, y el saldo acumulado. ' +
            'Es la herramienta para entender de donde vinieron los numeros que ves en el Balance.',
        conceptos: [
            {
                termino: 'Saldo deudor / acreedor',
                definicion:
                    'Si la cuenta tuvo mas Debe que Haber, queda con saldo deudor (positivo en cuentas de activo). ' +
                    'Si tuvo mas Haber, queda con saldo acreedor.',
            },
            {
                termino: 'Comprobante contable',
                definicion:
                    'Cada asiento tiene un numero unico (formato AATC######). Hacer click en el comprobante ' +
                    'te muestra el asiento completo y todas sus contrapartes.',
            },
        ],
        comoUsar: [
            'Selecciona el rango de fechas que quieres revisar.',
            'Elige una cuenta especifica o deja "Todas" para ver el general.',
            'Haz click en cualquier movimiento para ver el asiento completo.',
            'Podes exportar a Excel o PDF para presentar o archivar.',
        ],
        tip:
            'Para investigar diferencias, filtra por la cuenta sospechosa y compara el saldo final con el ' +
            'que aparece en el Balance. Si no calzan, hay un asiento mal hecho en el medio.',
    },

    // ================================================================
    // COMERCIAL
    // ================================================================
    cotizacion: {
        id: 'cotizacion',
        titulo: 'Cotizaciones',
        icono: '📝',
        resumen: 'Crea propuestas comerciales para clientes antes de facturar.',
        queEs:
            'Una cotizacion es una propuesta formal que le mandas a un cliente: "esto es lo que te puedo vender, ' +
            'a estos precios, validos hasta tal fecha". Si el cliente la acepta, la cotizacion se puede ' +
            'convertir directamente en factura sin reingresar los datos.',
        conceptos: [
            {
                termino: 'Estado de la cotizacion',
                definicion:
                    'BORRADOR (en preparacion), ENVIADA (mandada al cliente), APROBADA (cliente acepto), ' +
                    'RECHAZADA (cliente dijo que no), FACTURADA (ya se emitio factura), VENCIDA (paso la fecha de validez).',
            },
            {
                termino: 'Fecha de validez',
                definicion:
                    'Hasta cuando es valido el precio cotizado. Despues de esa fecha, el cliente no puede ' +
                    'reclamar que le respetes el precio.',
            },
            {
                termino: 'Numero de cotizacion',
                definicion:
                    'Un numero correlativo por empresa. Se genera automaticamente al crear la cotizacion.',
            },
        ],
        comoUsar: [
            'Selecciona el cliente. Si no esta, primero registra el cliente en el modulo Clientes.',
            'Agrega los productos o servicios con cantidad y precio unitario.',
            'Indica un descuento si corresponde.',
            'Pon la fecha de validez (por defecto 30 dias).',
            'Guarda como BORRADOR y revisa.',
            'Cuando este OK, descarga el PDF y mandasela al cliente.',
            'Si el cliente aprueba, cambia el estado a APROBADA.',
            'Despues podes convertirla directamente en factura desde el boton "Facturar".',
        ],
        errores: [
            {
                problema: 'No puedo cambiar el estado de la cotizacion.',
                solucion:
                    'Algunos cambios de estado tienen reglas. Por ejemplo, no podes pasar de FACTURADA a BORRADOR. ' +
                    'Si necesitas anular, crea una nota de credito sobre la factura asociada.',
            },
            {
                problema: 'Al convertir a factura me sale error.',
                solucion:
                    'Verifica que la cotizacion este en estado APROBADA y que el cliente tenga RUT valido. ' +
                    'El sistema crea un proveedor "proxy" con el RUT del cliente para soportar la facturacion de venta.',
            },
        ],
    },

    // ================================================================
    // ACTIVOS FIJOS
    // ================================================================
    activoFijo: {
        id: 'activoFijo',
        titulo: 'Activos Fijos',
        icono: '🏭',
        resumen: 'Registra y deprecia los bienes durables de la empresa.',
        queEs:
            'Los activos fijos son los bienes que la empresa USA para trabajar y va a tener por mas de un año: ' +
            'computadores, vehiculos, maquinaria, muebles. A diferencia de un gasto comun, no se descuentan ' +
            'todos en un solo mes: se distribuyen en el tiempo en que se "gastan" (depreciacion).',
        conceptos: [
            {
                termino: 'Depreciacion',
                definicion:
                    'Es la perdida de valor del activo con el tiempo. Si un computador cuesta $1.200.000 y dura ' +
                    '5 años, se "gasta" $20.000 por mes. Eso es la depreciacion mensual.',
            },
            {
                termino: 'Vida util',
                definicion:
                    'Cuantos meses se espera que dure el activo. El SII tiene tablas oficiales (computadores 60 meses, ' +
                    'vehiculos 84 meses, etc).',
            },
            {
                termino: 'Valor residual',
                definicion:
                    'El valor que se estima que tendra el activo al final de su vida util. Por convencion, ' +
                    'en Chile suele ser $1 (un peso simbolico).',
            },
            {
                termino: 'Valor libro',
                definicion:
                    'El valor que tiene el activo HOY en los libros: valor original menos la depreciacion acumulada.',
            },
        ],
        comoUsar: [
            'Crea el activo desde una factura de compra (boton "Convertir a activo") o manualmente.',
            'Define la fecha de adquisicion, vida util en meses y valor residual.',
            'Selecciona las cuentas contables: cuenta de activo, de depreciacion acumulada y de gasto.',
            'Cada mes ejecuta "Depreciar Mes" para registrar la cuota correspondiente.',
            'Cuando el activo se vende o se desecha, dalo de baja con el boton "Dar de Baja".',
        ],
        errores: [
            {
                problema: 'El sistema no me deja editar el valor de adquisicion.',
                solucion:
                    'Es a proposito. Una vez creado el activo, el valor de adquisicion no se puede cambiar ' +
                    'porque ya hay depreciaciones calculadas. Si esta mal, dalo de baja y crea uno nuevo.',
            },
            {
                problema: 'La depreciacion mensual no me cuadra con lo que esperaba.',
                solucion:
                    'Formula: (Valor adquisicion - Valor residual) / Vida util en meses. ' +
                    'Verifica los 3 valores en el detalle del activo.',
            },
        ],
        tip:
            'Para activos en construccion (ej: una bodega que estas armando con varias facturas), usa Proyectos de Activos. ' +
            'Cuando termines, lo "activas" y se vuelve un activo fijo normal con su depreciacion.',
    },

    proyectoActivo: {
        id: 'proyectoActivo',
        titulo: 'Proyecto de Activo',
        icono: '🏗️',
        resumen: 'Acumula costos de un activo en construccion antes de activarlo.',
        queEs:
            'Cuando un activo no se compra de una sola vez (ej: una bodega que se construye en 6 meses con ' +
            'multiples facturas), se usa un "Proyecto". Vas imputando cada factura al proyecto, y cuando esta ' +
            'terminado se "activa" y se convierte en un activo fijo formal que empieza a depreciarse.',
        conceptos: [
            {
                termino: 'En construccion',
                definicion:
                    'Estado inicial del proyecto. Acepta facturas vinculadas y todavia no se deprecia.',
            },
            {
                termino: 'Activado',
                definicion:
                    'Cuando el proyecto termina, se "activa". El total acumulado pasa a ser el valor de un nuevo activo fijo ' +
                    'que empieza a depreciarse desde ese momento.',
            },
        ],
        comoUsar: [
            'Crea el proyecto con un nombre descriptivo (ej: "Bodega Sur 2026").',
            'Define la vida util que tendra cuando se active.',
            'A medida que llegan facturas de la construccion, vinculalas al proyecto desde "Imputar Factura".',
            'Cuando termine, presiona "Activar Proyecto". Se crea el activo fijo con el valor acumulado.',
        ],
        errores: [
            {
                problema: 'No puedo eliminar el proyecto.',
                solucion:
                    'Solo se pueden eliminar proyectos EN_CONSTRUCCION que NO tengan facturas vinculadas. ' +
                    'Si tiene facturas, desvinculalas una por una primero.',
            },
        ],
    },

    // ================================================================
    // BANCO / TESORERIA
    // ================================================================
    conciliacion: {
        id: 'conciliacion',
        titulo: 'Conciliacion Bancaria',
        icono: '🏦',
        resumen: 'Compara movimientos del banco real con los registros del sistema.',
        queEs:
            'Conciliar es asegurarte de que lo que dice tu banco (la cartola que descargas del sitio del banco) ' +
            'coincide con lo que dice tu sistema contable. Si en el banco aparecen movimientos que no estan en el ' +
            'sistema (o al reves), hay que aclarar la diferencia.',
        conceptos: [
            {
                termino: 'Cartola bancaria',
                definicion:
                    'El listado oficial de movimientos que descargas del sitio web del banco. ' +
                    'Generalmente en formato Excel o PDF.',
            },
            {
                termino: 'Movimiento conciliado',
                definicion:
                    'Un movimiento del banco que ya fue "casado" con un asiento del sistema. ' +
                    'Esos no aparecen como diferencia.',
            },
            {
                termino: 'Diferencia de conciliacion',
                definicion:
                    'Movimientos que estan en un lado pero no en el otro. Pueden ser cheques que aun no se cobraron, ' +
                    'comisiones que el banco te cobro y no registraste, etc.',
            },
        ],
        comoUsar: [
            'Descarga la cartola del mes desde el sitio del banco.',
            'Subila al sistema en la seccion "Cartola Bancaria".',
            'En la mesa de conciliacion, vincula cada linea del banco con su asiento correspondiente.',
            'Para los movimientos que aparecen solo en el banco, crea el asiento contable que falta.',
            'Para los que aparecen solo en el sistema, verifica por que no estan en el banco (cheque sin cobrar?).',
        ],
        tip:
            'Concilia cada mes apenas tengas la cartola. Si dejas pasar 3-4 meses, las diferencias se vuelven ' +
            'casi imposibles de rastrear.',
    },

    // ================================================================
    // PROVEEDORES / COMERCIAL
    // ================================================================
    anticipoProveedor: {
        id: 'anticipoProveedor',
        titulo: 'Anticipos a Proveedores',
        icono: '💰',
        resumen: 'Registra pagos por adelantado y aplicalos a facturas futuras.',
        queEs:
            'Un anticipo es plata que le pagaste a un proveedor ANTES de que te emitiera la factura. ' +
            'Cuando la factura llegue, en vez de pagarla completa, "aplicas" el anticipo y solo pagas la diferencia. ' +
            'El sistema mantiene el saldo disponible del anticipo.',
        conceptos: [
            {
                termino: 'Saldo disponible',
                definicion:
                    'Lo que queda del anticipo sin aplicar todavia. Si pagaste $100.000 y aplicaste $40.000, ' +
                    'el saldo disponible es $60.000.',
            },
            {
                termino: 'Aplicacion parcial',
                definicion:
                    'Podes aplicar parte del anticipo a una factura y dejar el resto para otra futura. ' +
                    'El sistema lo trackea automaticamente.',
            },
        ],
        comoUsar: [
            'Registra el anticipo con: proveedor, monto, fecha y referencia (ej: numero de transferencia).',
            'Cuando llegue una factura del proveedor, ve a "Aplicar Anticipo".',
            'Selecciona el anticipo y la factura. Indica cuanto del anticipo aplicar.',
            'El saldo se descuenta automaticamente. Si llega a 0, el anticipo pasa a estado APLICADO.',
        ],
        errores: [
            {
                problema: 'No puedo aplicar mas que el saldo disponible.',
                solucion:
                    'Correcto, no se puede aplicar mas plata de la que tenes en el anticipo. ' +
                    'Si la factura es mayor, paga la diferencia normal por banco.',
            },
        ],
    },

    // ================================================================
    // EMPRESA
    // ================================================================
    crearEmpresa: {
        id: 'crearEmpresa',
        titulo: 'Crear Empresa',
        icono: '🏢',
        resumen: 'Da de alta una nueva empresa en el sistema.',
        queEs:
            'Cada empresa es un espacio de trabajo independiente: su propia contabilidad, sus propios usuarios, ' +
            'sus propios proveedores y clientes. Los datos NO se mezclan entre empresas (multi-tenant). ' +
            'Una sola cuenta de usuario puede tener acceso a varias empresas.',
        conceptos: [
            {
                termino: 'RUT empresa',
                definicion:
                    'El identificador tributario chileno. Tiene formato XX.XXX.XXX-X. El sistema valida el ' +
                    'digito verificador automaticamente.',
            },
            {
                termino: 'Razon social',
                definicion:
                    'El nombre legal completo de la empresa segun el SII. Ej: "Atlas Digital Tech SpA".',
            },
            {
                termino: 'Regimen tributario',
                definicion:
                    'El sistema bajo el cual la empresa declara impuestos. Los mas comunes en Chile: ' +
                    'Pro-Pyme General (14 D N3), Pro-Pyme Transparente (14 D N8), Renta Atribuida (14 A).',
            },
        ],
        comoUsar: [
            'Ingresa el RUT (con puntos y guion). El sistema valida que sea correcto.',
            'Pon la razon social tal como aparece en el e-RUT del SII.',
            'Selecciona el regimen tributario.',
            'Sube el logo (opcional) y configura el color principal de la empresa.',
            'Guarda. La empresa queda creada con el plan de cuentas estandar chileno.',
        ],
        errores: [
            {
                problema: 'RUT invalido.',
                solucion:
                    'El digito verificador no calza. Revisalo en el e-RUT del SII o usa una calculadora ' +
                    'de RUT online. El formato es XX.XXX.XXX-X.',
            },
            {
                problema: 'RUT ya existe.',
                solucion:
                    'Ya hay una empresa registrada con ese RUT. Si crees que es un error, contacta al admin del sistema.',
            },
        ],
    },

    // ================================================================
    // INVENTARIO
    // ================================================================
    inventario: {
        id: 'inventario',
        titulo: 'Inventario',
        icono: '📦',
        resumen:
            'Controla stock, bodegas, ubicaciones, lotes, reservas, preparación logística y trazabilidad operativa.',
        queEs:
            'El módulo de Inventario administra el ciclo logístico completo del stock físico: productos, bodegas, ' +
            'ubicaciones, movimientos, Kardex, lotes, reservas, toma física, valorización, alertas, picking, packing, ' +
            'despachos, devoluciones, reversas y auditoría operativa. Su responsabilidad es logística y trazable; ' +
            'no emite DTE ni ejecuta procesos tributarios.',
        conceptos: [
            {
                termino: 'Producto',
                definicion:
                    'Ítem administrado por el sistema. Puede tener SKU, unidad de medida, costo, stock, lotes, ' +
                    'ubicación y reglas de reposición.',
            },
            {
                termino: 'Bodega',
                definicion:
                    'Unidad física o lógica donde se almacena inventario. Permite separar stock por sucursal, ' +
                    'almacén o zona operativa.',
            },
            {
                termino: 'Ubicación',
                definicion:
                    'Posición específica dentro de una bodega. Mejora la trazabilidad del almacén y permite ' +
                    'ordenar el flujo operativo.',
            },
            {
                termino: 'Movimiento',
                definicion:
                    'Registro operativo de entrada, salida, traspaso o ajuste de stock. Cada movimiento alimenta ' +
                    'la trazabilidad y el Kardex.',
            },
            {
                termino: 'Kardex',
                definicion:
                    'Historial trazable de movimientos de inventario. Permite auditar entradas, salidas, ajustes, ' +
                    'costos y saldos.',
            },
            {
                termino: 'Lote',
                definicion:
                    'Agrupación trazable de unidades de un producto. Es clave para vencimientos, control sanitario, ' +
                    'alimentos, medicamentos o productos que requieren seguimiento por origen.',
            },
            {
                termino: 'Reserva',
                definicion:
                    'Stock comprometido para una operación futura. Reduce la disponibilidad libre sin descontar ' +
                    'físicamente el inventario hasta que corresponda.',
            },
            {
                termino: 'Toma física',
                definicion:
                    'Proceso de conteo real de inventario para comparar contra el stock del sistema y generar ' +
                    'diferencias o ajustes controlados.',
            },
            {
                termino: 'Valorización',
                definicion:
                    'Cálculo económico del inventario. Permite analizar costos, PMP, valor por producto, bodega o lote.',
            },
            {
                termino: 'Alertas y reposición',
                definicion:
                    'Reglas para detectar bajo stock, necesidad de reposición, productos críticos o posibles quiebres operativos.',
            },
            {
                termino: 'Picking',
                definicion:
                    'Preparación de productos desde bodega para un despacho o pedido. Controla cantidades solicitadas, ' +
                    'preparadas y pendientes.',
            },
            {
                termino: 'Packing',
                definicion:
                    'Proceso de embalaje posterior al picking. Organiza productos preparados en paquetes o unidades ' +
                    'listas para despacho.',
            },
            {
                termino: 'Despacho',
                definicion:
                    'Salida logística controlada de productos ya preparados y embalados. Permite cerrar el flujo operativo ' +
                    'sin mezclar la responsabilidad del módulo con facturación electrónica.',
            },
            {
                termino: 'Devoluciones/Reversas',
                definicion:
                    'Gestión de retornos y reversas operativas. Permite reingresar stock o anular efectos logísticos bajo ' +
                    'reglas controladas.',
            },
            {
                termino: 'Auditoría operativa',
                definicion:
                    'Registro de acciones relevantes del módulo para trazabilidad, control interno y revisión posterior.',
            },
            {
                termino: 'Eventos de integración',
                definicion:
                    'Eventos técnicos para comunicar acciones de inventario con otros módulos o sistemas externos, sin emitir ' +
                    'DTE ni ejecutar lógica tributaria desde Inventario.',
            },
        ],
        comoUsar: [
            'Revisa el Dashboard Inventario para ver KPIs, alertas y estado operativo general.',
            'Administra productos, bodegas y ubicaciones antes de registrar movimientos o procesos logísticos.',
            'Registra entradas, salidas, traspasos y ajustes mediante Movimientos; el Kardex mantiene la trazabilidad.',
            'Usa lotes, vencimientos y reservas cuando el producto requiera seguimiento o compromiso de stock.',
            'Ejecuta tomas físicas para comparar stock real contra stock del sistema y generar ajustes controlados.',
            'Gestiona picking, packing, despachos, devoluciones y reversas para cubrir el flujo logístico posterior a pedidos.',
            'Consulta reportes, valorización, auditoría operativa y eventos de integración para control gerencial y técnico.',
        ],
        errores: [
            {
                problema: 'El stock disponible no coincide con el stock físico.',
                solucion:
                    'Revisa Kardex, movimientos, reservas activas y tomas físicas recientes. Si corresponde, realiza un ajuste ' +
                    'controlado con motivo y observación.',
            },
            {
                problema: 'No aparece una opción del menú de Inventario.',
                solucion:
                    'Verifica los permisos asignados al rol del usuario. Algunas vistas requieren permisos específicos como ' +
                    'productos, reportes, alertas, reglas de reposición, picking o auditoría.',
            },
            {
                problema: 'Una reserva o despacho no permite completar el flujo.',
                solucion:
                    'Valida disponibilidad, lote, bodega, ubicación y estado operativo previo. El sistema bloquea acciones que ' +
                    'puedan romper trazabilidad o dejar stock inconsistente.',
            },
        ],
        tip:
            'Inventario debe mantenerse como módulo logístico/WMS. Puede integrarse con compras, ventas, pedidos u otros ' +
            'módulos mediante referencias y eventos, pero no debe emitir DTE ni ejecutar lógica SII desde este dominio.',
    },

    // ================================================================
    // PLAN DE CUENTAS
    // ================================================================
    planCuentas: {
        id: 'planCuentas',
        titulo: 'Plan de Cuentas',
        icono: '📂',
        resumen: 'Catalogo de todas las cuentas contables de la empresa.',
        queEs:
            'El plan de cuentas es la lista organizada de TODAS las cuentas contables que la empresa usa. ' +
            'Cada cuenta tiene un codigo (numero) y un nombre. Las cuentas se organizan jerarquicamente: ' +
            'las "padre" agrupan a las "hijas". Solo las cuentas marcadas como "imputables" pueden recibir movimientos.',
        conceptos: [
            {
                termino: 'Cuenta imputable',
                definicion:
                    'Una cuenta donde se pueden anotar movimientos directamente. Por ejemplo "Caja" es imputable. ' +
                    '"Activo Corriente" no lo es: es solo una categoria que agrupa.',
            },
            {
                termino: 'Tipo de cuenta',
                definicion:
                    'Activo, Pasivo, Patrimonio, Ingreso o Gasto. Define como se comporta la cuenta en los reportes.',
            },
            {
                termino: 'Cuenta de IVA',
                definicion:
                    'Cuentas especiales para los impuestos: IVA Credito (353350) y IVA Debito (213500). ' +
                    'El sistema las usa automaticamente al registrar facturas.',
            },
        ],
        comoUsar: [
            'El sistema viene con el plan de cuentas estandar chileno precargado.',
            'Para agregar una cuenta nueva: codigo (siguiendo la jerarquia), nombre, tipo, y si es imputable.',
            'Para desactivar una cuenta que ya no usas: marcala como inactiva (no se borra para mantener historial).',
        ],
        tip:
            'NO borres cuentas que ya tengan movimientos historicos. Si no las usas mas, desactivalas. ' +
            'Borrarlas romperia los reportes historicos.',
    },

    // ================================================================
    // MODULOS SECUNDARIOS
    // ================================================================
    anulacion: {
        id: 'anulacion',
        titulo: 'Anulacion de Documentos',
        icono: '🚫',
        resumen: 'Anula facturas y asientos contables manteniendo trazabilidad.',
        queEs:
            'Cuando una factura o un asiento se ingreso por error, NO se borra del sistema (eso romperia la auditoria). ' +
            'En su lugar, se "anula": el sistema genera un asiento inverso que cancela el efecto contable, ' +
            'pero el documento original queda en el historial para que el SII pueda auditarlo.',
        conceptos: [
            {
                termino: 'Asiento inverso',
                definicion:
                    'Un asiento que tiene los mismos montos pero con Debe y Haber cambiados. ' +
                    'Si el original era "Debe Caja 1000 / Haber Banco 1000", el inverso es "Debe Banco 1000 / Haber Caja 1000". ' +
                    'Asi se "deshace" el efecto.',
            },
            {
                termino: 'Documento anulado',
                definicion:
                    'Estado del documento despues de anular. Sigue visible en listados pero marcado claramente como anulado. ' +
                    'No se puede pagar, modificar ni asociar a otros movimientos.',
            },
        ],
        comoUsar: [
            'Busca el documento que quieres anular (factura, asiento, etc).',
            'Verifica que es el correcto, porque la anulacion deja huella.',
            'Ingresa un motivo de anulacion claro (ej: "Factura duplicada por error").',
            'Confirma. El sistema genera el asiento inverso y marca el documento como anulado.',
        ],
        errores: [
            {
                problema: 'No me deja anular porque ya esta pagada.',
                solucion:
                    'Las facturas pagadas necesitan que primero revertas el pago. Anula el pago, despues recien podes anular la factura.',
            },
        ],
        tip:
            'Antes de anular algo importante, descarga el PDF o saca un pantallazo. Aunque queda historial, ' +
            'tener tu propia copia ayuda si despues hay que justificar el motivo.',
    },

    cartolaBancaria: {
        id: 'cartolaBancaria',
        titulo: 'Cartola Bancaria',
        icono: '🧾',
        resumen: 'Importa el detalle de movimientos del banco al sistema.',
        queEs:
            'La cartola es el listado de movimientos de tu cuenta corriente que descargas del sitio del banco. ' +
            'Importarla al sistema te permite ver los movimientos lado a lado con los asientos contables, ' +
            'y conciliar mas rapido (saber que movimiento del banco corresponde a que asiento del sistema).',
        conceptos: [
            {
                termino: 'Formato de importacion',
                definicion:
                    'Cada banco entrega la cartola en un formato distinto (Excel, CSV, PDF). El sistema acepta los ' +
                    'mas comunes (Excel y CSV). Si tu banco solo entrega PDF, hay que convertirlo o transcribirlo.',
            },
            {
                termino: 'Movimiento de cargo / abono',
                definicion:
                    'Cargo: plata que SALE de tu cuenta (pago, transferencia que enviaste, comision). ' +
                    'Abono: plata que ENTRA (cobro, transferencia que recibiste, intereses).',
            },
        ],
        comoUsar: [
            'Entra al sitio web de tu banco y descarga la cartola del mes en Excel.',
            'En el sistema, ve a "Cartola Bancaria" y haz click en "Importar".',
            'Selecciona la cuenta bancaria del sistema y el archivo descargado.',
            'El sistema valida el formato y te muestra previsualizacion.',
            'Confirma. Los movimientos quedan disponibles para conciliar.',
        ],
        errores: [
            {
                problema: 'El sistema dice que no reconoce el formato.',
                solucion:
                    'Verifica que el archivo sea Excel (.xlsx) o CSV. Si es PDF, abrilo en Excel y guarda como xlsx primero.',
            },
            {
                problema: 'Aparecen movimientos duplicados.',
                solucion:
                    'Probablemente importaste el mismo periodo dos veces. El sistema deberia detectarlo, pero si paso, ' +
                    'usa "Limpiar duplicados" en el modulo o contacta soporte.',
            },
        ],
    },

    crearCotizacion: {
        id: 'crearCotizacion',
        titulo: 'Crear Cotizacion',
        icono: '📝',
        resumen: 'Formulario paso a paso para armar una nueva propuesta comercial.',
        queEs:
            'Esta es la pantalla donde creas una cotizacion nueva desde cero. Tiene tres partes: ' +
            'datos del cliente, productos/servicios cotizados, y condiciones (validez, descuento, observaciones). ' +
            'Al guardar, queda en estado BORRADOR y podes seguir editandola hasta enviarla.',
        conceptos: [
            {
                termino: 'Producto vs Servicio',
                definicion:
                    'Para el sistema, ambos son items con cantidad y precio. La diferencia es solo informativa. ' +
                    'Si es producto, podes vincularlo al inventario para descontar stock al facturar.',
            },
            {
                termino: 'Descuento global vs por linea',
                definicion:
                    'Descuento global: un porcentaje aplicado al total. ' +
                    'Por linea: precio diferente para algunos items. Usa lo que sea mas claro para el cliente.',
            },
        ],
        comoUsar: [
            'Selecciona el cliente. Si no esta registrado, agregalo desde el modulo Clientes primero.',
            'Agrega cada producto/servicio con cantidad y precio unitario.',
            'Si necesitas, aplica descuento global o por linea.',
            'Pon la fecha de validez (cuanto tiempo respetaras los precios).',
            'Guarda como BORRADOR. Despues podes descargar el PDF y enviarlo al cliente.',
        ],
    },

    dashboardRenta: {
        id: 'dashboardRenta',
        titulo: 'Dashboard de Renta',
        icono: '📈',
        resumen: 'Resumen del impuesto a la renta anual y proyecciones.',
        queEs:
            'El impuesto a la renta es lo que la empresa paga sobre sus utilidades cada año. ' +
            'Este dashboard te muestra cuanto llevas pagado en PPM mensuales, cuanto seria la utilidad estimada del año, ' +
            'y proyecciones del impuesto que tendras que pagar en abril.',
        conceptos: [
            {
                termino: 'PPM (Pago Provisional Mensual)',
                definicion:
                    'Un anticipo del impuesto a la renta que se paga cada mes en el F29. ' +
                    'Es un porcentaje sobre las ventas del mes. En abril, se descuenta de lo que toca pagar.',
            },
            {
                termino: 'Utilidad antes de impuestos',
                definicion:
                    'La diferencia entre ingresos y gastos del año, antes de aplicar el impuesto a la renta. ' +
                    'Es la base sobre la que se calcula el impuesto.',
            },
        ],
        tip:
            'Revisa este dashboard al menos cada trimestre. Si la utilidad esta creciendo, tu PPM puede ser muy bajo ' +
            'y vas a tener una sorpresa grande en abril. Tu contador puede ajustar el porcentaje de PPM si conviene.',
    },

    gestionClientes: {
        id: 'gestionClientes',
        titulo: 'Gestion de Clientes',
        icono: '👥',
        resumen: 'Directorio de clientes con sus datos y movimientos historicos.',
        queEs:
            'El directorio de clientes guarda los datos de cada persona o empresa a la que le vendes. ' +
            'Es el equivalente comercial de los proveedores. Cuando creas una cotizacion o emites una factura de venta, ' +
            'eliges al cliente desde aqui en vez de tipear sus datos cada vez.',
        conceptos: [
            {
                termino: 'RUT del cliente',
                definicion:
                    'Identificador tributario chileno. Es obligatorio para facturas electronicas. ' +
                    'El sistema valida el digito verificador automaticamente.',
            },
            {
                termino: 'Cliente activo / inactivo',
                definicion:
                    'Si dejaste de trabajar con un cliente, marcalo como inactivo (no lo borres). ' +
                    'Asi mantienes su historial y no aparece en los selectores de cotizaciones nuevas.',
            },
        ],
        comoUsar: [
            'Agrega clientes nuevos con RUT, razon social, direccion y datos de contacto.',
            'Para clientes recurrentes, completa email y telefono para futuros envios automaticos.',
            'Al editar un cliente, los cambios NO afectan las cotizaciones o facturas historicas (mantienen los datos del momento).',
        ],
    },

    gestionProveedores: {
        id: 'gestionProveedores',
        titulo: 'Gestion de Proveedores',
        icono: '🏢',
        resumen: 'Directorio de proveedores con sus datos y facturas asociadas.',
        queEs:
            'El directorio de proveedores guarda los datos de cada empresa o persona que te emite facturas. ' +
            'Tener al proveedor cargado evita tipear su RUT y razon social cada vez que registras una factura. ' +
            'Tambien permite ver el historial completo de compras a cada uno.',
        conceptos: [
            {
                termino: 'Cuenta contable del proveedor',
                definicion:
                    'La cuenta del plan de cuentas donde se imputa la deuda con ese proveedor. ' +
                    'Generalmente es 21XXXX. El sistema usa una por defecto si no especificas.',
            },
            {
                termino: 'Visor 360',
                definicion:
                    'Una vista completa de un proveedor: sus datos, todas sus facturas, los pagos hechos, los anticipos pendientes, y el saldo actual.',
            },
        ],
        tip:
            'Antes de registrar la primera factura de un proveedor nuevo, dalo de alta aqui. Asi sus datos quedan ' +
            'correctos desde el inicio y no tenes que corregirlos despues.',
    },

    historialFacturas: {
        id: 'historialFacturas',
        titulo: 'Historial de Facturas',
        icono: '📂',
        resumen: 'Listado completo de facturas con filtros y acciones rapidas.',
        queEs:
            'Este es el archivo de todas las facturas registradas en el sistema. Podes filtrar por fecha, ' +
            'proveedor, estado, tipo de documento, y hacer acciones masivas. Cada factura tiene un link a su ' +
            'asiento contable y al detalle de auditoria.',
        conceptos: [
            {
                termino: 'Estados de la factura',
                definicion:
                    'REGISTRADA (cargada en sistema), PAGADA (ya se transferio al proveedor), ANULADA (anulada con asiento inverso), ' +
                    'VENCIDA (paso la fecha de pago sin haberse pagado).',
            },
            {
                termino: 'Exportar Excel',
                definicion:
                    'Te descarga las facturas filtradas en un archivo Excel. Util para enviar al contador o subir al SII.',
            },
        ],
        tip:
            'Si necesitas saber por que tu balance no cuadra, filtra el historial por el mes problematico y ' +
            'mira que facturas tienen estado "REGISTRADA" pero sin asiento centralizado. Esas son las que faltan contabilizar.',
    },

    nominaPagos: {
        id: 'nominaPagos',
        titulo: 'Nomina de Pagos',
        icono: '💸',
        resumen: 'Agrupa facturas para pagarlas en bloque al banco.',
        queEs:
            'Una nomina de pagos es una lista de facturas que vas a pagar al mismo tiempo. ' +
            'En vez de transferir una por una, agrupas las que vencen en una nomina y se lo mandas al banco como un solo archivo. ' +
            'El banco procesa todos los pagos juntos y te ahorra tiempo.',
        conceptos: [
            {
                termino: 'Archivo bancario',
                definicion:
                    'Un formato especial (cada banco tiene el suyo) que el banco entiende para procesar pagos masivos. ' +
                    'El sistema lo genera automaticamente a partir de la nomina.',
            },
            {
                termino: 'Estado de la nomina',
                definicion:
                    'BORRADOR (la estas armando), ENVIADA (le mandaste el archivo al banco), CONFIRMADA (el banco confirmo que pago).',
            },
        ],
        comoUsar: [
            'Crea una nomina nueva y selecciona la cuenta bancaria desde donde vas a pagar.',
            'Agrega las facturas a pagar. El sistema valida que tengas saldo suficiente.',
            'Genera el archivo bancario y descargalo.',
            'Subi el archivo al portal de tu banco.',
            'Cuando el banco confirme los pagos, marca la nomina como CONFIRMADA. El sistema genera los asientos.',
        ],
    },

    perfilEmpresa: {
        id: 'perfilEmpresa',
        titulo: 'Perfil de Empresa',
        icono: '⚙️',
        resumen: 'Configuracion general y datos de tu empresa.',
        queEs:
            'Aqui se configuran los datos de tu empresa que se usan en TODO el sistema: ' +
            'razon social, RUT, logo, colores, direccion, contacto. Lo que ponemos aqui aparece en facturas, cotizaciones, reportes.',
        conceptos: [
            {
                termino: 'Logo de la empresa',
                definicion:
                    'Imagen que aparece en los PDFs de facturas y cotizaciones. ' +
                    'Recomendado: PNG con fondo transparente, no muy grande (max 500x500 px).',
            },
            {
                termino: 'Color principal',
                definicion:
                    'Define el color de los encabezados, botones y elementos destacados en el sistema. ' +
                    'Se aplica a todos los usuarios de la empresa.',
            },
        ],
        tip:
            'Cambios en el logo o RUT NO afectan facturas ya emitidas. Asi que si cambias el logo, las facturas ' +
            'historicas siguen mostrando el viejo (lo cual es lo correcto desde el punto de vista contable).',
    },

    reclasificadorAsiento: {
        id: 'reclasificadorAsiento',
        titulo: 'Reclasificar Asiento',
        icono: '🔄',
        resumen: 'Corrige el destino contable de un asiento sin anularlo.',
        queEs:
            'A veces un asiento se hizo correcto pero quedo en la cuenta equivocada. ' +
            'En vez de anularlo y rehacerlo, se "reclasifica": el sistema mueve el monto de la cuenta original ' +
            'a la cuenta correcta, manteniendo la fecha y la trazabilidad del cambio.',
        conceptos: [
            {
                termino: 'Cuenta origen / destino',
                definicion:
                    'Origen: donde esta hoy el monto (cuenta equivocada). Destino: donde deberia estar (cuenta correcta).',
            },
            {
                termino: 'Trazabilidad',
                definicion:
                    'Cada reclasificacion deja registro: quien lo hizo, cuando, motivo, y el asiento original sigue visible.',
            },
        ],
        errores: [
            {
                problema: 'No puedo reclasificar porque el periodo esta cerrado.',
                solucion:
                    'Una vez cerrado un periodo (con F29), los movimientos quedan en piedra. Habla con tu contador para abrir el periodo o crear un ajuste en el periodo actual.',
            },
        ],
        tip:
            'Si tenes que reclasificar muchas cosas a la vez, mejor revisa el plan de cuentas: probablemente ' +
            'esta mal configurado o falta capacitacion al equipo que registra.',
    },
};

export const listarModulos = () => Object.values(glosario).sort((a, b) =>
    a.titulo.localeCompare(b.titulo, 'es')
);

export const obtenerModulo = (id) => glosario[id] || null;

export const buscarModulos = (texto) => {
    if (!texto || texto.trim() === '') return listarModulos();
    const q = texto.toLowerCase().trim();
    return listarModulos().filter((m) =>
        m.titulo.toLowerCase().includes(q) ||
        m.resumen.toLowerCase().includes(q) ||
        m.queEs.toLowerCase().includes(q) ||
        m.conceptos?.some((c) =>
            c.termino.toLowerCase().includes(q) ||
            c.definicion.toLowerCase().includes(q)
        )
    );
};
