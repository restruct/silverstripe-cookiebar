<?php

namespace Restruct\CookieBar\Tests;

use Restruct\CookieBar\Controls\CookieBarController;
use Restruct\CookieBar\Tests\Stub\RecordingCookieJar;
use SilverStripe\Control\Cookie_Backend;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;

/**
 * The consent controller: its config options, its public static API and the no-JS accept action.
 *
 * Cookies are asserted through RecordingCookieJar, registered as the Cookie_Backend service per test
 * (SapphireTest nests the Injector per test, so the registration does not leak).
 */
class CookieBarControllerTest extends FunctionalTest
{
    protected $usesDatabase = true;

    private RecordingCookieJar $jar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jar = new RecordingCookieJar([]);
        Injector::inst()->registerService($this->jar, Cookie_Backend::class);
    }

    /**
     * Call accept() directly on a CookieBarController whose request carries the given headers, and
     * return what it returned (FunctionalTest would follow or wrap it). Director::is_ajax() reads the
     * HTTPRequest registered in the Injector (Director::currentRequest()), not the controller's own
     * request, so the request is registered there too; in a real request both are the same object.
     */
    private function callAccept(array $headers = [])
    {
        $request = new HTTPRequest('GET', 'cookiebar/accept');
        foreach ($headers as $name => $value) {
            $request->addHeader($name, $value);
        }
        $request->setSession(new Session([]));
        Injector::inst()->registerService($request, HTTPRequest::class);

        $controller = CookieBarController::create();
        $controller->setRequest($request);
        $controller->pushCurrent();

        try {
            return $controller->accept();
        } finally {
            $controller->popCurrent();
        }
    }

    public function testConfigDefaults()
    {
        $config = CookieBarController::config();
        $this->assertFalse($config->get('sans_bs_css'));
        $this->assertSame('cookie_consent', $config->get('cookie_name'));
        $this->assertSame(365, $config->get('cookie_age'));
        $this->assertTrue($config->get('cookie_refresh'));
        $this->assertContains('accept', $config->get('allowed_actions'));
    }

    /**
     * Regression: setCookieName()/setCookieAge() called config()->merge() with a scalar, and merge()
     * only accepts arrays, so both setters threw a TypeError on every call, on every major.
     */
    public function testCookieNameAndAgeSettersDoNotTypeErrorOnScalarMerge()
    {
        $this->assertSame('cookie_consent', CookieBarController::getCookieName());
        $this->assertSame(365, CookieBarController::getCookieAge());

        CookieBarController::setCookieName('my_consent');
        CookieBarController::setCookieAge(30);

        $this->assertSame('my_consent', CookieBarController::getCookieName());
        $this->assertSame(30, CookieBarController::getCookieAge());
        $this->assertSame('my_consent', CookieBarController::config()->get('cookie_name'));
    }

    public function testFindLinkBuildsTheRoutedUrl()
    {
        $this->assertSame('cookiebar/accept', CookieBarController::find_link('accept'));
    }

    public function testIsCookieAcceptedIsFalseWithoutACookie()
    {
        $this->assertFalse(CookieBarController::isCookieAccepted());
        $this->assertSame([], $this->jar->calls, 'Nothing may be written when there is no consent.');
    }

    public function testIsCookieAcceptedReadsTheConfiguredCookie()
    {
        CookieBarController::config()->set('cookie_name', 'my_consent');
        $this->jar->set('cookie_consent', '123');
        $this->assertFalse(
            CookieBarController::isCookieAccepted(),
            'Only the configured cookie name may count as consent.'
        );

        $this->jar->set('my_consent', '123');
        $this->assertTrue(CookieBarController::isCookieAccepted());
    }

    public function testIsCookieAcceptedHonoursTheLegacyCookie()
    {
        $this->jar->set('Restruct_CookiesAccepted', '1');
        $this->assertTrue(CookieBarController::isCookieAccepted());
    }

    public function testCookieRefreshRewritesTheConsentCookie()
    {
        $this->jar->set('cookie_consent', '123');
        $this->jar->calls = [];

        CookieBarController::config()->set('cookie_age', 42);
        $this->assertTrue(CookieBarController::isCookieAccepted());

        $this->assertCount(1, $this->jar->calls, 'cookie_refresh (default true) must re-set the cookie.');
        $this->assertSame('cookie_consent', $this->jar->calls[0]['name']);
        $this->assertSame('123', $this->jar->calls[0]['value']);
        $this->assertSame(42, $this->jar->calls[0]['expiry'], 'The refresh must use cookie_age.');
    }

    public function testCookieRefreshCanBeSwitchedOff()
    {
        $this->jar->set('cookie_consent', '123');
        $this->jar->calls = [];

        CookieBarController::config()->set('cookie_refresh', false);
        $this->assertTrue(CookieBarController::isCookieAccepted());
        $this->assertSame([], $this->jar->calls, 'With cookie_refresh off nothing may be re-written.');
    }

    /**
     * Regression: accept() passed null as Cookie::set()'s $secure, a TypeError on Silverstripe 6
     * (the parameter is typed bool there). This test errors on the unfixed code on SS6.
     */
    public function testAcceptSetsTheConsentCookieWithoutTypeErrorOnSilverstripe6()
    {
        CookieBarController::config()->set('cookie_age', 7);

        $result = $this->callAccept(['X-Requested-With' => 'XMLHttpRequest']);

        $this->assertSame('success', $result, 'An AJAX accept answers with a plain success string.');
        $this->assertCount(1, $this->jar->calls);
        $this->assertSame('cookie_consent', $this->jar->calls[0]['name']);
        $this->assertMatchesRegularExpression('/^\d+$/', (string) $this->jar->calls[0]['value'], 'Value is a timestamp.');
        $this->assertSame(7, $this->jar->calls[0]['expiry']);
        $this->assertFalse($this->jar->calls[0]['secure']);
        $this->assertFalse($this->jar->calls[0]['httpOnly'], 'The JS side must be able to read the cookie.');
        $this->assertTrue(CookieBarController::isCookieAccepted());
    }

    public function testAcceptDoesNotRewriteAnExistingConsent()
    {
        $this->jar->set('cookie_consent', '123');
        CookieBarController::config()->set('cookie_refresh', false);
        $this->jar->calls = [];

        $this->callAccept(['X-Requested-With' => 'XMLHttpRequest']);

        $this->assertSame([], $this->jar->calls);
    }

    public function testAcceptRouteIsWiredAndAnswersAjax()
    {
        $response = $this->get('cookiebar/accept', null, ['X-Requested-With' => 'XMLHttpRequest']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $response->getBody());
    }

    public function testAcceptRouteRedirectsBackWithoutAjax()
    {
        // FunctionalTest follows redirects by default, which would hide the 3xx.
        $this->autoFollowRedirection = false;

        $response = $this->get('cookiebar/accept', null, ['Referer' => '/some-page']);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('some-page', (string) $response->getHeader('Location'));
    }
}
