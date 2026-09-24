<?php

namespace Restruct\CookieBar\Tests;

use Restruct\CookieBar\Extensions\SiteTreeExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * The CookieBarRunOnInit script injected into $MetaTags.
 *
 * Uses SiteTree itself: the module applies the extension to SiteTree, and SiteTree::MetaTags() is
 * what fires the hook, so no test-only page class is needed.
 */
class SiteTreeExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    private const SCRIPT = "window.cookiebarInitSentinel = 1;";

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnvironment = Injector::inst()->get(Kernel::class)->getEnvironment();

        $config = SiteConfig::current_site_config();
        $config->CookieBarRunOnInit = self::SCRIPT;
        $config->CookieBarScriptsInDevTest = true;
        $config->write();
    }

    protected function tearDown(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment($this->originalEnvironment);
        parent::tearDown();
    }

    private function metaTags(): string
    {
        return (string) SiteTree::create(['Title' => 'Meta test'])->MetaTags(false);
    }

    public function testExtensionIsApplied()
    {
        $this->assertTrue(SiteTree::has_extension(SiteTreeExtension::class));
    }

    /**
     * Regression: Silverstripe 6 fires extend('updateMetaTags') where 5 fired extend('MetaTags'), so
     * the script silently disappeared on SS6. Fails on the unfixed code on SS6. The count also guards
     * the other direction: implementing both names must not inject the script twice.
     */
    public function testRunOnInitScriptIsInjectedExactlyOnceOnBothMajors()
    {
        $tags = $this->metaTags();

        $this->assertSame(1, substr_count($tags, '<script>' . self::SCRIPT . '</script>'), $tags);
    }

    public function testNothingIsInjectedInDevUnlessScriptsInDevTestIsSet()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarScriptsInDevTest = false;
        $config->write();

        Injector::inst()->get(Kernel::class)->setEnvironment('dev');
        $this->assertStringNotContainsString(self::SCRIPT, $this->metaTags());

        Injector::inst()->get(Kernel::class)->setEnvironment('live');
        $this->assertStringContainsString(self::SCRIPT, $this->metaTags());
    }

    public function testNothingIsInjectedWhenSecurityIsTheCurrentController()
    {
        $request = new HTTPRequest('GET', 'Security/login');
        $request->setSession(new Session([]));
        $security = Security::create();
        $security->setRequest($request);
        $security->pushCurrent();

        try {
            $tags = $this->metaTags();
        } finally {
            $security->popCurrent();
        }

        $this->assertStringNotContainsString(self::SCRIPT, $tags);
    }

    public function testNothingIsInjectedWithoutAScript()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarRunOnInit = null;
        $config->write();

        $this->assertStringNotContainsString('<script>', $this->metaTags());
    }
}
