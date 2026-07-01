import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const backendDir = path.resolve(__dirname, '../../Backend-laravel');
const baseUrl    = process.env.E2E_BASE_URL  || 'http://localhost:3000';
const isRemote   = !baseUrl.includes('localhost') && !baseUrl.includes('127.0.0.1');
const backendUrl = process.env.E2E_API_URL || (isRemote ? baseUrl : 'http://localhost:8001');

async function backendReachable() {
    try {
        const res = await fetch(`${backendUrl}/api/health`, { signal: AbortSignal.timeout(3000) });
        return res.ok || res.status === 404 || res.status === 429;
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

    if (!isRemote) {
        try {
            execSync('php artisan tenri:e2e-setup', {
                cwd: backendDir,
                stdio: 'inherit',
            });
        } catch (err) {
            console.warn('[e2e global-setup] Advertencia al preparar usuario e2e:', err.message);
        }
    }

    const email    = process.env.E2E_USER_EMAIL    || 'e2e_runner@tenri.cl';
    const password = process.env.E2E_USER_PASSWORD || 'E2ePassword_2026';

    const loginRes = await fetch(`${backendUrl}/api/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, password }),
        signal: AbortSignal.timeout(10_000),
    });
    const loginData = await loginRes.json();
    const token = loginData.token;

    if (!token) {
        throw new Error(`[e2e global-setup] Login falló: ${JSON.stringify(loginData)}`);
    }

    const meRes = await fetch(`${backendUrl}/api/auth/me`, {
        headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/json' },
        signal: AbortSignal.timeout(10_000),
    });
    const userData = await meRes.json();

    if (!userData || !userData.id) {
        throw new Error(`[e2e global-setup] /auth/me falló: ${JSON.stringify(userData)}`);
    }

    const authDir  = path.join(__dirname, '.auth');
    const authFile = path.join(authDir, 'user.json');

    if (!fs.existsSync(authDir)) fs.mkdirSync(authDir, { recursive: true });

    const storageState = {
        cookies: [],
        origins: [
            {
                origin: baseUrl,
                localStorage: [
                    { name: 'erp_token', value: token },
                    { name: 'erp_token_issued_at', value: String(Date.now()) },
                    { name: 'erp_user', value: JSON.stringify(userData) },
                ],
            },
        ],
    };

    fs.writeFileSync(authFile, JSON.stringify(storageState, null, 2), 'utf-8');
    console.log(`[e2e global-setup] storageState guardado en ${authFile} (usuario: ${userData.email})`);
}
