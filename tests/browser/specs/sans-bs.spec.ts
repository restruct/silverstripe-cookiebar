import { test, expect, bar, HOME } from './support';

// sans_bs_css (README "Configuration"): for sites without Bootstrap the module also loads a layout
// stylesheet that gives the bar its columns itself. The scratch host has no Bootstrap, so the
// bar's row is a flex container only when that stylesheet did its job. The option is switched on
// for this spec's requests only, by a cookie the fixture middleware reads
// (tests/browser/fixtures/CkBVariantMiddleware.php); the other specs keep the default.
test.use({ storageState: { cookies: [], origins: [] } });

const CSS = /silverstripe-cookiebar\/client\/dist\/css\/(cookiebar|cookiebar-layout-sans-bs)\.css/;

test('sans_bs_css loads the layout stylesheet on top of cookiebar.css, and lays the bar out in columns', async ({ page, context, baseURL }) => {
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
    // Both since #9 (it used to be the layout stylesheet only). The two downloads finish in either
    // order, so compare them sorted; the cascade order is the <link> order, checked below.
    expect([...seen].sort()).toEqual(['200 cookiebar', '200 cookiebar-layout-sans-bs']);
    const links = await page.evaluate(() =>
        [...document.querySelectorAll('link[rel="stylesheet"]')]
            .map((l) => /css\/(cookiebar|cookiebar-layout-sans-bs)\.css/.exec((l as HTMLLinkElement).href)?.[1])
            .filter(Boolean),
    );
    expect(links, 'cookiebar.css first, the layout stylesheet on top').toEqual(['cookiebar', 'cookiebar-layout-sans-bs']);
    await expect(bar(page).locator('.cookiebar-row').first()).toHaveCSS('display', 'flex');
});

test('with sans_bs_css the bar keeps its own styling: background, borders, bold title, image offset (#9)', async ({ page, context, baseURL }) => {
    // https://github.com/restruct/silverstripe-cookiebar/issues/9 - the layout stylesheet only stands
    // in for Bootstrap (columns, buttons); the bar's own look lives in cookiebar.css
    // (client/src/sass/cookiebar.scss). Both must apply when sans_bs_css is on.
    await context.addCookies([{ name: 'ckb-browser-variant', value: 'sans-bs', url: baseURL! }]);
    await page.goto(HOME);
    await expect(bar(page)).toBeVisible();
    // Values from cookiebar.scss.
    await expect(bar(page)).toHaveCSS('background-color', 'rgb(238, 238, 238)');
    await expect(bar(page)).toHaveCSS('border-top', '2px solid rgb(153, 153, 153)');
    await expect(bar(page)).toHaveCSS('border-bottom', '2px solid rgb(153, 153, 153)');
    await expect(bar(page).locator('.notification-title')).toHaveCSS('font-weight', '700');
    // The room the optional image is placed in, beside the text.
    await expect(bar(page).locator('.notification-inner')).toHaveCSS('padding-left', '100px');
});
