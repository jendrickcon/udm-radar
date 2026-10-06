import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import process from 'node:process';
import { defineConfig } from '@playwright/test';

const localEnvFile = resolve('.env.playwright.local');
if (existsSync(localEnvFile)) {
    process.loadEnvFile(localEnvFile);
}

const configuredBaseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost/capstone';
const baseURL = configuredBaseURL.endsWith('/') ? configuredBaseURL : `${configuredBaseURL}/`;

export default defineConfig({
    testDir: './tests/browser',
    testMatch: '**/*.spec.js',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    workers: 1,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],
    outputDir: 'test-results',
    use: {
        baseURL,
        browserName: 'chromium',
        channel: 'msedge',
        screenshot: 'off',
        trace: 'off',
        video: 'off',
    },
});