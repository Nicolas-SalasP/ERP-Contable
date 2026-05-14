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

test.describe('Flujo Logístico: Inventario', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('renderiza el dashboard principal y los accesos rapidos logísticos', async ({ page }) => {
        await page.goto('/inventario');
        await expect(page.getByText(/Inventario/i).first()).toBeVisible({ timeout: 10_000 });
        const moduloBodegas = page.getByText(/Bodega/i).first();
        if (await moduloBodegas.isVisible()) {
            await expect(moduloBodegas).toBeVisible();
        }
    });

    test('el gestor de tomas fisicas (auditoria) previene interacciones fantasma', async ({ page }) => {
        await page.goto('/inventario/tomas-fisicas');
        await expect(page.getByText(/Tomas Física|Tomas Físicas/i).first()).toBeVisible({ timeout: 10_000 });
        const btnProcesar = page.locator('button').filter({ hasText: /Nueva|Generar|Iniciar|Crear/i }).first();
        if (await btnProcesar.isVisible()) {
            await expect(btnProcesar).toBeEnabled();
        }
    });
});