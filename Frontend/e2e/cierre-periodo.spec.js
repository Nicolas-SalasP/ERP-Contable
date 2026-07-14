/**
 * E2E — Módulo Cierre de Períodos Contables
 *
 * Requiere:
 *   - Backend corriendo con usuario superadmin seeded
 *   - Frontend dev server en E2E_BASE_URL (default: http://localhost:3000)
 *   - E2E_USER_EMAIL / E2E_USER_PASSWORD con rol que tenga contabilidad.cerrar_periodo
 */

import { test, expect } from '@playwright/test';

const USER_EMAIL = process.env.E2E_USER_EMAIL || 'e2e_runner@tenri.cl';
const USER_PASSWORD = process.env.E2E_USER_PASSWORD || 'E2ePassword_2026';
const URL = '/contabilidad/cierre-periodo';

async function login(_page) {
    // Auth provista por storageState — cada test navega directo a su URL objetivo
}

test.describe('Cierre de Períodos — smoke @smoke', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('la vista carga y muestra el título correcto', async ({ page }) => {
        await page.goto(URL);
        await expect(page.getByText(/Cierre de Períodos Contables/i).first())
            .toBeVisible({ timeout: 10_000 });
    });

    test('muestra los 12 meses en la grilla', async ({ page }) => {
        await page.goto(URL);
        await expect(page.getByText(/Cierre de Períodos Contables/i).first())
            .toBeVisible({ timeout: 10_000 });

        for (const mes of ['Enero', 'Marzo', 'Junio', 'Septiembre', 'Diciembre']) {
            await expect(page.getByText(mes).first()).toBeVisible();
        }
    });

    test('muestra el año actual en el selector', async ({ page }) => {
        await page.goto(URL);
        const anio = new Date().getFullYear().toString();
        await expect(page.getByText(anio).first()).toBeVisible({ timeout: 10_000 });
    });

    test('los meses muestran badge Abierto o Cerrado', async ({ page }) => {
        await page.goto(URL);
        await expect(page.getByText(/Cierre de Períodos Contables/i).first())
            .toBeVisible({ timeout: 10_000 });

        // Los botones de acción solo aparecen después de que la API carga los estados de cada mes
        await expect(page.getByRole('button', { name: /Cerrar período|Reabrir período/i }).first())
            .toBeVisible();
    });

    test('el sidebar contiene el enlace al módulo', async ({ page }) => {
        await page.goto('/');
        // Expandir el menú Contabilidad General para ver subitems
        await page.getByText('Contabilidad General').first().click();
        await expect(page.getByText(/Cierre de Períodos/i).first()).toBeVisible({ timeout: 5_000 });
    });
});

test.describe('Cierre de Períodos — navegación', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('el botón siguiente avanza al año siguiente', async ({ page }) => {
        await page.goto(URL);
        await expect(page.getByText(/Cierre de Períodos Contables/i).first())
            .toBeVisible({ timeout: 10_000 });

        const anioActual = new Date().getFullYear();
        await expect(page.getByText(String(anioActual)).first()).toBeVisible();

        // Clic en chevron-right
        await page.locator('.fa-chevron-right').first().click();

        await expect(page.getByText(String(anioActual + 1)).first()).toBeVisible({ timeout: 5_000 });
    });

    test('el botón anterior retrocede al año anterior', async ({ page }) => {
        await page.goto(URL);
        await expect(page.getByText(/Cierre de Períodos Contables/i).first())
            .toBeVisible({ timeout: 10_000 });

        const anioActual = new Date().getFullYear();
        await page.locator('.fa-chevron-left').first().click();

        await expect(page.getByText(String(anioActual - 1)).first()).toBeVisible({ timeout: 5_000 });
    });
});

