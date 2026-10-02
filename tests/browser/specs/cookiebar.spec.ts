import {
    test,
    expect,
    bar,
    barAssets,
    consentCookie,
    trace,
    watchDocumentNavigations,
    CHILD,
    COOKIE,
    HOME,
    POLICY,
} from './support';

// The bar as a visitor sees it (README "How it works"). Every spec starts as a fresh visitor: no
// CMS session, no consent cookie.
test.use({ storageState: { cookies: [], origins: [] } });

test('a first visit shows the bar with the CMS texts and loads its assets', async ({ page }) => {
    const css: number[] = [];
    page.on('response', (r) => {
        if (r.url().includes('silverstripe-cookiebar/client/dist/css/cookiebar.css')) css.push(r.status());
    });
    await page.goto(HOME);

    await expect(bar(page)).toBeVisible();
    await expect(bar(page).locator('.notification-title')).toHaveText('Cookies on this test site');
    // CookieBarContent is HTML and arrives as HTML.
    await expect(bar(page).locator('.notification-content .ckb-content strong')).toHaveText('test');
    await expect(bar(page).locator('#acceptcookies')).toHaveText('Accept all');
    const info = bar(page).locator('a.infolink');
    await expect(info).toHaveText('Our cookie policy');
    expect(new URL(await info.evaluate((a) => (a as HTMLAnchorElement).href)).pathname.replace(/\/$/, '')).toBe(POLICY);

    expect(css, 'cookiebar.css requested once, 200').toEqual([200]);
    expect(await barAssets(page)).toEqual({ css: 1, js: 1 });
    expect(await consentCookie(page), 'no consent cookie before Accept').toBeUndefined();
    // The on-init script ran; the run-if-consent one has not.
    expect(await trace(page)).toEqual(['init']);
});

test('Accept sets the consent cookie in the page, hides the bar and runs the consent script', async ({ page }) => {
    await page.goto(HOME);
    await expect(bar(page)).toBeVisible();
    const navigations = watchDocumentNavigations(page);
    const before = Date.now();

    await bar(page).locator('#acceptcookies').click();

    // CookieBar.js fades the bar out (opacity steps, then display:none).
    await expect(bar(page)).toBeHidden();
    const cookie = await consentCookie(page);
    expect(cookie, 'consent cookie set').toBeDefined();
    expect(cookie!.path).toBe('/');
    expect(Number(cookie!.value), 'cookie value is the consent time').toBeGreaterThanOrEqual(Math.floor(before / 1000));
    // cookie_age: 365 days (allow a minute either way).
    const days = (cookie!.expires - before / 1000) / 86400;
    expect(days).toBeGreaterThan(364.99);
    expect(days).toBeLessThan(365.01);
    expect(await trace(page)).toEqual(['init', 'consent']);
    expect(navigations(), 'Accept does not follow the accept link').toEqual([]);
});

test('with consent: no bar and no assets, and the consent script runs on every page', async ({ page }) => {
    await page.goto(HOME);
    await bar(page).locator('#acceptcookies').click();
    await expect(bar(page)).toBeHidden();

    for (const path of [HOME, CHILD]) {
        await page.goto(path);
        await expect(page.locator('#ckb-page h1')).toBeVisible();
        await expect(page.locator('#cookiebar')).toHaveCount(0);
        expect(await barAssets(page), `${path}: no cookiebar CSS/JS once consent exists`).toEqual({ css: 0, js: 0 });
        // Output and called by the server-side path now, since CookieBar.js is not on the page.
        expect(await trace(page), `${path}: init and consent scripts`).toEqual(['init', 'consent']);
    }
});

test('the legacy Restruct_CookiesAccepted cookie still counts as consent', async ({ page, context, baseURL }) => {
    await context.addCookies([{ name: 'Restruct_CookiesAccepted', value: '1', url: baseURL! }]);
    await page.goto(HOME);
    await expect(page.locator('#ckb-page h1')).toBeVisible();
    await expect(page.locator('#cookiebar')).toHaveCount(0);
    expect(await barAssets(page)).toEqual({ css: 0, js: 0 });
    expect(await trace(page)).toEqual(['init', 'consent']);
});

test('the bar comes back on every page until Accept', async ({ page }) => {
    await page.goto(HOME);
    await expect(bar(page)).toBeVisible();
    await page.locator('#ckb-to-child').click();
    await expect(page).toHaveURL(new RegExp(`${CHILD}/?$`));
    await expect(bar(page)).toBeVisible();
    expect(await consentCookie(page)).toBeUndefined();
});

test('nothing is injected on the Security login page', async ({ page }) => {
    await page.goto('/Security/login');
    await expect(page.locator('input[name="Email"]')).toBeVisible();
    await expect(page.locator('#cookiebar, #cookiebar-template')).toHaveCount(0);
    expect(await barAssets(page)).toEqual({ css: 0, js: 0 });
    expect(await trace(page), 'not even the on-init script').toEqual([]);
});

test('an AJAX request to /cookiebar/accept answers "success" and sets the cookie', async ({ page }) => {
    await page.goto(HOME);
    const res = await page.evaluate(async () => {
        const r = await fetch('/cookiebar/accept', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, redirect: 'manual' });
        return { status: r.status, body: await r.text() };
    });
    expect(res).toEqual({ status: 200, body: 'success' });
    expect(await consentCookie(page), 'set server side').toBeDefined();
});

test.fixme('a page without $CookieBar in its template logs no error (#6)', async ({ page }) => {
    // https://github.com/restruct/silverstripe-cookiebar/issues/6 - the bar's JS is added on every
    // page, and on one without the markup it throws "Cannot read properties of null (reading
    // 'innerHTML')". The host's stock home page renders through the CMS fallback template, which
    // has no $CookieBar. The console guard is the assertion.
    await page.goto('/');
    await expect(page.locator('body')).toBeVisible();
});

test.describe('without JavaScript', () => {
    test.use({ javaScriptEnabled: false });

    test('the noscript bar accepts through /cookiebar/accept and returns to the page', async ({ page }) => {
        // A page below the site root: the noscript link must be root-relative to work here.
        await page.goto(CHILD);
        const noscriptBar = page.locator('#cookiebar.cookiebar-noscript');
        await expect(noscriptBar).toBeVisible();
        await expect(noscriptBar.locator('.notification-title')).toHaveText('Cookies on this test site');
        const accept = noscriptBar.locator('a.acceptlink');
        await expect(accept).toHaveAttribute('href', '/cookiebar/accept');

        const responses: string[] = [];
        page.on('response', (r) => {
            if (r.request().isNavigationRequest()) responses.push(`${r.status()} ${new URL(r.url()).pathname}`);
        });
        await accept.click();
        await page.waitForLoadState();

        // Accepted server side, then redirected back to where the visitor was.
        expect(responses[0]).toBe('302 /cookiebar/accept');
        await expect(page).toHaveURL(new RegExp(`${CHILD}/?$`));
        expect((await consentCookie(page))?.name).toBe(COOKIE);
        await expect(page.locator('.cookiebar-noscript')).toHaveCount(0);
    });
});
