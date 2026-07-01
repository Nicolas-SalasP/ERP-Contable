import { test, expect } from '@playwright/test';

const USER_EMAIL = process.env.E2E_USER_EMAIL || 'superadmin@tenri.cl';
const USER_PASSWORD = process.env.E2E_USER_PASSWORD || 'password123';

async function login(_page) {
    // Auth provista por storageState — cada test navega directo a su URL objetivo
}

test.describe('Módulo RRHH y Remuneraciones', () => {
    test.beforeEach(async ({ page }) => { await login(page); });

    test('la ficha de personal carga la lista de empleados', async ({ page }) => {
        await page.goto('/rrhh/empleados');
        await expect(page.getByText(/Ficha de Personal/i).first()).toBeVisible({ timeout: 10_000 });
        const buscador = page.locator('input[placeholder*="Buscar"]').first();
        await expect(buscador).toBeVisible();
    });

    test('la vista de contratos pide seleccionar un empleado', async ({ page }) => {
        await page.goto('/rrhh/contratos');
        await expect(page.getByText(/Contratos/i).first()).toBeVisible({ timeout: 10_000 });
        await expect(page.locator('select').first()).toBeVisible();
    });

    test('las liquidaciones de sueldo muestran filtros de periodo', async ({ page }) => {
        await page.goto('/rrhh/liquidaciones');
        await expect(page.getByText(/Liquidaciones de Sueldo/i).first()).toBeVisible({ timeout: 10_000 });
        await expect(page.locator('select').first()).toBeVisible();
    });

    test('los finiquitos renderizan su encabezado legal', async ({ page }) => {
        await page.goto('/rrhh/finiquitos');
        await expect(page.getByText(/Finiquitos/i).first()).toBeVisible({ timeout: 10_000 });
    });

    test('los parametros previsionales muestran las pestañas', async ({ page }) => {
        await page.goto('/rrhh/parametros');
        await expect(page.getByText(/Parámetros Previsionales/i).first()).toBeVisible({ timeout: 10_000 });
        await expect(page.getByText(/Indicadores UF\/UTM/i).first()).toBeVisible();
    });

    test('la centralizacion contable muestra el mapeo de cuentas', async ({ page }) => {
        await page.goto('/rrhh/centralizacion');
        await expect(page.getByText(/Centralización Contable/i).first()).toBeVisible({ timeout: 10_000 });
        await expect(page.getByText(/Mapeo contable/i).first()).toBeVisible();
    });

    test('el archivo previred ofrece previsualizar y descargar', async ({ page }) => {
        await page.goto('/rrhh/previred');
        await expect(page.getByText(/Archivo Previred/i).first()).toBeVisible({ timeout: 10_000 });
        await expect(page.getByRole('button', { name: /Previsualizar/i }).first()).toBeVisible();
    });

    test('el modal de ayuda se abre desde la ficha de personal', async ({ page }) => {
        await page.goto('/rrhh/empleados');
        await expect(page.getByText(/Ficha de Personal/i).first()).toBeVisible({ timeout: 10_000 });
        // Cerrar cualquier dialog/alert que pueda bloquear el click
        await page.keyboard.press('Escape');
        await page.waitForTimeout(300);
        await page.getByTestId('ayuda-modulo-boton').first().click();
        await expect(page.getByTestId('ayuda-modulo-modal')).toBeVisible({ timeout: 5_000 });
        await expect(page.getByText(/Como se usa/i).first()).toBeVisible();
    });
});
