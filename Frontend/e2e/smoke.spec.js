import { test, expect } from '@playwright/test';

test.describe('@smoke - Sistema vivo', () => {
    test('la app carga y muestra el login', async ({ page }) => {
        await page.goto('/');
        await expect(page).toHaveTitle(/ERP|Atlas|Contable/i, { timeout: 10_000 });
        const body = await page.locator('body').textContent();
        expect(body).toBeTruthy();
    });

    test('la pagina de login se renderiza correctamente', async ({ page }) => {
        await page.goto('/login');

        // Esperar input de email o usuario
        const inputEmail = page.locator('input[type="email"], input[name="email"]').first();
        await expect(inputEmail).toBeVisible({ timeout: 5_000 });

        // Esperar input de password
        const inputPassword = page.locator('input[type="password"]').first();
        await expect(inputPassword).toBeVisible();

        // Esperar boton de submit
        const botonSubmit = page.locator('button[type="submit"], button:has-text("Ingresar"), button:has-text("Iniciar")').first();
        await expect(botonSubmit).toBeVisible();
    });

    test('el glosario es accesible sin autenticacion (si la app lo permite)', async ({ page }) => {
        await page.goto('/glosario');
        await page.waitForLoadState('networkidle');
        const body = await page.locator('body').textContent();
        expect(body.length).toBeGreaterThan(50);
    });
});
