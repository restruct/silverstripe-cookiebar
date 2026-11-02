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

// --- CMS change tracker (issue #8) ---------------------------------------------------------------
//
// Why the SS6 settings spec threw "Cannot read properties of null (reading
// 'prepValueForChangeTracker')" now and then: silverstripe/admin's changetracker (SS6 / admin 3)
// re-reads every field of the edit form on each change event that reaches the form, through a lodash
// debounce of 250ms with leading AND trailing calls, one debounce per event target. For an HTML
// editor field (CookieBarContent) that read is textarea.entwine('ss').getEditor()
// .prepValueForChangeTracker(). The UploadField fires a change on the form when the file is added and
// again when the upload returns; when those two land within 250ms, the second re-read is deferred to
// the debounce's trailing edge. A save inside that window whose response replaces the form first
// leaves the deferred re-read running against the removed form, where entwine has already dropped the
// textarea's Editor: getEditor() is null. A person never saves that fast; a spec does. SS5's
// changetracker never calls prepValueForChangeTracker, so it cannot throw this.
//
// The probe below makes that deferred re-read observable, so a spec can wait for it instead of
// sleeping: a change event on the edit form marks the tracker "pending", the tracker's own re-read of
// the HTML editor field clears it again (at once for a leading call, after 250ms for a trailing one).

/**
 * Install the change-tracker probe on the CMS page that is open now. Call it after every full page
 * load of an edit form (the probe lives in the page). A no-op where the changetracker never reads the
 * editor (SS5), so changeTrackerSettled() then returns at once.
 */
export async function probeChangeTracker(page: Page): Promise<void> {
    await page.evaluate(() => {
        const w = window as any;
        const $ = w.jQuery;
        if (w.__ckbProbe || !$?.fn?.changetracker || !w.ss?.editorWrappers) return;
        if (!String($.fn.changetracker).includes('prepValueForChangeTracker')) return;
        const probe = (w.__ckbProbe = { pending: false });
        // Wrap one editor instance: its prepValueForChangeTracker is the changetracker's re-read.
        const wrap = (editor: any) => {
            if (editor && !editor.__ckbProbed) {
                const read = editor.prepValueForChangeTracker;
                editor.prepValueForChangeTracker = function (value: unknown) {
                    probe.pending = false;
                    return read.call(this, value);
                };
                editor.__ckbProbed = true;
            }
            return editor;
        };
        // Editors created from now on: a save re-renders the form and builds new ones.
        for (const key of Object.keys(w.ss.editorWrappers)) {
            const make = w.ss.editorWrappers[key];
            w.ss.editorWrappers[key] = function (...args: unknown[]) {
                return wrap(make.apply(this, args));
            };
        }
        // The editors already on the page.
        $('textarea.htmleditor').each(function (this: HTMLElement) {
            wrap($(this).entwine('ss').getEditor());
        });
        // preDispatch runs before the form's own change handlers, so a leading re-read (same
        // dispatch) clears the flag again before the dispatch ends. Native and triggered events alike.
        const special = ($.event.special.change ||= {});
        const before = special.preDispatch;
        special.preDispatch = function (this: HTMLElement, event: unknown) {
            if (this.id === 'Form_EditForm') probe.pending = true;
            return before ? before.call(this, event) : undefined;
        };
    });
}

/**
 * Wait until the changetracker has re-read the form after its last change event, i.e. no deferred
 * (trailing) re-read is left that a save could orphan. Needs probeChangeTracker() on this page load.
 * Caveat: a change event on the HTML editor textarea itself also clears the flag early (the per-field
 * handler reads that one field); no spec here edits that field.
 */
export async function changeTrackerSettled(page: Page): Promise<void> {
    await expect
        .poll(() => page.evaluate(() => (window as any).__ckbProbe?.pending ?? false), {
            message: 'the CMS changetracker has re-read the form after its last change event (#8)',
        })
        .toBe(false);
}
