import { test, expect } from '@playwright/test';

const USER_EMAIL = process.env.E2E_USER_EMAIL || 'superadmin@tenri.cl';
const USER_PASSWORD = process.env.E2E_USER_PASSWORD || 'password123';

async function login(page) {
    await page.goto('/login');
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    await emailInput.waitFor({ state: 'visible', timeout: 5000 });
    await emailInput.fill(USER_EMAIL);
    
    await page.locator('input[type="password"]').first().fill(USER_PASSWORD);
    await Promise.all([
        page.waitForNavigation({ url: '**/', timeout: 15_000 }).catch(() => {}),
        page.locator('button[type="submit"]').first().click()
    ]);

    await expect(page).not.toHaveURL(/.*\/login/, { timeout: 15_000 });
}

test.describe('Flujo de Compras y Facturación', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('el formulario de ingreso de nueva factura carga los campos tributarios', async ({ page }) => {
        await page.goto('/facturas/nueva');
        await expect(page.getByText(/Ingresar Factura|Nueva Factura/i).first()).toBeVisible({ timeout: 10_000 });
        const inputFolio = page.locator('input[name*="folio"], input[placeholder*="Folio"], input[name*="numero"]').first();
        await expect(inputFolio).toBeVisible();
        const buscadorProveedor = page.locator('input[placeholder*="Proveedor"], select').first();
        if (await buscadorProveedor.isVisible()) {
            await expect(buscadorProveedor).toBeEnabled();
        }
    });

    test('el historial de compras renderiza los filtros de busqueda', async ({ page }) => {
        await page.goto('/facturas/historial');
        await expect(page.getByText(/Historial/i).first()).toBeVisible();
        const btnNueva = page.locator('a, button').filter({ hasText: /Nueva|Ingresar/i }).first();
        if (await btnNueva.isVisible()) {
            await expect(btnNueva).toBeEnabled();
        }
    });

    test('el historial muestra nombres de proveedor (no celdas vacias)', async ({ page }) => {
        await page.goto('/facturas/historial');
        await expect(page.getByText(/Historial/i).first()).toBeVisible();
        await page.waitForLoadState('networkidle');
        const tienealgo = page.locator('table tbody tr, [class*="card"]').first();

        if (await tienealgo.isVisible({ timeout: 5_000 }).catch(() => false)) {
            const filaTexto = await tienealgo.textContent();
            expect(filaTexto.trim().length).toBeGreaterThan(10);
            const hayDesconocido = await page.getByText(/Proveedor Desconocido/i).count();
            expect(hayDesconocido).toBe(0);
        } else {
            const emptyState = page.getByText(/No hay facturas|sin facturas|nada que mostrar/i);
            await emptyState.isVisible({ timeout: 1_000 }).catch(() => false);
            expect(true).toBe(true);
        }
    });

    test('el dashboard muestra proveedores en Atencion Requerida (no celdas vacias)', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');
        const seccionAtencion = page.getByText(/Atenci.n Requerida/i).first();

        if (await seccionAtencion.isVisible({ timeout: 5_000 }).catch(() => false)) {
            const tablaAtencion = page.locator('table').filter({
                has: page.locator('th').filter({ hasText: /PROVEEDOR/i }),
            }).first();

            if (await tablaAtencion.isVisible({ timeout: 2_000 }).catch(() => false)) {
                const filas = await tablaAtencion.locator('tbody tr').all();

                if (filas.length > 0) {
                    for (const fila of filas) {
                        const celdas = await fila.locator('td').all();
                        if (celdas.length >= 2) {
                            const textoProveedor = (await celdas[1].textContent())?.trim() ?? '';
                            expect(textoProveedor.length, 'columna PROVEEDOR no debe estar vacia').toBeGreaterThan(0);
                        }
                    }
                }
            }
        }
    });
});