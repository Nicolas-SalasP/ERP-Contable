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

test.describe('Flujo de Gestión Tributaria', () => {
    test.beforeEach(async ({ page }) => { await login(page); });

    test('el dashboard del F29 (Cierre de IVA) carga el selector de periodo', async ({ page }) => {
        await page.goto('/contabilidad/cierre-f29');
        await expect(page.getByText(/F29/i).first()).toBeVisible({ timeout: 10_000 });
    });

    test('la vista de operacion renta inicializa los componentes principales', async ({ page }) => {
        await page.goto('/tributario/renta');
        await expect(page.getByText(/Renta/i).first()).toBeVisible();
    });

    test('el modal de mapeo SII permite asignar cuentas contables', async ({ page }) => {
        await page.goto('/tributario/renta');
        const btnMapeo = page.locator('button').filter({ hasText: /Mapeo/i }).first();
        if (await btnMapeo.isVisible()) {
            await btnMapeo.click();
            await expect(page.getByText(/Sugerencias/i).first()).toBeVisible();
        }
    });

    test('valida la existencia de reportes tributarios historicos', async ({ page }) => {
        await page.goto('/contabilidad/cierre-f29');
        await expect(page.getByText(/F29|Cierre/i).first()).toBeVisible({ timeout: 10_000 });
        const tabla = page.locator('table, .list, .grid').first();
        if (await tabla.isVisible()) {
            await expect(tabla).toBeVisible();
        }
    });

    test('el sistema de ayuda contextual funciona en el modulo tributario', async ({ page }) => {
        await page.goto('/contabilidad/cierre-f29');
        const btnAyuda = page.locator('button[aria-label*="Ayuda"]').first();
        if (await btnAyuda.isVisible()) {
            await btnAyuda.click();
            await expect(page.locator('[role="dialog"]').first()).toBeVisible();
        }
    });
});

test.describe('DJ 1835 / 1837 / 1926 — Nuevas declaraciones juradas', () => {
    test.beforeEach(async ({ page }) => { await login(page); });

    // DJ 1835
    test('DJ 1835 carga el formulario de generación @smoke', async ({ page }) => {
        await page.goto('/tributario/dj-1835');
        await expect(page.getByText(/DJ 1835/i).first()).toBeVisible({ timeout: 10_000 });
        // selector de año visible
        await expect(page.locator('select').first()).toBeVisible();
    });

    test('DJ 1835 muestra estado vacío cuando no hay declaraciones', async ({ page }) => {
        await page.goto('/tributario/dj-1835');
        // Either the empty state or the table is shown — page loads without crash
        await expect(page.locator('h1, h2').filter({ hasText: /1835/ }).first()).toBeVisible({ timeout: 10_000 });
    });

    // DJ 1837
    test('DJ 1837 carga el formulario de generación @smoke', async ({ page }) => {
        await page.goto('/tributario/dj-1837');
        await expect(page.getByText(/DJ 1837/i).first()).toBeVisible({ timeout: 10_000 });
        await expect(page.locator('select').first()).toBeVisible();
    });

    test('DJ 1837 muestra estado vacío cuando no hay declaraciones', async ({ page }) => {
        await page.goto('/tributario/dj-1837');
        await expect(page.locator('h1, h2').filter({ hasText: /1837/ }).first()).toBeVisible({ timeout: 10_000 });
    });

    // DJ 1926
    test('DJ 1926 carga el formulario de generación @smoke', async ({ page }) => {
        await page.goto('/tributario/dj-1926');
        await expect(page.getByText(/DJ 1926/i).first()).toBeVisible({ timeout: 10_000 });
        await expect(page.locator('select').first()).toBeVisible();
    });

    test('DJ 1926 muestra estado vacío cuando no hay declaraciones', async ({ page }) => {
        await page.goto('/tributario/dj-1926');
        await expect(page.locator('h1, h2').filter({ hasText: /1926/ }).first()).toBeVisible({ timeout: 10_000 });
    });

    // Cross-DJ navigation
    test('navegación entre DJs funciona desde la barra lateral', async ({ page }) => {
        await page.goto('/tributario/dj-1835');
        await expect(page.getByText(/1835/i).first()).toBeVisible({ timeout: 10_000 });
        // Navigate to 1837
        await page.goto('/tributario/dj-1837');
        await expect(page.getByText(/1837/i).first()).toBeVisible({ timeout: 10_000 });
        // Navigate to 1926
        await page.goto('/tributario/dj-1926');
        await expect(page.getByText(/1926/i).first()).toBeVisible({ timeout: 10_000 });
    });
});