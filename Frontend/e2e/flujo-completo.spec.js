import { test, expect } from '@playwright/test';

/**
 * Ciclo completo de negocio, "por partes" (serial): cotización → aceptar → facturar →
 * registrar el pago como movimiento bancario → conciliar la factura → cerrar el período.
 * Cada parte depende del estado dejado por la anterior (test.describe.serial), tal como pediría
 * un pipeline real. Requiere que exista el cliente y la cuenta bancaria de prueba que crea
 * `php artisan tenri:e2e-setup` (ver Backend-laravel/app/Console/Commands/E2eSetupCommand.php).
 *
 * NO es @smoke: no corre en cada push, solo cuando el job de smoke ya paso (ver ci-cd.yml,
 * job e2e-flujo-completo con needs: [e2e-smoke]).
 */
test.describe.serial('@flujo-completo - Ciclo cotización → cierre de período', () => {
    // Un retry en modo serial re-ejecuta TODO el grupo desde el principio (asi funciona
    // Playwright), duplicando cotizacion/factura/movimiento con los mismos datos de prueba --
    // mejor un fallo limpio de una sola corrida que reintentos que enmascaran el estado real.
    test.describe.configure({ retries: 0 });

    const CLIENTE_NOMBRE = 'Cliente E2E de Prueba';
    const MONTO = 50_000;
    const HOY = new Date().toISOString().slice(0, 10);

    let cotizacionId;
    let numeroFactura;

    test('Parte 1 — crea una cotización para el cliente de prueba', async ({ page }) => {
        await page.goto('/cotizaciones/nueva');
        await expect(page.getByText(/Nueva Cotización|Crear Cotización/i).first()).toBeVisible({ timeout: 10_000 });

        const buscadorCliente = page.locator('input[placeholder="Buscar por RUT o Razón Social..."]');
        await buscadorCliente.fill(CLIENTE_NOMBRE);
        await page.getByText(CLIENTE_NOMBRE, { exact: false }).first().click();

        // FilaItemCotizacion.jsx no usa name/placeholder en Producto/Cantidad/Precio -- se
        // localiza por el contenedor del <label> correspondiente (mismo patron para los 3).
        const campoPorLabel = (texto) => page.locator('div').filter({ has: page.getByText(texto, { exact: true }) }).last().locator('input');

        await campoPorLabel('Producto o Servicio').fill('Servicio E2E');
        await campoPorLabel('Cantidad').fill('1');
        await campoPorLabel('Precio Unitario').fill(String(MONTO));

        const responsePromise = page.waitForResponse((res) => res.url().includes('/cotizaciones') && res.request().method() === 'POST');
        await page.getByRole('button', { name: /Generar Cotización/i }).click();
        const response = await responsePromise;
        const body = await response.json();

        expect(body.success).toBe(true);
        cotizacionId = body.data?.id;
        expect(cotizacionId).toBeTruthy();
    });

    test('Parte 2 — acepta y convierte la cotización a factura', async ({ page }) => {
        test.skip(!cotizacionId, 'La Parte 1 no dejó una cotización creada');

        await page.goto('/cotizaciones');
        await expect(page.getByText(/Cotizaciones/i).first()).toBeVisible({ timeout: 10_000 });

        // La tabla (vista desktop) muestra numero_cotizacion, no "#{id}" -- se localiza la fila
        // por el nombre del cliente, unico en este set de datos de prueba.
        const fila = page.locator('tr').filter({ hasText: CLIENTE_NOMBRE }).first();
        await expect(fila).toBeVisible({ timeout: 10_000 });

        // handleCambiarEstado pide confirmación con un swal ("¿Cambiar estado?" / "Confirmar")
        // antes de pegar al API.
        await fila.locator('button[title="Aceptar Cotización"]').click();
        const confirmarEstado = page.locator('.swal2-confirm');
        await expect(confirmarEstado).toBeVisible({ timeout: 5_000 });
        const aceptarResponse = page.waitForResponse((res) => res.url().includes(`/cotizaciones/${cotizacionId}/estado`));
        await confirmarEstado.click();
        await aceptarResponse;

        const btnFacturar = fila.locator('button[title="Generar Factura de Venta"]');
        await expect(btnFacturar).toBeVisible({ timeout: 10_000 });
        await btnFacturar.click();

        const inputFecha = page.locator('.swal2-container input[type="date"]');
        await expect(inputFecha).toBeVisible({ timeout: 5_000 });
        await inputFecha.fill(HOY);

        const facturarResponse = page.waitForResponse((res) => res.url().includes(`/cotizaciones/${cotizacionId}/facturar`));
        await page.locator('.swal2-confirm').click();
        const response = await facturarResponse;
        const body = await response.json();

        expect(body.success).toBe(true);
        numeroFactura = body.data?.numero_factura;
        expect(numeroFactura).toBeTruthy();
    });

    test('Parte 3 — registra el ingreso en la cartola bancaria', async ({ page }) => {
        test.skip(!numeroFactura, 'La Parte 2 no dejó una factura emitida');

        // "Banco E2E" (creada por tenri:e2e-setup) es la unica cuenta -> queda auto-seleccionada
        // en cuantoActiva tras GET /banco/cuentas. Sin esperar esa respuesta hay una carrera:
        // el submit puede salir con cuenta_bancaria_id vacio (400 "el campo... es obligatorio").
        const cuentasResponse = page.waitForResponse((res) => res.url().includes('/banco/cuentas'));
        await page.goto('/banco/cartola');
        await expect(page.getByText(/Cartola y Movimientos/i).first()).toBeVisible({ timeout: 10_000 });
        await cuentasResponse;

        // tipo_movimiento ya inicia en 'INGRESO' por defecto, pero se clickea igual por claridad.
        await page.getByRole('button', { name: /Ingreso \(Abono\)/i }).click();

        // Fecha/Monto/Descripcion/N°Documento no tienen name -- se ordenan asi en el DOM.
        await page.locator('input[type="date"]').first().fill(HOY);
        await page.locator('input[type="number"]').first().fill(String(MONTO));
        await page.locator('input[type="text"]').first().fill(`Pago factura ${numeroFactura} - E2E`);

        const responsePromise = page.waitForResponse((res) => res.url().includes('/banco/ingreso-manual'));
        await page.getByRole('button', { name: /Confirmar Registro de Ingreso/i }).click();
        const response = await responsePromise;

        expect(response.ok()).toBe(true);
    });

    test('Parte 4 — concilia el movimiento con la factura emitida', async ({ page }) => {
        test.skip(!numeroFactura, 'La Parte 2 no dejó una factura emitida');

        // Igual que en Parte 3: sin esperar la respuesta de movimientos pendientes hay una
        // carrera entre la navegación y la carga de datos (la fila puede no estar aun en el DOM).
        const movimientosResponse = page.waitForResponse((res) => res.url().includes('/banco/movimientos/pendientes'));
        await page.goto('/banco/conciliacion');
        await expect(page.getByText(/Conciliación/i).first()).toBeVisible({ timeout: 10_000 });
        await movimientosResponse;

        const filaMovimiento = page.locator('tr').filter({ hasText: 'E2E' }).first();
        await expect(filaMovimiento).toBeVisible({ timeout: 10_000 });
        await filaMovimiento.getByRole('button', { name: 'Conciliar' }).click();

        // El modal no usa role="dialog" -- se ubica por el titulo fijo de la cabecera. OJO:
        // `div.filter({has:...}).last()` en orden de documento agarra el div MAS INTERNO que
        // contiene el texto -- en MesaConciliacion.jsx eso es el wrapper del header
        // (`<div className="relative z-10"><h3>Plataforma de Conciliación</h3>...`), que NO
        // incluye el Select/tabs/boton de abajo (estan en divs hermanos, no descendientes).
        // ROOT CAUSE real de "Parte 4" (encontrado con trace de Playwright, nunca fue un bug de
        // react-select): con `.last()`, cualquier `modal.getByText/getByRole` para contenido
        // fuera del header no encontraba nada -- `entroModoManual` quedaba siempre en `false` y
        // el bloque de seleccion manual (el <Select>) nunca llegaba a tocarse. Se ubica por la
        // clase del card (`max-w-4xl`), que sí envuelve header + contenido.
        const modal = page.locator('div.max-w-4xl').filter({ has: page.getByText('Plataforma de Conciliación') });
        await expect(modal).toBeVisible({ timeout: 5_000 });

        // abrirModalConciliacion() intenta "Sugerencia" (match automatico por monto) primero;
        // GET /banco/movimientos/{id}/sugerencias a veces encuentra la factura y a veces no
        // (observado en corridas reales -- no depende de nada en este spec), y el propio
        // componente cae solo a modo "Busqueda Manual" cuando no hay match. El test maneja
        // ambos caminos en vez de asumir uno fijo.
        await page.waitForResponse((res) => res.url().includes('/sugerencias'));

        const marcaModoManual = modal.getByText(/Seleccionar (Cliente|Proveedor)/i);
        const entroModoManual = await marcaModoManual
            .waitFor({ state: 'visible', timeout: 8_000 })
            .then(() => true)
            .catch(() => false);

        if (entroModoManual) {
            const comboboxCliente = modal.getByRole('combobox');
            await comboboxCliente.click();
            await page.keyboard.type('Cliente E2E', { delay: 60 });

            const opcionCliente = page.getByText('Cliente E2E de Prueba', { exact: false }).last();
            await expect(opcionCliente).toBeVisible({ timeout: 5_000 });

            const facturasResponsePromise = page.waitForResponse((res) => res.url().includes('/facturas?') || res.url().includes('/facturas'));
            await opcionCliente.click();
            await facturasResponsePromise;

            const filaFactura = modal.locator('tr').filter({ hasText: numeroFactura }).first();
            await expect(filaFactura).toBeVisible({ timeout: 10_000 });
            await filaFactura.click();
        }
        // Si NO entró a modo manual, la sugerencia automática ya dejó la factura lista en
        // facturasAProcesar (todo lo que devuelve /sugerencias se incluye sin selección manual).

        const responsePromise = page.waitForResponse((res) => res.url().includes('/banco/movimientos/conciliar-facturas'));
        await expect(modal.getByRole('button', { name: /Aprobar Pago|Registrar como Anticipo/i })).toBeVisible({ timeout: 5_000 });
        await modal.getByRole('button', { name: /Aprobar Pago|Registrar como Anticipo/i }).click();
        const response = await responsePromise;

        expect(response.ok()).toBe(true);
    });

    test('Parte 5 — cierra el período contable del mes actual', async ({ page }) => {
        await page.goto('/contabilidad/cierre-periodo');
        await expect(page.getByText(/Cierre de Período/i).first()).toBeVisible({ timeout: 10_000 });

        const botonesCierre = page.getByRole('button', { name: 'Cerrar período', exact: true });
        const hayBotonDisponible = await botonesCierre.first().isVisible({ timeout: 5_000 }).catch(() => false);

        test.skip(!hayBotonDisponible, 'No hay ningún mes abierto disponible para cerrar (o falta el permiso contabilidad.cerrar_periodo)');

        await botonesCierre.first().click();

        // El modal no tiene role="dialog" -- es un overlay .fixed.inset-0.z-50, y coexiste con
        // el botón "Cerrar período" original de la grilla mientras está abierto (mismo texto),
        // por eso se toma el ÚLTIMO botón con ese nombre en vez de "first()".
        const modalVisible = page.locator('div.fixed.inset-0.z-50');
        await expect(modalVisible).toBeVisible({ timeout: 5_000 });

        const responsePromise = page.waitForResponse((res) => res.url().includes('/contabilidad/periodos/cerrar'));
        await page.getByRole('button', { name: 'Cerrar período', exact: true }).last().click();
        const response = await responsePromise;

        expect(response.status()).toBeLessThan(500);
    });
});
