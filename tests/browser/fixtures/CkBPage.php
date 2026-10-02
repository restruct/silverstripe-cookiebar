<?php

namespace Restruct\CkBrowser;

use Page;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * BROWSER-TEST FIXTURE ONLY - a front-end page whose template places $CookieBar before </body>, as
 * the README's installation step says (see CkBPageController for the template). The scratch host is
 * a bare recipe-cms project without a theme, so the stock pages render through the CMS's fallback
 * ContentController template, which has no $CookieBar.
 *
 * Never loaded by a real install: it lives under tests/browser/, which carries a _manifest_exclude
 * marker, and the browser-test runner copies it into a scratch host's app/ before dev/build.
 * Written to load on both Silverstripe 5 and 6.
 *
 * Every dev/build (the runner does one per run) re-creates the fixture pages and resets the
 * SiteConfig's CookieBar settings, so each run starts from the same state.
 */
class CkBPage extends Page
{
    private static $table_name = 'CkBPage';

    /** URL segments of the seeded pages, used by the specs. */
    public const HOME = 'cookie-home';
    public const CHILD = 'cookie-child';
    public const POLICY = 'cookie-policy';

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();

        # Only once, for this class (requireDefaultRecords() runs per class in the hierarchy).
        if (static::class !== self::class) {
            return;
        }
        foreach (self::get() as $old) {
            $old->doUnpublish();
            $old->delete();
        }

        $home = $this->publishPage('Cookie home', self::HOME, 0, '<p>A page with the cookie bar.</p>');
        # Below the site root, where a document-relative accept link would 404.
        $this->publishPage('Cookie child', self::CHILD, $home->ID, '<p>A nested page.</p>');
        $policy = $this->publishPage('Cookie policy', self::POLICY, 0, '<p>About our cookies.</p>');

        # The CMS settings under Settings > CookieBar. The host runs in dev mode, so the bar only
        # appears with CookieBarScriptsInDevTest ticked. The two scripts leave a trace on window that
        # the specs read: the on-init one runs before anything else, the other once per consent.
        $config = SiteConfig::current_site_config();
        $config->update([
            'CookieBarEnable' => true,
            'CookieBarScriptsInDevTest' => true,
            'CookieBarTitle' => 'Cookies on this test site',
            'CookieBarContent' => '<p class="ckb-content">We use <strong>test</strong> cookies.</p>',
            'CookieCloseText' => 'Accept all',
            'CookieMoreText' => 'Our cookie policy',
            'CookiePageID' => $policy->ID,
            # No image: the image spec (cms.spec.ts) uploads one.
            'CookieImageID' => 0,
            'CookieBarRunOnInit' => "window.ckbTrace = (window.ckbTrace || []).concat('init');",
            'CookieBarRunOnConsent' => "window.ckbTrace = (window.ckbTrace || []).concat('consent');",
        ]);
        $config->write();
    }

    private function publishPage(string $title, string $segment, int $parentID, string $content): self
    {
        $page = self::create([
            'Title' => $title,
            'URLSegment' => $segment,
            'ParentID' => $parentID,
            'Content' => $content,
        ]);
        $page->write();
        $page->publishRecursive();
        return $page;
    }
}
