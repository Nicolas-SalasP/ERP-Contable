import { execSync } from 'child_process';
import { chromium } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const backendDir = path.resolve(__dirname, '../../Backend-laravel');
const backendUrl = process.env.E2E_API_URL || 'http://localhost:8001';

async function backendReachable() {
    try {
        const res = await fetch(`${backendUrl}/api/health`, { signal: AbortSignal.timeout(3000) });
        return res.ok || res.status === 404;
    } catch {
        return false;
    }
}

export default async function globalSetup() {
    const ok = await backendReachable();
    if (!ok) {
        throw new Error(
            `\n\n❌ Backend no responde en ${backendUrl}\n` +
            `   Inicia el servidor antes de correr e2e:\n` +
            `   cd Backend-laravel && php artisan serve --port=8001\n`
        );
    }

    try {
        execSync('php artisan tenri:e2e-setup', {
            cwd: backendDir,
            stdio: 'inherit',
        });
    } catch (err) {
        console.warn('[e2e global-setup] Advertencia al preparar usuario e2e:', err.message);
    }

    const authDir = path.join(__dirname, '.auth');
    if (!fs.existsSync(authDir)) fs.mkdirSync(authDir, { recursive: true });

    const browser = await chromium.launch();
    const context = await browser.newContext();
    const page = await context.newPage();

    const loginRes = await page.request.post(
        `${backendUrl}/api/auth/login`,
        { data: { email: process.env.E2E_USER_EMAIL, password: process.env.E2E_USER_PASSWORD } }
    );
    const loginData = await loginRes.json();
    const token = loginData.token;

    if (!token) throw new Error(`globalSetup: login falló — ${JSON.stringify(loginData)}`);

    await page.goto(process.env.E2E_BASE_URL || 'http://localhost:3000');
    await page.waitForLoadState('networkidle');

    await page.evaluate((t) => {
        localStorage.setItem('erp_token', t);
        localStorage.setItem('erp_token_issued_at', Date.now().toString());
    }, token);

    await context.storageState({ path: path.join(authDir, 'user.json') });
    await browser.close();
}
