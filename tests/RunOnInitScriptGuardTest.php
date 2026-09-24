<?php

namespace Restruct\CookieBar\Tests;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * $SiteConfig.CookieBarRunOnInitScript, the helper for projects that place the on-init script by
 * hand instead of relying on $MetaTags, must apply the same guards as the $MetaTags path.
 *
 * Regression: the dev/test switch and the Security-controller skip sat only in the $MetaTags hook,
 * so the hand-placed script was output on development copies and on the login pages.
 */
class RunOnInitScriptGuardTest extends SapphireTest
{
    protected $usesDatabase = true;

    private const SCRIPT = "window.cookiebarHandPlacedSentinel = 1;";

    private string $originalEnvironment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnvironment = Injector::inst()->get(Kernel::class)->getEnvironment();

        $config = SiteConfig::current_site_config();
        $config->CookieBarRunOnInit = self::SCRIPT;
        $config->CookieBarScriptsInDevTest = false;
        $config->write();
    }

    protected function tearDown(): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment($this->originalEnvironment);
        parent::tearDown();
    }

    private function script(): ?string
    {
        $value = SiteConfig::current_site_config()->CookieBarRunOnInitScript();

        return $value ? $value->forTemplate() : null;
    }

    private function setEnvironment(string $environment): void
    {
        Injector::inst()->get(Kernel::class)->setEnvironment($environment);
    }

    public function testNotOutputInDevOrTestWithoutScriptsInDevTest()
    {
        $this->setEnvironment('dev');
        $this->assertNull($this->script(), 'dev');

        $this->setEnvironment('test');
        $this->assertNull($this->script(), 'test');
    }

    public function testOutputInDevWithScriptsInDevTest()
    {
        $config = SiteConfig::current_site_config();
        $config->CookieBarScriptsInDevTest = true;
        $config->write();

        $this->setEnvironment('dev');
        $this->assertSame('<script>' . self::SCRIPT . '</script>', $this->script());
    }

    public function testOutputInLiveWithoutScriptsInDevTest()
    {
        $this->setEnvironment('live');
        $this->assertSame('<script>' . self::SCRIPT . '</script>', $this->script());
    }

    public function testNotOutputWhenSecurityIsTheCurrentController()
    {
        $this->setEnvironment('live');

        $request = new HTTPRequest('GET', 'Security/login');
        $request->setSession(new Session([]));
        $security = Security::create();
        $security->setRequest($request);
        $security->pushCurrent();

        try {
            $script = $this->script();
        } finally {
            $security->popCurrent();
        }

        $this->assertNull($script, 'Login, logout and password pages must not get the on-init script.');
    }
}
