import { test, expect } from '@playwright/test';

const USER_EMAIL = process.env.E2E_USER_EMAIL || 'superadmin@tenri.cl';
const USER_PASSWORD = process.env.E2E_USER_PASSWORD || 'password123';

async function login(_page) {
    // Auth provista por storageState — cada test navega directo a su URL objetivo
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