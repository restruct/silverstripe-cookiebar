import { test, expect, bar, HOME } from './support';

// sans_bs_css (README "Configuration"): for sites without Bootstrap the module loads a layout
// stylesheet that gives the bar its columns itself. The scratch host has no Bootstrap, so the
// bar's row is a flex container only when that stylesheet did its job. The option is switched on
// for this spec's requests only, by a cookie the fixture middleware reads
// (tests/browser/fixtures/CkBVariantMiddleware.php); the other specs keep the default.
test.use({ storageState: { cookies: [], origins: [] } });

const CSS = /silverstripe-cookiebar\/client\/dist\/css\/(cookiebar|cookiebar-layout-sans-bs)\.css/;

test('sans_bs_css loads the layout stylesheet instead of cookiebar.css, and lays the bar out in columns', async ({ page, context, baseURL }) => {
    const seen: string[] = [];
    page.on('response', (r) => {
        const m = CSS.exec(r.url());
        if (m) seen.push(`${r.status()} ${m[1]}`);
    });

    // Default first, as the control: cookiebar.css, and no flex row without Bootstrap.
    await page.goto(HOME);
    await expect(bar(page)).toBeVisible();
    expect(seen).toEqual(['200 cookiebar']);
    await expect(bar(page).locator('.cookiebar-row').first()).toHaveCSS('display', 'block');

    seen.length = 0;
    await context.addCookies([{ name: 'ckb-browser-variant', value: 'sans-bs', url: baseURL! }]);
    await page.goto(HOME);
    await expect(bar(page)).toBeVisible();
    expect(seen).toEqual(['200 cookiebar-layout-sans-bs']);
    await expect(bar(page).locator('.cookiebar-row').first()).toHaveCSS('display', 'flex');
});
