import { test, expect } from '@playwright/test';

const USER_EMAIL = process.env.E2E_USER_EMAIL || 'admin@test.cl';
const USER_PASSWORD = process.env.E2E_USER_PASSWORD || 'password';

async function login(_page) {
    // Auth provista por storageState — cada test navega directo a su URL objetivo
}

test.describe('Flujo Activos Fijos', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('navega a /activos y muestra el modulo', async ({ page }) => {
        await page.goto('/activos');
        await expect(page.getByRole('heading', { name: 'Activos Fijos', level: 1 }))
            .toBeVisible({ timeout: 10_000 });
    });

    test('el icono de ayuda esta presente y abre el modal al click', async ({ page }) => {
        await page.goto('/activos');
        await expect(page.getByRole('heading', { name: 'Activos Fijos', level: 1 }))
            .toBeVisible();

        // Boton de ayuda (data-testid del componente AyudaModulo)
        const botonAyuda = page.getByTestId('ayuda-modulo-boton').first();
        await expect(botonAyuda).toBeVisible();
        await botonAyuda.click();

        // El modal debe abrir
        const modal = page.getByTestId('ayuda-modulo-modal');
        await expect(modal).toBeVisible({ timeout: 3_000 });

        // Cerrar con Escape
        await page.keyboard.press('Escape');
        await expect(modal).not.toBeVisible();
    });

    test('muestra las tabs de Pendientes / Registrados / Proyectos', async ({ page }) => {
        await page.goto('/activos');
        await expect(page.getByRole('heading', { name: 'Activos Fijos', level: 1 }))
            .toBeVisible();

        await expect(page.getByRole('button', { name: /Pendientes/ })).toBeVisible();
        await expect(page.getByRole('button', { name: /Activos Registrados/ })).toBeVisible();
        await expect(page.getByRole('button', { name: /Proyectos en Curso/ })).toBeVisible();
    });
});

test.describe('Flujo Glosario', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('el glosario muestra el listado de fichas', async ({ page }) => {
        await page.goto('/glosario');
        await expect(page.getByRole('heading', { name: /Glosario del Sistema/i, level: 1 }))
            .toBeVisible();
        await expect(page.getByText(/Asiento Manual/i).first()).toBeVisible({ timeout: 5_000 });
    });

    test('el buscador del glosario filtra resultados', async ({ page }) => {
        await page.goto('/glosario');
        await expect(page.getByText(/Asiento Manual/i).first()).toBeVisible();
        await page.getByPlaceholder(/Buscar/i).fill('IVA');
        await expect(page.getByText(/Cierre/i).first()).toBeVisible({ timeout: 3_000 });
    });
});
