import { execSync } from 'child_process';
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
}
