export const glosario = {
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
                    'El nombre legal completo de la empresa segun el SII. Ej: "Tenri SpA".',
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
    empleadosRrhh: {
        id: 'empleadosRrhh',
        titulo: 'Ficha de Personal',
        icono: '🧑‍💼',
        resumen: 'Registra los datos de cada trabajador de la empresa.',
        queEs:
            'Es el registro maestro de tus trabajadores. Aqui guardas sus datos personales, de contacto, ' +
            'su prevision (AFP y salud) y sus datos bancarios para el pago de sueldos. Toda liquidacion, ' +
            'contrato o finiquito parte desde un empleado registrado aqui.',
        conceptos: [
            { termino: 'AFP', definicion: 'Administradora de Fondos de Pensiones donde el trabajador cotiza el 10% obligatorio mas la comision.' },
            { termino: 'FONASA / ISAPRE', definicion: 'Sistema de salud del trabajador. FONASA descuenta 7%; ISAPRE descuenta el plan pactado en UF.' },
            { termino: 'Datos bancarios', definicion: 'Banco y numero de cuenta para depositar el sueldo. Se guardan cifrados por la Ley 21.719 de proteccion de datos.' },
        ],
        comoUsar: [
            'Haz click en "Nuevo empleado".',
            'Completa al menos RUT, nombres y apellido paterno (lo demas es opcional).',
            'Elige su AFP y sistema de salud: esto define como se calculan sus descuentos.',
            'Si pagas por transferencia, completa banco, tipo y numero de cuenta.',
            'Guarda. El empleado queda disponible para crearle contratos y liquidaciones.',
        ],
        errores: [
            { problema: 'No me deja crear el empleado, dice que el RUT ya existe.', solucion: 'Cada RUT es unico por empresa. Busca en la lista; probablemente el trabajador ya esta registrado.' },
            { problema: 'No veo el numero de cuenta al editar.', solucion: 'Por seguridad el numero de cuenta nunca se muestra de vuelta (esta cifrado). Si necesitas cambiarlo, escribelo de nuevo; si lo dejas vacio, se mantiene el anterior.' },
        ],
        tip: 'Carga primero a todos los empleados antes de empezar a calcular liquidaciones: te ahorras volver atras.',
    },
    contratosRrhh: {
        id: 'contratosRrhh',
        titulo: 'Contratos',
        icono: '📝',
        resumen: 'Gestiona el historial de contratos de cada trabajador.',
        queEs:
            'Cada trabajador tiene uno o mas contratos a lo largo del tiempo. El contrato define el tipo ' +
            '(indefinido, plazo fijo o por obra), el sueldo base, la jornada y el cargo. Solo un contrato ' +
            'puede estar VIGENTE a la vez: al crear uno nuevo, el anterior se desactiva automaticamente.',
        conceptos: [
            { termino: 'Indefinido / Plazo fijo / Por obra', definicion: 'Tipo de contrato. Afecta el seguro de cesantia (AFC) y las indemnizaciones del finiquito.' },
            { termino: 'Sueldo base', definicion: 'La remuneracion fija mensual sobre la que se calcula la gratificacion, las horas extra y las cotizaciones.' },
            { termino: 'Causal de termino', definicion: 'El articulo del Codigo del Trabajo por el que termina el contrato (Art. 159, 160 o 161). Define si hay o no indemnizacion.' },
        ],
        comoUsar: [
            'Selecciona un empleado en el desplegable.',
            'Revisa su historial de contratos en la tabla.',
            'Para uno nuevo, haz click en "Nuevo contrato" y completa tipo, fechas y sueldo base.',
            'Para terminar un contrato vigente, usa el boton de termino e indica la causal y la fecha.',
        ],
        errores: [
            { problema: 'No me deja poner fecha de termino.', solucion: 'Los contratos indefinidos no llevan fecha de termino. Solo plazo fijo y por obra la exigen.' },
            { problema: 'Cree un contrato y el anterior quedo TERMINADO.', solucion: 'Es correcto: solo un contrato puede estar vigente. Crear uno nuevo cierra el anterior automaticamente.' },
        ],
        tip: 'Antes de calcular el finiquito, asegurate de que el contrato del trabajador este VIGENTE: el finiquito lo termina al firmarse.',
    },
    liquidacionesRrhh: {
        id: 'liquidacionesRrhh',
        titulo: 'Liquidaciones de Sueldo',
        icono: '💵',
        resumen: 'Calcula el sueldo mensual con todos los descuentos legales.',
        queEs:
            'La liquidacion es el calculo mensual del sueldo de un trabajador: parte del sueldo base, suma ' +
            'gratificacion y horas extra, y descuenta AFP, salud, seguro de cesantia (AFC) e impuesto unico. ' +
            'El resultado es el "liquido a pagar". El sistema usa los parametros legales del periodo, sin numeros a mano.',
        conceptos: [
            { termino: 'Imponible', definicion: 'La parte del sueldo sobre la que se calculan las cotizaciones, con tope legal (90 UF en 2026).' },
            { termino: 'Liquido a pagar', definicion: 'Lo que efectivamente recibe el trabajador: haberes menos todos los descuentos.' },
            { termino: 'Borrador / Emitida', definicion: 'Una liquidacion en BORRADOR se puede recalcular. Al EMITIRLA queda firme y entra a centralizacion y Previred.' },
        ],
        comoUsar: [
            'Haz click en "Calcular liquidacion" y elige el empleado y el periodo (mes y año).',
            'Agrega horas extra o remuneraciones variables si corresponde.',
            'Revisa el detalle de haberes y descuentos en el modal.',
            'Cuando este correcta, EMITELA. Una vez emitida no se puede recalcular, pero si anular.',
        ],
        errores: [
            { problema: 'Falla diciendo que no hay parametros del periodo.', solucion: 'Faltan los indicadores (UF/UTM) o los parametros previsionales del mes. Cargalos en "Parametros Previsionales" antes de calcular.' },
            { problema: 'Recalcule y se borro la anterior.', solucion: 'Es correcto mientras este en BORRADOR: recalcular reemplaza el borrador del mismo periodo. Las EMITIDAS no se tocan.' },
        ],
        tip: 'Calcula y revisa en borrador todo el mes; recien cuando este todo OK, emite en bloque y centraliza.',
    },
    finiquitosRrhh: {
        id: 'finiquitosRrhh',
        titulo: 'Finiquitos',
        icono: '🤝',
        resumen: 'Calcula el pago final al terminar un contrato.',
        queEs:
            'El finiquito es el calculo de lo que se le paga al trabajador cuando termina su contrato: ' +
            'indemnizacion por años de servicio, mes de aviso previo y vacaciones proporcionales, segun la ' +
            'causal de termino del Codigo del Trabajo. Al firmarlo, el contrato queda terminado.',
        conceptos: [
            { termino: 'Indemnizacion por años de servicio', definicion: '30 dias de sueldo por año trabajado (fraccion mayor a 6 meses cuenta como año, tope 11 años). Solo aplica en necesidades de la empresa (Art. 161).' },
            { termino: 'Aviso previo', definicion: 'Si no se avisa con 30 dias, se paga un mes de sueldo sustitutivo.' },
            { termino: 'Vacaciones proporcionales', definicion: 'Los dias de vacaciones que el trabajador alcanzo a acumular y no tomo, pagados en dinero (Art. 73).' },
        ],
        comoUsar: [
            'Haz click en "Calcular finiquito".',
            'Elige el empleado y su contrato vigente.',
            'Selecciona la causal de termino: define si hay indemnizacion.',
            'Indica la fecha de termino y si diste aviso previo.',
            'Revisa el desglose y, si esta correcto, firma el finiquito (esto termina el contrato).',
        ],
        errores: [
            { problema: 'No aparece el contrato del trabajador.', solucion: 'Solo se listan contratos VIGENTES. Si ya estaba terminado, no se puede finiquitar de nuevo.' },
            { problema: 'No salio indemnizacion por años.', solucion: 'La indemnizacion del Art. 163 solo aplica en "Necesidades de la empresa". En renuncia o vencimiento de plazo no corresponde.' },
        ],
        tip: 'Revisa bien la causal: es lo que mas cambia el monto final. Una renuncia y un despido por necesidades de la empresa pagan muy distinto.',
    },
    vacacionesRrhh: {
        id: 'vacacionesRrhh',
        titulo: 'Vacaciones',
        icono: '🏖️',
        resumen: 'Solicitud y aprobacion del feriado legal de cada trabajador.',
        queEs:
            'Modulo para pedir y aprobar dias de vacaciones (feriado legal, Art. 67-70 Codigo del Trabajo). ' +
            'El saldo disponible se calcula restando los dias ya aprobados al total devengado mes a mes ' +
            '(1,25 dias habiles por mes trabajado). Una solicitud PENDIENTE no descuenta saldo todavia; ' +
            'solo se descuenta cuando se aprueba.',
        conceptos: [
            { termino: 'Dias devengados', definicion: 'Total acumulado desde el inicio del contrato, calculado automaticamente al emitir cada liquidacion.' },
            { termino: 'Dias tomados', definicion: 'Suma de dias habiles de todas las solicitudes ya APROBADAS.' },
            { termino: 'Saldo disponible', definicion: 'Dias devengados menos dias tomados. Es lo que se puede solicitar.' },
        ],
        comoUsar: [
            'Haz click en "Solicitar vacaciones" y elige el empleado: veras su saldo disponible.',
            'Indica el rango de fechas; el sistema cuenta solo dias habiles (lunes a viernes).',
            'La solicitud queda PENDIENTE hasta que RRHH la aprueba o rechaza.',
            'Si una vacacion aprobada se debe deshacer, usa "Anular": repone el saldo consumido.',
        ],
        errores: [
            { problema: 'Dice saldo insuficiente pero el empleado lleva años trabajando.', solucion: 'El saldo depende de que se hayan emitido las liquidaciones mensuales (cada una devenga 1,25 dias). Si faltan liquidaciones emitidas, el devengo esta incompleto.' },
            { problema: 'No puedo aprobar una solicitud.', solucion: 'Solo se pueden aprobar/rechazar solicitudes en estado PENDIENTE. Si ya fue resuelta, no se puede volver a procesar.' },
        ],
        tip: 'Aprobar y luego anular no es lo mismo que rechazar: el rechazo es para una solicitud que nunca debio aprobarse, la anulacion es para una vacacion aprobada que finalmente no se tomo.',
    },
    parametrosRrhh: {
        id: 'parametrosRrhh',
        titulo: 'Parametros Previsionales',
        icono: '⚖️',
        resumen: 'Las tasas y topes legales que usa el calculo de sueldos.',
        queEs:
            'Aqui viven los valores legales con que se calculan las liquidaciones: tasas de AFP y salud, topes ' +
            'imponibles, seguro de cesantia, la tabla de impuesto unico y los indicadores UF/UTM de cada mes. ' +
            'El motor de calculo lee estos datos; nunca hay numeros fijos en el codigo.',
        conceptos: [
            { termino: 'UF / UTM', definicion: 'Unidades reajustables. La UF fija los topes en pesos y la UTM se usa para el impuesto unico. Cambian todos los meses.' },
            { termino: 'Tope imponible', definicion: 'El maximo sobre el que se cotiza (90 UF para AFP/salud en 2026). Lo que excede no cotiza.' },
            { termino: 'Tabla de impuesto unico', definicion: 'Los tramos de impuesto a la renta de segunda categoria, expresados en UTM. Definen cuanto impuesto descuenta cada sueldo.' },
        ],
        comoUsar: [
            'Revisa la pestaña "Previsionales" para ver las tasas y topes vigentes.',
            'Cada mes, en "Indicadores UF/UTM", registra la UF y UTM del periodo (las publica la CMF y el SII).',
            'En "Tabla Impuesto Unico" puedes consultar los tramos por año.',
        ],
        errores: [
            { problema: 'Las liquidaciones fallan por falta de indicadores.', solucion: 'Cada mes necesita su UF y UTM cargadas. Registralas en la pestaña Indicadores antes de calcular sueldos de ese mes.' },
            { problema: 'No hay parametros previsionales.', solucion: 'Ejecuta el seeder RrhhParametrosLegalesSeeder o crealos via API. Sin ellos el motor no puede calcular.' },
        ],
        tip: 'Verifica siempre los valores contra la fuente oficial (Superintendencia de Pensiones, SII, Previred) antes de procesar sueldos reales.',
    },
    centralizacionRrhh: {
        id: 'centralizacionRrhh',
        titulo: 'Centralizacion Contable',
        icono: '📚',
        resumen: 'Lleva las remuneraciones del mes a la contabilidad.',
        queEs:
            'Centralizar es generar el asiento contable mensual que resume todas las liquidaciones emitidas: ' +
            'el gasto en remuneraciones y leyes sociales (debe) contra lo que queda por pagar a trabajadores, ' +
            'AFP, salud, impuesto y aportes (haber). Primero configuras que cuenta del Plan usar para cada categoria.',
        conceptos: [
            { termino: 'Mapeo contable', definicion: 'La asociacion entre cada categoria de RRHH (ej: "Liquido por pagar") y una cuenta de tu Plan de Cuentas.' },
            { termino: 'Partida doble', definicion: 'El asiento siempre cuadra: el total del Debe (gastos) es igual al del Haber (pasivos por pagar).' },
            { termino: 'Cuenta obligatoria', definicion: 'Hay 6 categorias minimas que debes mapear si o si para poder centralizar; las de vacaciones son opcionales.' },
        ],
        comoUsar: [
            'En "Mapeo contable", busca y asigna una cuenta del Plan para cada categoria obligatoria.',
            'Emite todas las liquidaciones del mes en la vista de Liquidaciones.',
            'Elige el periodo y haz click en "Centralizar periodo".',
            'El sistema genera un unico asiento mensual y lo deja en la contabilidad.',
        ],
        errores: [
            { problema: 'El boton de centralizar esta deshabilitado.', solucion: 'Faltan cuentas obligatorias por mapear. Completa las que aparecen marcadas como "obligatorio" mas abajo.' },
            { problema: 'Dice que el periodo ya fue centralizado.', solucion: 'Cada mes se centraliza una sola vez. Si necesitas rehacerlo, reversa el asiento generado desde Contabilidad.' },
        ],
        tip: 'Configura el mapeo una sola vez al principio; despues, cada mes solo emites y centralizas.',
    },
    previredRrhh: {
        id: 'previredRrhh',
        titulo: 'Archivo Previred',
        icono: '📄',
        resumen: 'Genera la planilla mensual para pagar las cotizaciones.',
        queEs:
            'Previred es el portal donde se declaran y pagan las cotizaciones previsionales. Esta vista genera ' +
            'la planilla del mes en formato CSV, con una fila por trabajador y sus montos de AFP, salud, AFC, ' +
            'SIS, mutual e impuesto, lista para subir a previred.com.',
        conceptos: [
            { termino: 'Planilla previsional', definicion: 'El archivo con las cotizaciones de todos los trabajadores del mes, que se sube a Previred para declarar y pagar.' },
            { termino: 'Previsualizar', definicion: 'Ver en pantalla el contenido del archivo antes de descargarlo, para revisar montos y trabajadores.' },
            { termino: 'Periodo', definicion: 'El mes y año que se declara. Solo incluye liquidaciones que esten EMITIDAS.' },
        ],
        comoUsar: [
            'Elige el periodo (mes y año).',
            'Haz click en "Previsualizar" para revisar los datos por trabajador.',
            'Si todo esta correcto, descarga el CSV con "Descargar CSV".',
            'Sube el archivo en previred.com para declarar y pagar dentro del plazo legal.',
        ],
        errores: [
            { problema: 'No genera nada o da error.', solucion: 'Solo se incluyen liquidaciones EMITIDAS. Emite los sueldos del mes antes de generar el archivo.' },
            { problema: 'Un trabajador aparece con codigo de AFP 00.', solucion: 'Su AFP o ISAPRE no esta en la tabla de codigos Previred. Revisa que el nombre de la AFP/ISAPRE en la ficha sea el correcto.' },
        ],
        tip: 'Las cotizaciones se pagan hasta el dia 13 del mes siguiente. Genera y sube el archivo con tiempo para evitar multas e intereses.',
    },
    lre: {
        id: 'lre',
        titulo: 'LRE — Libro de Remuneraciones Electronico',
        icono: '📋',
        resumen: 'Genera y valida el archivo LRE mensual para enviar al portal Mi DT.',
        queEs:
            'El Libro de Remuneraciones Electronico (LRE) es el reemplazo digital del libro de remuneraciones en papel. ' +
            'Las empresas con 5 o mas trabajadores deben generarlo cada mes y subirlo al portal Mi DT ' +
            '(Direccion del Trabajo). El ERP lo arma desde las liquidaciones emitidas del periodo; ' +
            'el envio al portal es manual.',
        conceptos: [
            { termino: 'Mi DT', definicion: 'Portal web de la Direccion del Trabajo (www.dt.gob.cl) donde se sube el archivo. No se transmite automaticamente desde el ERP.' },
            { termino: 'Numero de confirmacion DT', definicion: 'Codigo que entrega Mi DT al recibir el archivo. Registralo en el ERP para dejar constancia del envio.' },
        ],
        comoUsar: [
            'Ve a RRHH > LRE.',
            'Selecciona el anio y mes del periodo.',
            'Haz click en "Generar LRE". El sistema procesa todas las liquidaciones EMITIDAS del periodo.',
            'Haz click en "Validar" para verificar que no haya errores.',
            'Descarga el archivo .txt y subelo manualmente al portal Mi DT.',
            'Una vez que Mi DT te entregue el numero de confirmacion, registralo en el ERP con "Confirmar envio a DT".',
        ],
        errores: [
            { problema: 'Dice "no hay liquidaciones emitidas".', solucion: 'Solo se incluyen liquidaciones en estado EMITIDA. Emite los sueldos del periodo antes de generar el LRE.' },
            { problema: 'Falla la validacion.', solucion: 'Revisa los errores listados. Lo mas comun es un RUT mal formateado o un codigo de AFP/ISAPRE incorrecto en la ficha del trabajador.' },
        ],
        tip: 'El plazo legal es el ultimo dia del mes siguiente al periodo. No esperes el ultimo dia.',
    },

    dj1887: {
        id: 'dj1887',
        titulo: 'DJ 1887 — Rentas de Empleados',
        icono: '📄',
        resumen: 'Declara las rentas anuales y el IUSC retenido por trabajador al SII.',
        queEs:
            'La DJ 1887 informa al SII las rentas que la empresa pago a cada trabajador durante el anio ' +
            'y el Impuesto Unico de Segunda Categoria (IUSC) que se les retuvo mensualmente. ' +
            'Es la base para que los trabajadores hagan su Operacion Renta. ' +
            'Se presenta una vez al anio, plazo hasta el 28 de febrero del anio siguiente.',
        conceptos: [
            { termino: 'IUSC', definicion: 'Impuesto Unico de Segunda Categoria. Se descuenta de la liquidacion mensual segun tabla progresiva; la DJ 1887 informa el total retenido en el anio.' },
            { termino: 'Anio tributario (AT)', definicion: 'El anio en que se declaran las rentas. Ej: AT 2026 corresponde a las rentas ganadas durante el anio 2025.' },
        ],
        comoUsar: [
            'Ve a Tributario > DJ 1887.',
            'Selecciona el anio tributario (AT) a declarar.',
            'Haz click en "Generar DJ". El sistema lee las liquidaciones EMITIDAS del anio.',
            'Haz click en "Validar" y revisa que no haya errores.',
            'Descarga el archivo .txt y subelo al portal del SII antes del 28 de febrero.',
            'Una vez presentada, haz click en "Confirmar presentacion" y registra el folio.',
        ],
        errores: [
            { problema: 'No genera nada o dice "sin trabajadores".', solucion: 'Solo incluye liquidaciones en estado EMITIDA o PAGADA. Verifica que el anio seleccionado tenga liquidaciones emitidas.' },
            { problema: 'El archivo no pasa la validacion del SII.', solucion: 'Verifica que el RUT de la empresa y de todos los trabajadores esten correctos y con formato valido (sin puntos, con guion).' },
        ],
        tip: 'Genera la DJ 1887 antes de que tus trabajadores hagan su Operacion Renta. Si llega tarde, no pueden usar los datos y el SII puede multar a la empresa.',
    },

    dj1879: {
        id: 'dj1879',
        titulo: 'DJ 1879 — Retenciones de Honorarios',
        icono: '📄',
        resumen: 'Declara los honorarios pagados y las retenciones practicadas a prestadores independientes.',
        queEs:
            'Cuando la empresa paga honorarios a personas que emiten boletas (prestadores independientes), ' +
            'retiene un porcentaje del bruto y lo entera al SII. ' +
            'La DJ 1879 informa anualmente esos pagos y retenciones agrupados por prestador. ' +
            'Se nutre del modulo Honorarios Recibidos; sin registros ahi, la DJ no tiene datos.',
        conceptos: [
            { termino: 'Retencion', definicion: 'Porcentaje que la empresa descuenta del honorario bruto y paga al SII en nombre del prestador. La tasa sube cada anio por Ley 21.133.' },
            { termino: 'Liquido a pagar', definicion: 'Lo que efectivamente recibe el prestador: monto bruto menos la retencion.' },
        ],
        comoUsar: [
            'Primero registra cada boleta recibida en Compras > Honorarios Recibidos.',
            'Ve a Tributario > DJ 1879.',
            'Selecciona el anio tributario.',
            'Haz click en "Generar DJ". El sistema agrupa los honorarios por prestador.',
            'Valida, descarga y sube al SII antes del 28 de febrero.',
        ],
        errores: [
            { problema: 'No hay datos al generar.', solucion: 'Primero registra las boletas de honorarios en el modulo Honorarios Recibidos del anio seleccionado.' },
            { problema: 'Error de cuadratura.', solucion: 'Revisa que no haya honorarios con monto de retencion negativo o cero cuando deberia tener retencion.' },
        ],
        tip: 'Registra las boletas en Honorarios Recibidos a medida que llegan. Si las ingresas todas a fin de anio es mas facil cometer errores.',
    },

    dj1947: {
        id: 'dj1947',
        titulo: 'DJ 1947 — Propyme Transparente (14D N°8)',
        icono: '📄',
        resumen: 'Declara la renta atribuida y los PPM a disposicion de cada propietario del regimen Propyme Transparente.',
        queEs:
            'Las empresas en regimen Propyme Transparente (14D N°8) no pagan Impuesto de Primera Categoria. ' +
            'En cambio, cada propietario tributa con sus impuestos finales sobre la parte del resultado que le corresponde segun su % de participacion. ' +
            'La DJ 1947 informa al SII esa distribucion: cuanto le toca a cada socio del resultado del anio y que PPM queda a su disposicion.',
        conceptos: [
            { termino: 'Base imponible simplificada', definicion: 'Ingresos percibidos menos gastos pagados del anio, sin correccion monetaria. Es la base del calculo para el regimen 14D N°8.' },
            { termino: 'Atribucion de renta', definicion: 'Distribucion del resultado tributario a cada propietario segun su porcentaje de participacion registrado en el ERP.' },
        ],
        comoUsar: [
            'Registra los propietarios en Empresa > Propietarios (deben sumar 100%).',
            'Verifica que el regimen tributario de la empresa este en "14_D8" (Perfil de empresa).',
            'Ve a Tributario > DJ 1947.',
            'Selecciona el anio tributario.',
            'Genera, valida, descarga y sube al SII antes del 28 de febrero.',
        ],
        errores: [
            { problema: 'Dice que la empresa no es 14D N°8.', solucion: 'Ve a Perfil de empresa y verifica que el campo Regimen tributario este en "Propyme Transparente (14D N°8)".' },
            { problema: 'Error "propietarios no suman 100%".', solucion: 'Ve a Empresa > Propietarios y ajusta los porcentajes hasta que sumen exactamente 100%.' },
        ],
        tip: 'Antes de generar la DJ 1947, verifica que los propietarios esten registrados en Empresa > Propietarios y que sus porcentajes sumen exactamente 100%.',
    },

    honorariosRecibidos: {
        id: 'honorariosRecibidos',
        titulo: 'Honorarios Recibidos',
        icono: '🧾',
        resumen: 'Registra boletas de honorarios de prestadores independientes y calcula la retencion automaticamente.',
        queEs:
            'Cuando pagas honorarios a alguien que te emite una boleta, debes retener un porcentaje y enterarlo al SII. ' +
            'Este modulo guarda cada boleta, calcula la retencion segun la tasa legal del anio (Ley 21.133) ' +
            'y la incluye automaticamente en el F29 del mes correspondiente. ' +
            'Los registros de este modulo alimentan la DJ 1879 al cierre del anio.',
        conceptos: [
            { termino: 'Retencion', definicion: 'Monto que la empresa descuenta del bruto y paga al SII. La tasa sube cada anio; el sistema la aplica automaticamente segun la fecha de la boleta.' },
            { termino: 'Liquido a pagar', definicion: 'Lo que le depositas al prestador: monto bruto menos la retencion.' },
        ],
        comoUsar: [
            'Ve a Compras > Honorarios Recibidos.',
            'Haz click en "Agregar honorario".',
            'Ingresa el RUT del prestador, nombre, numero de boleta (opcional), fecha y monto bruto.',
            'El sistema calcula automaticamente la retencion y el liquido a pagar segun la tasa del anio.',
            'Guarda. El honorario aparece en el F29 del mes de la fecha ingresada.',
        ],
        errores: [
            { problema: 'RUT invalido.', solucion: 'Verifica que el RUT tenga el formato correcto (ej: 12345678-9) y que el digito verificador sea valido.' },
            { problema: 'No encuentra la tasa del anio.', solucion: 'La tasa de retencion esta en una tabla por anio. Si el anio no esta configurado, contacta al administrador.' },
        ],
        tip: 'Registra cada boleta al recibirla. Asi el F29 del mes siempre refleja las retenciones reales sin correcciones de ultimo momento.',
    },

    propietariosEmpresa: {
        id: 'propietariosEmpresa',
        titulo: 'Propietarios de la Empresa',
        icono: '👥',
        resumen: 'Registra los socios con su porcentaje de participacion para distribuir la renta en la DJ 1947.',
        queEs:
            'En el regimen Propyme Transparente el resultado tributario se distribuye a los propietarios ' +
            'segun el porcentaje que cada uno tiene en la empresa. ' +
            'Aqui se registra esa composicion societaria. Los porcentajes deben sumar exactamente 100%; ' +
            'mientras no cuadren, no es posible generar la DJ 1947.',
        conceptos: [
            { termino: 'Porcentaje de participacion', definicion: 'La fraccion del negocio que le pertenece a cada socio. Determina cuanta renta e impuesto le corresponde en la DJ 1947.' },
            { termino: 'Suma 100%', definicion: 'Todos los porcentajes juntos deben dar exactamente 100. El modulo muestra el total en verde si cuadra y en rojo si no.' },
        ],
        comoUsar: [
            'Ve a Empresa > Propietarios.',
            'Haz click en "Agregar propietario".',
            'Ingresa el RUT, nombre completo y porcentaje de participacion.',
            'Verifica que la barra de total marque exactamente 100% (aparece en verde).',
            'Guarda. Estos datos se usaran al generar la DJ 1947.',
        ],
        errores: [
            { problema: 'Los porcentajes no suman 100%.', solucion: 'Ajusta los valores hasta que la suma sea exactamente 100%. Puedes usar decimales (ej: 33.33 + 33.33 + 33.34 = 100).' },
            { problema: 'No me deja agregar el mismo RUT dos veces.', solucion: 'Cada propietario solo puede aparecer una vez. Si necesitas cambiar el porcentaje, edita el existente.' },
        ],
        tip: 'Si la empresa tiene un solo dueno, ingresalo con 100%. Actualiza los porcentajes antes de generar la DJ 1947 si hubo cambios societarios durante el anio.',
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
