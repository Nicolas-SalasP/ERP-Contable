import { defineConfig, devices } from '@playwright/test';
import { config as loadEnv } from 'dotenv';
import path from 'path';

loadEnv({ path: path.resolve(process.cwd(), '.env.e2e') });

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.js',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: process.env.CI
        ? [['github'], ['html', { outputFolder: 'playwright-report' }]]
        : 'html',

    timeout: 60_000,

    expect: {
        timeout: 45_000,
    },

    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://localhost:3000',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
        storageState: 'e2e/.auth/user.json',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: process.env.E2E_BASE_URL && !process.env.E2E_BASE_URL.includes('localhost')
        ? []
        : [
            {
                command: 'pnpm dev',
                url: 'http://localhost:3000',
                reuseExistingServer: true,
                timeout: 120_000,
            },
        ],
});
