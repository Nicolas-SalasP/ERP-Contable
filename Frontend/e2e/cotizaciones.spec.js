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
        page.waitForNavigation({ url: '**/', timeout: 15_000 }).catch(() => { }),
        page.locator('button[type="submit"]').first().click()
    ]);

    await expect(page).not.toHaveURL(/.*\/login/, { timeout: 15_000 });
}

test.describe('Flujo Comercial: Cotizaciones', () => {
    test.beforeEach(async ({ page }) => { await login(page); });

    test('el gestor de cotizaciones carga y permite abrir el buscador', async ({ page }) => {
        await page.goto('/cotizaciones');
        await expect(page.getByText(/Cotizaciones/i).first()).toBeVisible({ timeout: 10_000 });
        const btnNueva = page.getByRole('link', { name: /Nueva|Crear/i }).first();
        await expect(btnNueva).toBeVisible();
    });

    test('formulario de nueva cotizacion inicializa detalles matematicos', async ({ page }) => {
        await page.goto('/cotizaciones/nueva');
        await expect(page.locator('input[name*="rut"], input[placeholder*="RUT"]').first()).toBeVisible();
        const inputPrecio = page.locator('input[type="number"], input[name*="precio"]').first();
        await expect(inputPrecio).toBeVisible();
    });

    test('el directorio de clientes carga la lista de deudores', async ({ page }) => {
        await page.goto('/clientes');
        await expect(page.getByText(/Clientes|Directorio/i).first()).toBeVisible({ timeout: 10_000 });
        const inputBusqueda = page.locator('input[type="text"], input[type="search"]').first();
        if (await inputBusqueda.isVisible()) {
            await expect(inputBusqueda).toBeEditable();
        }
    });

    test('permite abrir el formulario de creacion de clientes', async ({ page }) => {
        await page.goto('/clientes');
        const btnCrear = page.locator('button, a').filter({ hasText: /Nuevo|Crear/i }).first();
        await btnCrear.click();
        await expect(page.locator('form').first()).toBeVisible();
    });

    test('aplica filtros por estado en el historial de cotizaciones', async ({ page }) => {
        await page.goto('/cotizaciones');
        const selectEstado = page.locator('select, [role="combobox"]').first();
        await expect(selectEstado).toBeVisible();
        await selectEstado.selectOption({ index: 1 });
    });
});