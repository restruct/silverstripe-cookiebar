<?php

namespace Restruct\CookieBar\Tests;

use Restruct\CookieBar\Controls\CookieBarController;
use Restruct\CookieBar\Extensions\ContentControllerExtension;
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
 * What the ContentController extension adds to a page: the CSS/JS requirements in onAfterInit(),
 * the $CookieBar markup, and the gates that suppress both (disabled, consented, dev/test, Security).
 *
 * onAfterInit() is driven through $controller->extend('onAfterInit'), the same dispatch path a real
 * request takes, so a fatal inside the hook (as with has_curr() on Silverstripe 6) fails here too.
 */
class ContentControllerExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

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
        $config->CookieBarTitle = 'Cookie title sentinel';
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

    private function cssFiles(): array
    {
        return array_keys(Requirements::backend()->getCSS());
    }

    private function allCustomScript(): string
    {
        return implode("\n", Requirements::backend()->getCustomScripts());
    }

    private function assertNothingRequired(string $message): void
    {
        $this->assertSame([], $this->cssFiles(), $message);
        $this->assertSame('', $this->allCustomScript(), $message);
    }

    /**
     * Regression: onAfterInit() called Controller::has_curr(), removed in Silverstripe 6, so every
     * front-end request fataled there. On the unfixed code this test errors on SS6.
     */
    public function testOnAfterInitDoesNotFatalOnHasCurrRemoval()
    {
        $this->runInit();

        $css = $this->cssFiles();
        $this->assertCount(1, $css);
        $this->assertStringEndsWith('client/dist/css/cookiebar.css', $css[0]);

        $script = $this->allCustomScript();
        $this->assertStringContainsString('cookie_consent', $script, 'CookieBar.js must be templated with the cookie name.');
        $this->assertStringNotContainsString('$ConsentCookieKey', $script, 'The template variables must be substituted.');
        $this->assertStringNotContainsString('$ConsentExpiration', $script);
    }

    public function testJsTemplateUsesConfiguredCookieNameAndAge()
    {
        CookieBarController::config()->set('cookie_name', 'my_consent');
        CookieBarController::config()->set('cookie_age', 12);

        $this->runInit();

        $script = $this->allCustomScript();
        $this->assertStringContainsString('"my_consent"', $script);
        $this->assertMatchesRegularExpression('/=\s*12\b/', $script);
    }

    public function testSansBootstrapCssOptionSwapsTheStylesheet()
    {
        CookieBarController::config()->set('sans_bs_css', true);

        $this->runInit();

        $css = $this->cssFiles();
        $this->assertCount(1, $css);
        $this->assertStringEndsWith('client/dist/css/cookiebar-layout-sans-bs.css', $css[0]);
    }

    public function testRunOnConsentScriptIsWrappedAndStripped()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarRunOnConsent = "console.log('consented');<script>evil()</script>";
        $config->write();

        $this->runInit();

        $scripts = Requirements::backend()->getCustomScripts();
        $this->assertArrayHasKey('cookiebar_run_if_consent', $scripts);
        $script = $scripts['cookiebar_run_if_consent'];
        $this->assertStringContainsString('function cookieBarRunIfConsent()', $script);
        $this->assertStringContainsString("console.log('consented');", $script);
        $this->assertStringNotContainsString('<script>', $script, 'HTML tags must be stripped.');
    }

    public function testNothingIsRequiredWhenDisabled()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarEnable = false;
        $config->write();

        $this->runInit();

        $this->assertNothingRequired('A disabled cookie bar must add no requirements.');
    }

    public function testNothingIsRequiredOnceConsentIsGiven()
    {
        $this->jar->set('cookie_consent', '123');

        $this->runInit();

        $this->assertNothingRequired('After consent the bar assets must not load.');
    }

    public function testNothingIsRequiredInDevUnlessScriptsInDevTestIsSet()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarScriptsInDevTest = false;
        $config->write();

        Injector::inst()->get(Kernel::class)->setEnvironment('dev');
        $this->runInit();
        $this->assertNothingRequired('In dev the bar must stay off unless CookieBarScriptsInDevTest is set.');

        Injector::inst()->get(Kernel::class)->setEnvironment('live');
        $this->runInit();
        $this->assertNotEmpty($this->cssFiles(), 'In live the bar loads without CookieBarScriptsInDevTest.');
    }

    public function testNothingIsRequiredWhenSecurityIsTheCurrentController()
    {
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

        $this->assertNothingRequired('Login, logout and password pages must not get the cookie bar.');
    }

    public function testCookieBarRendersTheTemplateWhenEnabled()
    {
        $html = (string) ContentController::create()->CookieBar();

        $this->assertStringContainsString('id="cookiebar-template"', $html);
        $this->assertStringContainsString('Cookie title sentinel', $html, 'SiteConfig texts must render.');
        $this->assertStringContainsString('href="cookiebar/accept"', $html, 'The no-JS accept link must point at the controller.');
        $this->assertStringContainsString('Accept', $html, 'The default close text must render.');
    }

    public function testCookieBarIsEmptyWhenDisabled()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarEnable = false;
        $config->write();

        $this->assertNull(ContentController::create()->CookieBar());
    }

    public function testCookieBarEnabledReflectsSiteConfig()
    {
        $this->assertTrue(ContentControllerExtension::cookieBarEnabled());

        $config = SiteConfig::current_site_config();
        $config->CookieBarEnable = false;
        $config->write();

        $this->assertFalse(ContentControllerExtension::cookieBarEnabled());
    }

    public function testTemplateHelpers()
    {
        $controller = ContentController::create();

        $this->assertSame('cookiebar/accept', $controller->getAcceptCookiesLink());
        $this->assertFalse($controller->CookieConsent());

        $this->jar->set('cookie_consent', '123');
        $this->assertTrue($controller->CookieConsent());
    }
}
