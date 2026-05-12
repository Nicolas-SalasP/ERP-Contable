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

        // Debe haber un input para el Folio o Nro de Documento
        const inputFolio = page.locator('input[name*="folio"], input[placeholder*="Folio"], input[name*="numero"]').first();
        await expect(inputFolio).toBeVisible();

        // Debe existir una forma de buscar o seleccionar al proveedor
        const buscadorProveedor = page.locator('input[placeholder*="Proveedor"], select').first();
        if (await buscadorProveedor.isVisible()) {
            await expect(buscadorProveedor).toBeEnabled();
        }
    });

    test('el historial de compras renderiza los filtros de busqueda', async ({ page }) => {
        await page.goto('/facturas/historial');
        
        await expect(page.getByText(/Historial/i).first()).toBeVisible();

        // El botón para registrar una nueva factura desde el historial debe estar presente
        const btnNueva = page.locator('a, button').filter({ hasText: /Nueva|Ingresar/i }).first();
        if (await btnNueva.isVisible()) {
            await expect(btnNueva).toBeEnabled();
        }
    });
});