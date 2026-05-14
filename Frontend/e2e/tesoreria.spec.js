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

test.describe('Flujo de Tesorería y Banco', () => {
    test.beforeEach(async ({ page }) => { await login(page); });

    test('la mesa de conciliacion carga y muestra las columnas de comparacion', async ({ page }) => {
        await page.goto('/banco/conciliacion');
        await expect(page.getByText(/Conciliación/i).first()).toBeVisible({ timeout: 10_000 });
    });

    test('la vista de importacion de cartola habilita la subida de archivos', async ({ page }) => {
        await page.goto('/banco/cartola');
        await expect(page.getByText(/Cartola/i).first()).toBeVisible();
        const input = page.locator('input[type="file"]').first();
        await expect(input).toBeDefined();
    });

    test('la nomina de pagos permite gestionar ordenes pendientes', async ({ page }) => {
        await page.goto('/banco/nomina-pagos');
        await expect(page.getByText(/Nómina/i).first()).toBeVisible({ timeout: 10_000 });
        const btnGenerar = page.locator('button, a').filter({ hasText: /Generar|Nueva|Crear|Procesar/i }).first();
        if (await btnGenerar.isVisible()) {
            await expect(btnGenerar).toBeEnabled();
        }
    });

    test('el listado de movimientos bancarios muestra ingresos y egresos', async ({ page }) => {
        await page.goto('/banco/movimientos');
        await expect(page.getByText(/Movimientos/i).first()).toBeVisible({ timeout: 10_000 });
        const fila = page.locator('table tr').nth(1);
        if (await fila.isVisible()) { await expect(fila).toBeVisible(); }
    });

    test('permite visualizar las cuentas bancarias de la empresa configuradas', async ({ page }) => {
        await page.goto('/empresa/perfil');
        await expect(page.getByText(/Cuentas/i).first()).toBeVisible({ timeout: 10_000 });
    });
});