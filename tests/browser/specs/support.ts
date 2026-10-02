import { test as base, expect, type Page } from '@playwright/test';

// Shared fixtures and helpers for the cookiebar specs.
//
// The front-end page is the fixture page type in tests/browser/fixtures/ (copied into the scratch
// host by the runner): /cookie-home and /cookie-home/cookie-child render $CookieBar before </body>,
// /cookie-policy is the "more information" page. Every dev/build re-seeds them and resets the
// SiteConfig's CookieBar settings (fixtures/CkBPage.php), including the two scripts, which append
// 'init' and 'consent' to window.ckbTrace.

export const HOME = '/cookie-home';
export const CHILD = '/cookie-home/cookie-child';
export const POLICY = '/cookie-policy';
export const COOKIE = 'cookie_consent';

/**
 * test, extended with an automatic console guard: every spec fails if the page logs a console
 * error or throws an uncaught exception at any point, page load included ("Failed to load
 * resource" for a missing stylesheet arrives as a console error too).
 */
export const test = base.extend<{ consoleGuard: void }>({
    consoleGuard: [
        async ({ page }, use, testInfo) => {
            const errors: string[] = [];
            page.on('console', (msg) => {
                if (msg.type() === 'error') {
                    errors.push(`console.error: ${msg.text()} (${msg.location().url})`);
                }
            });
            page.on('pageerror', (err) => errors.push(`uncaught: ${err.message}`));

            await use();

            if (errors.length) {
                await testInfo.attach('console-errors', { body: errors.join('\n'), contentType: 'text/plain' });
            }
            expect(errors, 'no console errors or uncaught exceptions').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };

/** The bar CookieBar.js inserted at the top of <body> (the JS template's copy, not the noscript one). */
export function bar(page: Page) {
    return page.locator('body > #cookiebar');
}

/** What the two SiteConfig scripts recorded on this page load ('init', 'consent'). */
export async function trace(page: Page): Promise<string[]> {
    return page.evaluate(() => (window as any).ckbTrace ?? []);
}

/** The consent cookie in this browser context, if any. */
export async function consentCookie(page: Page) {
    return (await page.context().cookies()).find((c) => c.name === COOKIE);
}

/** Whether the page carries the bar's assets: cookiebar.css and the inlined CookieBar.js. */
export async function barAssets(page: Page): Promise<{ css: number; js: number }> {
    return page.evaluate(() => ({
        css: document.querySelectorAll('link[href*="silverstripe-cookiebar/client/dist/css/cookiebar"]').length,
        // javascriptTemplate() inlines CookieBar.js; its webpack licence banner identifies it.
        js: [...document.scripts].filter((s) => (s.textContent ?? '').includes('CookieBar.js.LICENSE')).length,
    }));
}

/**
 * Record every DOCUMENT request of the main frame from now on. The JS Accept must set the cookie
 * in the page, not by following the accept link. Returns a getter for the URLs seen.
 */
export function watchDocumentNavigations(page: Page): () => string[] {
    const seen: string[] = [];
    page.on('request', (r) => {
        if (r.isNavigationRequest() && r.frame() === page.mainFrame()) {
            seen.push(`${r.method()} ${r.url()}`);
        }
    });
    return () => [...seen];
}
