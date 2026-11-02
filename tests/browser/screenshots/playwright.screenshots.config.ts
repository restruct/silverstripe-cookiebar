import { defineConfig, devices } from '@playwright/test';
import * as path from 'path';

// README screenshots, NOT tests: writes docs/images/*.png from a Silverstripe 6 host with the
// browser fixtures loaded. Kept apart from ../playwright.config.ts on purpose: the browser tests
// (and CI) run that config, which only matches specs/*.spec.ts, and must never rewrite
// docs/images/. How to run: README.md next to this file.

// The same BROWSER_TARGET_URLS the runner passes, but only ONE target is used: two targets would
// overwrite each other's images. ss6 when present, else the first one.
const targets = (process.env.BROWSER_TARGET_URLS ?? '')
    .split(',')
    .map((pair) => pair.trim())
    .filter(Boolean)
    .map((pair) => {
        const [name, url] = pair.split('=');
        return { name, url };
    });
const target = targets.find((t) => t.name === 'ss6') ?? targets[0];

if (!target) {
    throw new Error(
        'BROWSER_TARGET_URLS is empty. Run through tools/browser/run.sh (see screenshots/README.md), ' +
            'or set it by hand, e.g. BROWSER_TARGET_URLS="ss6=http://127.0.0.1:8868"',
    );
}

// tests/browser/, where the specs, the saved logins and the Playwright output live.
const BROWSER_DIR = path.join(__dirname, '..');

export default defineConfig({
    testDir: BROWSER_DIR,
    outputDir: path.join(BROWSER_DIR, 'test-results'),
    retries: 0,
    // One admin session, shared, and the shots run in order (the CMS one saves the settings the
    // front-end ones show); see ../playwright.config.ts for why that also means one worker.
    workers: 1,
    timeout: 60_000,
    expect: { timeout: 10_000 },
    reporter: [['list']],
    use: {
        ...devices['Desktop Chrome'],
        // ~1280 wide, at 1x, so the PNGs are the width a README shows them at.
        viewport: { width: 1280, height: 800 },
        deviceScaleFactor: 1,
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: `${target.name}-login`,
            testMatch: /specs\/login\.setup\.ts/,
            use: { baseURL: target.url },
        },
        {
            name: target.name,
            testMatch: /screenshots\/.*\.shot\.ts/,
            dependencies: [`${target.name}-login`],
            // login.setup.ts writes .auth/<target>.json relative to the working directory, which is
            // tests/browser/ (the runner and the README both run Playwright from there).
            use: { baseURL: target.url, storageState: path.join(BROWSER_DIR, '.auth', `${target.name}.json`) },
        },
    ],
});
