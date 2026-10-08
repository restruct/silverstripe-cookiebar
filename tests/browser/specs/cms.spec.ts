import { test, expect, barAssets, trace, HOME } from './support';
import type { Browser, Page } from '@playwright/test';
import { deflateSync } from 'node:zlib';

// The CookieBar tab under Settings (README "CMS settings"). These specs log in (the saved admin
// session) and change the one SiteConfig, so each restores what it changed in a finally block;
// the next dev/build re-seeds it anyway (fixtures/CkBPage.php).

const SEEDED_TITLE = 'Cookies on this test site';

async function openCookieBarTab(page: Page): Promise<void> {
    await page.goto('/admin/settings');
    // Root.CookieBar, which the CMS titles "Cookie bar".
    await page.locator('a[href$="#Root_CookieBar"]').click();
    await expect(page.locator('#Form_EditForm_CookieBarTitle')).toBeVisible();
}

/** Save the settings form and wait for its AJAX POST to come back 200. */
async function save(page: Page): Promise<void> {
    const posted = page.waitForResponse((r) => r.request().method() === 'POST' && /\/admin\/settings\/EditForm/.test(r.url()));
    // The SiteConfig save action is "save_siteconfig" on SS5 and "save" on SS6.
    await page.locator('#Form_EditForm_action_save_siteconfig, #Form_EditForm_action_save').click();
    const res = await posted;
    expect(['xhr', 'fetch']).toContain(res.request().resourceType());
    expect(res.status()).toBe(200);
}

/** Load HOME as a fresh visitor (no CMS session, no consent) in its own context. */
async function visit(browser: Browser, baseURL: string) {
    // An explicit empty storageState: inside a test, browser.newContext() takes its defaults from
    // the project's `use`, which includes the saved admin session.
    const context = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
    const page = await context.newPage();
    const errors: string[] = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
    await page.goto(HOME);
    await expect(page.locator('#ckb-page h1')).toBeVisible();
    // textContent() would wait for a missing title until the test times out; count first.
    const titleEl = page.locator('body > #cookiebar .notification-title');
    const result = {
        bar: await page.locator('body > #cookiebar').count(),
        title: (await titleEl.count()) ? await titleEl.textContent() : null,
        template: await page.locator('#cookiebar-template').count(),
        assets: await barAssets(page),
        trace: await trace(page),
        errors,
    };
    await context.close();
    return result;
}

test('Settings > CookieBar shows the module fields with their values', async ({ page }) => {
    await openCookieBarTab(page);
    await expect(page.locator('#Form_EditForm_CookieBarEnable')).toBeChecked();
    await expect(page.locator('#Form_EditForm_CookieBarScriptsInDevTest')).toBeChecked();
    await expect(page.locator('#Form_EditForm_CookieBarTitle')).toHaveValue(SEEDED_TITLE);
    await expect(page.locator('#Form_EditForm_CookieCloseText')).toHaveValue('Accept all');
    await expect(page.locator('#Form_EditForm_CookieMoreText')).toHaveValue('Our cookie policy');
    // The tree dropdown shows the chosen information page by title.
    await expect(page.locator('#Form_EditForm_CookiePageID_Holder')).toContainText('Cookie policy');
    await expect(page.locator('#Form_EditForm_CookieBarRunOnInit')).toHaveValue(/ckbTrace.*'init'/);
    await expect(page.locator('#Form_EditForm_CookieBarRunOnConsent')).toHaveValue(/ckbTrace.*'consent'/);
    // The optional image is an UploadField (rendered by the admin's React upload field).
    await expect(page.locator('#Form_EditForm_CookieImage_Holder')).toBeVisible();
});

test('a saved title shows on the front end; the dev/test switch and the master switch take it all away', async ({
    page,
    browser,
    baseURL,
}) => {
    // Four saves and four visitor page loads.
    test.setTimeout(90_000);
    const title = `Edited title ${Date.now()}`;
    await openCookieBarTab(page);
    try {
        await page.locator('#Form_EditForm_CookieBarTitle').fill(title);
        await save(page);
        let seen = await visit(browser, baseURL!);
        expect(seen.title?.trim(), 'the edited title').toBe(title);
        expect(seen.errors).toEqual([]);

        // Dev/test switch off (the host is in dev mode): no assets and no scripts at all. The JS
        // template is still in the markup ($CookieBar only checks the master switch), but with
        // CookieBar.js gone nothing renders it.
        await page.locator('#Form_EditForm_CookieBarScriptsInDevTest').uncheck();
        await save(page);
        seen = await visit(browser, baseURL!);
        expect(seen).toMatchObject({ bar: 0, assets: { css: 0, js: 0 }, trace: [], errors: [] });

        // Back on, master switch off: no markup either.
        await page.locator('#Form_EditForm_CookieBarScriptsInDevTest').check();
        await page.locator('#Form_EditForm_CookieBarEnable').uncheck();
        await save(page);
        seen = await visit(browser, baseURL!);
        expect(seen).toMatchObject({ bar: 0, template: 0, assets: { css: 0, js: 0 }, errors: [] });
        // The on-init script does not depend on the master switch (README: "whether or not the
        // visitor has consented"; it is output through $MetaTags whenever scripts are allowed).
        expect(seen.trace).toEqual(['init']);
    } finally {
        await openCookieBarTab(page);
        await page.locator('#Form_EditForm_CookieBarTitle').fill(SEEDED_TITLE);
        await page.locator('#Form_EditForm_CookieBarScriptsInDevTest').check();
        await page.locator('#Form_EditForm_CookieBarEnable').check();
        await save(page);
    }
    const restored = await visit(browser, baseURL!);
    expect(restored.title?.trim()).toBe(SEEDED_TITLE);
});

