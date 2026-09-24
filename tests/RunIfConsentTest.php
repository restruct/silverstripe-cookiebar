<?php

namespace Restruct\CookieBar\Tests;

use Restruct\CookieBar\Tests\Stub\RecordingCookieJar;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Cookie_Backend;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;
use SilverStripe\View\Requirements_Backend;

/**
 * The "JS to run if/after consent" script (CookieBarRunOnConsent) on page loads AFTER consent.
 *
 * Regression: onAfterInit() output the script only together with the bar's assets, i.e. only while no
 * consent cookie existed, so it ran on the page where the visitor clicked Accept and on no later page.
 * With consent it must now be output AND invoked on every page, behind the same guards as the rest.
 */
class RunIfConsentTest extends SapphireTest
{
    protected $usesDatabase = true;

    private const BODY = "window.cookiebarConsentSentinel = 1;";

    private RecordingCookieJar $jar;

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();

        // A fresh backend per test: Requirements is process-global state.
        Requirements::set_backend(Requirements_Backend::create());

        $this->jar = new RecordingCookieJar([]);
        Injector::inst()->registerService($this->jar, Cookie_Backend::class);

        // The kernel is not part of the state SapphireTest restores, so put it back ourselves.
        $this->originalEnvironment = Injector::inst()->get(Kernel::class)->getEnvironment();

        $config = SiteConfig::current_site_config();
        $config->CookieBarEnable = true;
        $config->CookieBarScriptsInDevTest = true;
        $config->CookieBarRunOnConsent = self::BODY;
        $config->write();
    }

    protected function tearDown(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment($this->originalEnvironment);
        Requirements::set_backend(Requirements_Backend::create());
        parent::tearDown();
    }

    private function runInit(): void
    {
        ContentController::create()->extend('onAfterInit');
    }

    private function consentScript(): ?string
    {
        return Requirements::backend()->getCustomScripts()['cookiebar_run_if_consent'] ?? null;
    }

    /**
     * Whether the script calls cookieBarRunIfConsent itself, outside its own definition: as a direct
     * call or as a handler reference. The definition line is removed first so it cannot match.
     */
    private function invokes(string $script): bool
    {
        $withoutDefinition = str_replace('function cookieBarRunIfConsent()', '', $script);

        return str_contains($withoutDefinition, 'cookieBarRunIfConsent');
    }

    private function giveConsent(): void
    {
        $this->jar->set('cookie_consent', '123');
    }

    public function testWithConsentTheScriptIsOutputAndInvoked()
    {
        $this->giveConsent();

        $this->runInit();

        $script = $this->consentScript();
        $this->assertNotNull($script, 'With consent, the run-if-consent script must be on every page.');
        $this->assertStringContainsString('function cookieBarRunIfConsent()', $script);
        $this->assertStringContainsString(self::BODY, $script);
        $this->assertTrue($this->invokes($script), 'CookieBar.js is not loaded after consent, so the inline script must call the function itself.');
    }

    public function testWithConsentTheBarAssetsStillDoNotLoad()
    {
        $this->giveConsent();

        $this->runInit();

        $this->assertSame([], array_keys(Requirements::backend()->getCSS()), 'The bar CSS must not load after consent.');
        $this->assertStringNotContainsString(
            'cookie_consent',
            implode("\n", Requirements::backend()->getCustomScripts()),
            'CookieBar.js (templated with the cookie name) must not load after consent.'
        );
    }

    public function testWithoutConsentTheScriptIsDefinedButNotInvoked()
    {
        $this->runInit();

        $script = $this->consentScript();
        $this->assertNotNull($script);
        $this->assertStringContainsString('function cookieBarRunIfConsent()', $script);
        $this->assertFalse($this->invokes($script), 'Before consent only CookieBar.js may call it, on Accept.');
    }

    public function testWithConsentTheScriptIsStripped()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarRunOnConsent = self::BODY . '<script>evil()</script>';
        $config->write();
        $this->giveConsent();

        $this->runInit();

        $this->assertStringNotContainsString('<script>', (string) $this->consentScript(), 'HTML tags must be stripped.');
    }

    public function testWithConsentNothingIsOutputWhenDisabled()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarEnable = false;
        $config->write();
        $this->giveConsent();

        $this->runInit();

        $this->assertNull($this->consentScript(), 'A disabled cookie bar must not output the script.');
    }

    public function testWithConsentNothingIsOutputInDevUnlessScriptsInDevTestIsSet()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarScriptsInDevTest = false;
        $config->write();
        $this->giveConsent();

        Injector::inst()->get(Kernel::class)->setEnvironment('dev');
        $this->runInit();
        $this->assertNull($this->consentScript(), 'In dev the script must stay off unless CookieBarScriptsInDevTest is set.');

        Injector::inst()->get(Kernel::class)->setEnvironment('live');
        $this->runInit();
        $this->assertNotNull($this->consentScript(), 'In live the script is output without CookieBarScriptsInDevTest.');
    }

    public function testWithConsentNothingIsOutputWhenSecurityIsTheCurrentController()
    {
        $this->giveConsent();

        $request = new HTTPRequest('GET', 'Security/login');
        $request->setSession(new Session([]));
        $security = Security::create();
        $security->setRequest($request);
        $security->pushCurrent();

        try {
            $this->runInit();
        } finally {
            $security->popCurrent();
        }

        $this->assertNull($this->consentScript(), 'Login, logout and password pages must not get the script.');
    }
}