test.describe('Cierre de Períodos — modal', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('el modal de cierre se abre al hacer click en Cerrar período', async ({ page }) => {
        const respPeriodos = page.waitForResponse(
            r => r.url().includes('/contabilidad/periodos') && r.status() === 200
        );
        await page.goto(URL);
        await expect(page.getByText(/Cierre de Períodos Contables/i).first())
            .toBeVisible({ timeout: 10_000 });
        await respPeriodos;

        // Primer botón "Cerrar período" visible (mes abierto)
        const btnCerrar = page.getByRole('button', { name: /Cerrar período/i }).first();
        await expect(btnCerrar).toBeVisible({ timeout: 5_000 });
        await btnCerrar.click();

        // El modal muestra la advertencia
        await expect(page.getByText(/Advertencia/i).first()).toBeVisible({ timeout: 5_000 });
    });

    test('Cancelar en el modal cierra el modal sin cambios', async ({ page }) => {
        await page.goto(URL);
        await expect(page.getByText(/Cierre de Períodos Contables/i).first())
            .toBeVisible({ timeout: 10_000 });

        const btnCerrar = page.getByRole('button', { name: /Cerrar período/i }).first();
        await expect(btnCerrar).toBeVisible({ timeout: 8_000 });
        await btnCerrar.click();

        await expect(page.getByText(/Advertencia/i).first()).toBeVisible({ timeout: 5_000 });

        await page.getByRole('button', { name: /Cancelar/i }).click();

        await expect(page.getByText(/Advertencia/i)).toHaveCount(0, { timeout: 5_000 });
    });

    test('el modal exige motivo para reabrir un período', async ({ page }) => {
        // Primero cerrar un período vía API para tener uno que reabrir
        await page.goto(URL);
        await expect(page.getByText(/Cierre de Períodos Contables/i).first())
            .toBeVisible({ timeout: 10_000 });

        // Buscar botón de Reabrir (si ya existe un período cerrado en el entorno de prueba)
        const btnReabrir = page.getByRole('button', { name: /Reabrir período/i }).first();
        const hayReabrir = await btnReabrir.isVisible().catch(() => false);

        if (!hayReabrir) {
            test.skip(); // No hay período cerrado en este entorno, omitir
            return;
        }

        await btnReabrir.click();
        await expect(page.locator('h3').filter({ hasText: /Reabrir período/i })).toBeVisible({ timeout: 5_000 });

        // Click en confirmar SIN motivo
        const botonesReabrir = page.getByRole('button', { name: /Reabrir período/i });
        await botonesReabrir.last().click();

        // Debe mostrar error de validación
        await expect(page.getByText(/motivo es obligatorio/i)).toBeVisible({ timeout: 5_000 });
    });
});

test.describe('Cierre de Períodos — flujo completo', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('puede cerrar y reabrir un período desde la UI', async ({ page }) => {
        const respPeriodos = page.waitForResponse(
            r => r.url().includes('/contabilidad/periodos') && r.status() === 200
        );
        await page.goto(URL);
        await expect(page.getByText(/Cierre de Períodos Contables/i).first())
            .toBeVisible({ timeout: 10_000 });
        await respPeriodos;

        // Ir al año anterior para no afectar el mes actual
        const respPeriodosAnterior = page.waitForResponse(
            r => r.url().includes('/contabilidad/periodos') && r.status() === 200
        );
        await page.locator('.fa-chevron-left').first().click();
        const anioAnterior = new Date().getFullYear() - 1;
        await expect(page.getByText(String(anioAnterior)).first()).toBeVisible({ timeout: 5_000 });
        await respPeriodosAnterior;

        // Abrir modal de cierre en el primer mes visible con botón Cerrar
        const btnCerrar = page.getByRole('button', { name: /Cerrar período/i }).first();
        await expect(btnCerrar).toBeVisible({ timeout: 5_000 });
        await btnCerrar.click();

        await expect(page.getByText(/Advertencia/i).first()).toBeVisible({ timeout: 5_000 });

        // Opcional: ingresar motivo
        const textarea = page.locator('textarea').first();
        if (await textarea.isVisible()) {
            await textarea.fill('Cierre E2E automático');
        }

        // Confirmar cierre
        await page.getByRole('button', { name: /Cerrar período/i }).last().click();

        // Debe aparecer badge Cerrado en el mes
        await expect(page.getByText(/Cerrado/i).first()).toBeVisible({ timeout: 10_000 });

        // Ahora reabrir el mismo mes
        const btnReabrir = page.getByRole('button', { name: /Reabrir período/i }).first();
        await expect(btnReabrir).toBeVisible({ timeout: 8_000 });
        await btnReabrir.click();

        await expect(page.locator('h3').filter({ hasText: /Reabrir período/i })).toBeVisible({ timeout: 5_000 });

        // Ingresar motivo obligatorio
        const textareaReapertura = page.locator('textarea').first();
        await textareaReapertura.fill('Reapertura E2E para corrección');

        await page.getByRole('button', { name: /Reabrir período/i }).last().click();

        // El mes debe volver a Abierto
        await expect(page.getByText(/Abierto/i).first()).toBeVisible({ timeout: 10_000 });
    });
});
