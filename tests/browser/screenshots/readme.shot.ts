import { test, expect, type Browser, type Page } from '@playwright/test';
import * as path from 'path';

// Generates the README screenshots in docs/images/. Not a test suite: run it with
// screenshots/playwright.screenshots.config.ts (see README.md next to this file).
//
// The pages come from the browser fixtures (tests/browser/fixtures/CkBPage.php seeds /cookie-home
// and /cookie-policy). The bar's texts are entered here, through the CMS, the way an editor would,
// replacing the fixture's test texts; the next dev/build (every runner run does one) seeds those
// back. The shots therefore run in order: the CMS one saves what the front-end ones show.

const IMAGES = path.join(__dirname, '..', '..', '..', 'docs', 'images');
const HOME = '/cookie-home';

// Neutral demo texts for the bar.
const TITLE = 'This website uses cookies';
const CONTENT =
    'We use cookies to remember your preferences and to see which pages are read most, so we can ' +
    'improve them. Some of these cookies are set by third-party services.';
const ACCEPT = 'Accept cookies';
const MORE = 'Read about our cookies';

// A Bootstrap theme, as the module's default stylesheet expects (README: the template uses
// Bootstrap classes for its layout). The fixture page has no theme at all, so the stylesheet is
// added to the page for the shot. Presentation only: the module's markup and CSS are untouched.
const BOOTSTRAP_CSS = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';

test.describe.configure({ mode: 'serial' });

/** Save the settings form and wait for its AJAX POST to come back 200 (as in specs/cms.spec.ts). */
async function save(page: Page): Promise<void> {
    const posted = page.waitForResponse((r) => r.request().method() === 'POST' && /\/admin\/settings\/EditForm/.test(r.url()));
    // The SiteConfig save action is "save_siteconfig" on SS5 and "save" on SS6.
    await page.locator('#Form_EditForm_action_save_siteconfig, #Form_EditForm_action_save').click();
    expect((await posted).status()).toBe(200);
}

/** Settings > Cookie bar (Root.CookieBar), as in specs/cms.spec.ts. */
async function openCookieBarTab(page: Page): Promise<void> {
    await page.goto('/admin/settings');
    await page.locator('a[href$="#Root_CookieBar"]').click();
    await expect(page.locator('#Form_EditForm_CookieBarTitle')).toBeVisible();
}

/** A page as a first-time visitor sees it: no CMS session, no consent cookie. */
async function visitor(browser: Browser, baseURL: string, cookies: { name: string; value: string }[] = []) {
    const context = await browser.newContext({
        baseURL,
        storageState: { cookies: [], origins: [] },
        viewport: { width: 1280, height: 800 },
        deviceScaleFactor: 1,
    });
    if (cookies.length) {
        await context.addCookies(cookies.map((c) => ({ ...c, url: baseURL })));
    }
    return { context, page: await context.newPage() };
}

/**
 * Give the bare fixture page a little body so the bar sits on top of something: the fixture's own
 * navigation links (test plumbing) are hidden and the content is padded. Presentation only.
 */
async function tidyFixturePage(page: Page): Promise<void> {
    await page.addStyleTag({
        content: '#ckb-page nav { display: none !important; } #ckb-page { max-width: 960px; margin: 2rem auto; padding: 0 1rem; }',
    });
}

test('CMS: Settings > Cookie bar', async ({ page }) => {
    // Taller than the default viewport: the tab is one long form, and the CMS scrolls inside its
    // own panel, so a full-page screenshot would not reach further down.
    await page.setViewportSize({ width: 1280, height: 1560 });
    await openCookieBarTab(page);

    await page.locator('#Form_EditForm_CookieBarTitle').fill(TITLE);
    await page.locator('#Form_EditForm_CookieCloseText').fill(ACCEPT);
    await page.locator('#Form_EditForm_CookieMoreText').fill(MORE);
    // The fixture's trace scripts are test plumbing; emptied, the fields show the module's own
    // placeholders (Google Consent Mode defaults and update), which is what the shot should show.
    await page.locator('#Form_EditForm_CookieBarRunOnInit').fill('');
    await page.locator('#Form_EditForm_CookieBarRunOnConsent').fill('');
    // The content field is TinyMCE: set it through the editor, which writes it back to the form.
    await expect.poll(() => page.evaluate(() => !!(window as any).tinymce?.get('Form_EditForm_CookieBarContent'))).toBe(true);
    await page.evaluate((html) => {
        const editor = (window as any).tinymce.get('Form_EditForm_CookieBarContent');
        editor.setContent(html);
        editor.save();
        // The CMS marks the form changed on the textarea's change event.
        editor.getElement().dispatchEvent(new Event('change', { bubbles: true }));
    }, `<p>${CONTENT}</p>`);
    await save(page);

    // Reload for a clean state after the save: no "Saved." toast, no focus ring, no hover, and the
    // form showing what was stored rather than what was typed.
    await openCookieBarTab(page);
    await expect(page.locator('#Form_EditForm_CookieBarTitle')).toHaveValue(TITLE);
    await page.mouse.move(5, 5);
    await page.waitForTimeout(500);
    await page.screenshot({ path: path.join(IMAGES, 'cms-settings.png') });
});

test('Front end: the bar on a Bootstrap site', async ({ browser, baseURL }) => {
    const { context, page } = await visitor(browser, baseURL!);
    await page.goto(HOME);
    await page.addStyleTag({ url: BOOTSTRAP_CSS });
    await tidyFixturePage(page);
    const bar = page.locator('body > #cookiebar');
    await expect(bar.locator('.notification-title')).toHaveText(TITLE);
    await page.waitForTimeout(300);

    // The bar and the top of the page under it.
    const box = (await bar.boundingBox())!;
    await page.screenshot({
        path: path.join(IMAGES, 'frontend-bar.png'),
        clip: { x: 0, y: 0, width: 1280, height: Math.ceil(box.y + box.height + 110) },
    });
    await context.close();
});