test.fixme('an image uploaded in Settings shows in the bar for visitors (#5)', async ({ page, browser, baseURL }) => {
    // https://github.com/restruct/silverstripe-cookiebar/issues/5 - the image stays in draft (no
    // $owns, and SiteConfig is not versioned), so a logged-out visitor's bar has no <img>.
    // Measured red at toHaveCount(1) on SS5 and SS6 (2026-10-02). On SS6 the admin itself also
    // logged "Cannot read properties of null (reading 'prepValueForChangeTracker')" during that run
    // (upload/remove in the settings form); check that again when this spec is switched on.
    test.setTimeout(60_000);
    await openCookieBarTab(page);
    try {
        // The field shows the file name as soon as the upload starts, before the server has
        // returned the new File ID; saving then posts CookieImage = 0 (measured 2026-10-08: the
        // settings were written with CookieImageID 0). So wait for the field's own upload POST.
        const uploaded = page.waitForResponse((r) => r.request().method() === 'POST' && /\/field\/CookieImage\/upload/.test(r.url()));
        // The React UploadField's dropzone input.
        await page.locator('input.dz-input-CookieImage').setInputFiles({ name: 'ckb-test.png', mimeType: 'image/png', buffer: tinyPng() });
        expect((await uploaded).status()).toBe(200);
        // Once uploaded the field shows the server's Title, "ckb test"; "ckb-test" (the file name)
        // only matched while the upload was still in flight.
        await expect(page.locator('#Form_EditForm_CookieImage_Holder')).toContainText(/ckb.test/);
        await save(page);

        const context = await browser.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
        const visitor = await context.newPage();
        const images: number[] = [];
        visitor.on('response', (r) => r.url().includes('/assets/') && images.push(r.status()));
        await visitor.goto(HOME);
        const img = visitor.locator('body > #cookiebar img');
        await expect(img).toHaveCount(1);
        // SetHeight(80) in the template.
        await expect(img).toHaveAttribute('height', '80');
        await expect.poll(() => img.evaluate((i) => (i as HTMLImageElement).naturalWidth)).toBeGreaterThan(0);
        expect(images.every((s) => s === 200), `image responses ${images}`).toBe(true);
        await context.close();
    } finally {
        // Detach the image again (the next dev/build also resets CookieImageID).
        await openCookieBarTab(page);
        const remove = page.locator('#Form_EditForm_CookieImage_Holder').getByRole('button', { name: /remove/i });
        if (await remove.count()) {
            await remove.first().click();
            await save(page);
        }
    }
});

/** An 8x8 red PNG, built in memory so the spec needs no binary fixture file. */
function tinyPng(): Buffer {
    const w = 8;
    const h = 8;
    const raw = Buffer.alloc((w * 3 + 1) * h);
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) raw[y * (w * 3 + 1) + 1 + x * 3] = 255;
    }
    const table = Array.from({ length: 256 }, (_, n) => {
        let c = n;
        for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
        return c >>> 0;
    });
    const crc = (b: Buffer) => {
        let r = 0xffffffff;
        for (const x of b) r = table[(r ^ x) & 0xff] ^ (r >>> 8);
        return (r ^ 0xffffffff) >>> 0;
    };
    const chunk = (type: string, data: Buffer) => {
        const len = Buffer.alloc(4);
        len.writeUInt32BE(data.length);
        const body = Buffer.concat([Buffer.from(type), data]);
        const sum = Buffer.alloc(4);
        sum.writeUInt32BE(crc(body));
        return Buffer.concat([len, body, sum]);
    };
    const ihdr = Buffer.alloc(13);
    ihdr.writeUInt32BE(w, 0);
    ihdr.writeUInt32BE(h, 4);
    ihdr[8] = 8; // bit depth
    ihdr[9] = 2; // colour type: RGB
    return Buffer.concat([
        Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]),
        chunk('IHDR', ihdr),
        chunk('IDAT', deflateSync(raw)),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}
