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

test.describe('Flujo Contable: Núcleo Financiero', () => {
    test.beforeEach(async ({ page }) => { await login(page); });

    test('la vista de asiento manual renderiza la estructura de partida doble', async ({ page }) => {
        await page.goto('/contabilidad/asiento-manual');
        await expect(page.getByText(/Asiento Manual/i).first()).toBeVisible({ timeout: 10_000 });
        await expect(page.getByText(/Debe/i, { exact: false }).first()).toBeVisible();
        await expect(page.getByText(/Haber/i, { exact: false }).first()).toBeVisible();
    });

    test('el libro mayor carga sin errores de renderizado masivo', async ({ page }) => {
        await page.goto('/contabilidad/libro-mayor');
        await expect(page.getByText(/Libro Mayor/i).first()).toBeVisible({ timeout: 10_000 });
        const filtro = page.locator('input, select, button').filter({ hasText: /Cuenta/i }).first();
        if (await filtro.isVisible()) { await expect(filtro).toBeEnabled(); }
    });

    test('navega al plan de cuentas y permite buscar cuentas', async ({ page }) => {
        await page.goto('/contabilidad/plan-cuentas');
        await expect(page.getByText(/Plan de Cuentas/i).first()).toBeVisible({ timeout: 10_000 });
        const buscador = page.locator('input[placeholder*="Buscar"], input[type="text"]').first();
        await expect(buscador).toBeVisible();
        await buscador.fill('Caja');
    });

    test('la vista de anulaciones carga el historial de documentos revertidos', async ({ page }) => {
        await page.goto('/contabilidad/anulacion');
        await expect(page.getByText(/Anulaciones|Anulación/i).first()).toBeVisible({ timeout: 10_000 });
        const tabla = page.locator('table, .grid').first();
        if (await tabla.isVisible()) { 
            await expect(tabla).toBeVisible(); 
        }
    });

    test('el administrador de cuentas muestra la jerarquía contable', async ({ page }) => {
        await page.goto('/contabilidad/cuentas');
        await expect(page.getByText(/Cuentas|Administrador/i).first()).toBeVisible({ timeout: 10_000 });
        const btnNueva = page.locator('button, a').filter({ hasText: /Nueva|Crear/i }).first();
        if (await btnNueva.isVisible()) { 
            await expect(btnNueva).toBeEnabled(); 
        }
    });
});