<?php

namespace Restruct\CookieBar\Tests;

use Restruct\CookieBar\Tests\Stub\RecordingCookieJar;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Cookie_Backend;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * The <noscript> copy of the bar in $CookieBar: the JS bar sits inside a <script type="text/x-template">,
 * so without JavaScript nothing rendered it and a no-JS visitor could not consent at all.
 *
 * The <noscript> block must carry the message and a root-relative accept link, sit outside the JS
 * template (so the JS path, which copies the template's content into the page, cannot show it twice),
 * and follow the same gates as the JS bar: not after consent, not in dev/test unless enabled.
 */
class NoScriptCookieBarTest extends SapphireTest
{
    protected $usesDatabase = true;

    private RecordingCookieJar $jar;

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jar = new RecordingCookieJar([]);
        Injector::inst()->registerService($this->jar, Cookie_Backend::class);

        // The kernel is not part of the state SapphireTest restores, so put it back ourselves.
        $this->originalEnvironment = Injector::inst()->get(Kernel::class)->getEnvironment();

        $config = SiteConfig::current_site_config();
        $config->CookieBarEnable = true;
        $config->CookieBarScriptsInDevTest = true;
        $config->CookieBarTitle = 'Cookie title sentinel';
        $config->CookieBarContent = '<p>Cookie content sentinel</p>';
        $config->write();
    }

    protected function tearDown(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment($this->originalEnvironment);
        parent::tearDown();
    }

    private function renderBar(): string
    {
        return (string) ContentController::create()->CookieBar();
    }

    /**
     * The inner HTML of the one <noscript> element in $html; fails when there is none or more than one.
     */
    private function noScriptBlock(string $html): string
    {
        $this->assertSame(
            1,
            preg_match_all('#<noscript>(.*?)</noscript>#s', $html, $matches),
            'The bar must contain exactly one <noscript> block.'
        );

        return $matches[1][0];
    }

    public function testNoScriptBlockHasMessageAndRootRelativeAcceptLink()
    {
        $block = $this->noScriptBlock($this->renderBar());

        $this->assertStringContainsString('Cookie title sentinel', $block, 'The message title must render.');
        $this->assertStringContainsString('Cookie content sentinel', $block, 'The message content must render.');
        $this->assertStringContainsString('Accept', $block, 'The default close text must render.');

        $this->assertSame(
            1,
            preg_match('#<a [^>]*class="acceptlink[^"]*"[^>]*href="([^"]*)"#', $block, $link),
            'The <noscript> block must contain the accept link.'
        );
        $this->assertStringStartsWith('/', $link[1], 'The no-JS accept link must be root-relative.');
        $this->assertSame(rtrim(Director::baseURL(), '/') . '/cookiebar/accept', $link[1]);
    }

    public function testNoScriptAcceptLinkIncludesTheBaseUrlBelowTheRoot()
    {
        # A site installed in a subdirectory: the link must include it, from the root.
        Director::config()->set('alternate_base_url', '/subsite/');

        $block = $this->noScriptBlock($this->renderBar());

        $this->assertStringContainsString('href="/subsite/cookiebar/accept"', $block);
    }

    public function testNoScriptBlockSitsOutsideTheJsTemplate()
    {
        $html = $this->renderBar();

        # CookieBar.js inserts the template's innerHTML; a <noscript> inside it would be copied along.
        $this->assertSame(
            1,
            preg_match('#<script type="text/x-template" id="cookiebar-template".*?</script>#s', $html, $template),
            'The JS template must still render.'
        );
        $this->assertStringNotContainsString('<noscript', $template[0], 'The <noscript> bar must not be inside the JS template.');
        $this->noScriptBlock($html);
    }

    public function testNoScriptBlockIsOmittedOnceConsentIsGiven()
    {
        $this->jar->set('cookie_consent', '123');

        $html = $this->renderBar();

        $this->assertStringContainsString('id="cookiebar-template"', $html);
        $this->assertStringNotContainsString('<noscript', $html, 'After consent the no-JS bar must not show.');
    }

    public function testNoScriptBlockIsOmittedInDevUnlessScriptsInDevTestIsSet()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarScriptsInDevTest = false;
        $config->write();

        Injector::inst()->get(Kernel::class)->setEnvironment('dev');
        $this->assertStringNotContainsString('<noscript', $this->renderBar(), 'In dev the no-JS bar follows the JS bar: off.');

        Injector::inst()->get(Kernel::class)->setEnvironment('live');
        $this->noScriptBlock($this->renderBar());
    }
}
