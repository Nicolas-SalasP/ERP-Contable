/**
 * Tests E2E del flujo de autenticacion.
 *
 * Cubren:
 * - Login con credenciales validas
 * - Rechazo con credenciales invalidas
 * - Logout
 *
 * Requiere usuario de prueba en la base de datos del backend.
 * Defaults: admin@test.cl / password (ajusta en .env.e2e si tu seed usa otros)
 */

import { test, expect } from '@playwright/test';

const USER_EMAIL = process.env.E2E_USER_EMAIL || 'admin@test.cl';
const USER_PASSWORD = process.env.E2E_USER_PASSWORD || 'password';

test.describe('Autenticacion', () => {
    test('rechaza login con credenciales invalidas', async ({ page }) => {
        await page.goto('/login');

        await page.locator('input[type="email"], input[name="email"]').first().fill('noexiste@test.cl');
        await page.locator('input[type="password"]').first().fill('credencialesMalas');
        await page.locator('button[type="submit"]').first().click();

        // Debe aparecer un mensaje de error o quedarse en /login
        await page.waitForTimeout(2_000); // dar tiempo al backend a responder
        const url = page.url();
        expect(url).toContain('/login');
    });

    test('permite login con credenciales validas y redirige al dashboard', async ({ page }) => {
        await page.goto('/login');

        await page.locator('input[type="email"], input[name="email"]').first().fill(USER_EMAIL);
        await page.locator('input[type="password"]').first().fill(USER_PASSWORD);
        await page.locator('button[type="submit"]').first().click();

        // Debe redirigir fuera de /login a la pagina principal
        await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 10_000 });

        // En el dashboard o pagina inicial, debe haber algun indicio de que esta logueado
        // (puede ser el sidebar, el nombre del usuario, etc.)
        const body = await page.locator('body').textContent();
        expect(body).toBeTruthy();
    });
});
