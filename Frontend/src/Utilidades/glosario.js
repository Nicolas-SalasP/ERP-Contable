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
    // INVENTARIO (resumen breve, modulo grande)
    // ================================================================
    inventario: {
        id: 'inventario',
        titulo: 'Inventario',
        icono: '📦',
        resumen: 'Controla productos, lotes, bodegas y movimientos de stock.',
        queEs:
            'El inventario es el registro detallado de TODO lo que tienes en stock fisico: productos, ' +
            'cantidades, ubicaciones (bodegas), y movimientos (entradas, salidas, ajustes, traslados). ' +
            'Esta integrado con compras y ventas para que el stock se actualice solo.',
        conceptos: [
            {
                termino: 'Producto',
                definicion:
                    'El item que se compra, vende o usa. Tiene codigo SKU, nombre, unidad de medida y precio de costo.',
            },
            {
                termino: 'Lote',
                definicion:
                    'Una agrupacion de unidades de un producto que llegaron juntas. Importante para productos ' +
                    'con vencimiento (alimentos, medicamentos).',
            },
            {
                termino: 'Bodega',
                definicion:
                    'Una ubicacion fisica donde se guarda el inventario. Una empresa puede tener varias bodegas.',
            },
            {
                termino: 'Toma fisica',
                definicion:
                    'El conteo manual del inventario para comparar con lo que dice el sistema. ' +
                    'Si hay diferencias se genera un ajuste.',
            },
            {
                termino: 'Reserva',
                definicion:
                    'Stock apartado para una orden de venta futura. Aparece como "no disponible" para otras ventas.',
            },
        ],
        comoUsar: [
            'Crea los productos con su SKU, nombre y unidad de medida.',
            'Define las bodegas donde guardas inventario.',
            'Registra movimientos de entrada cuando llega mercancia.',
            'Las ventas descuentan stock automaticamente.',
            'Periodicamente haz tomas fisicas para verificar y ajustar.',
        ],
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
};

/**
 * Devuelve la lista completa de modulos como array (ordenada por titulo).
 */
export const listarModulos = () => Object.values(glosario).sort((a, b) =>
    a.titulo.localeCompare(b.titulo, 'es')
);

/**
 * Busca un modulo por id. Si no existe, devuelve null.
 */
export const obtenerModulo = (id) => glosario[id] || null;

/**
 * Busca modulos por texto (busca en titulo, resumen, queEs).
 * Util para el buscador del modulo Glosario.
 */
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
