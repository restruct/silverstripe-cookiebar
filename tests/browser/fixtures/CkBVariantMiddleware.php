<?php

namespace Restruct\CkBrowser;

use Restruct\CookieBar\Controls\CookieBarController;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Config;

/**
 * BROWSER-TEST FIXTURE ONLY - lets one spec see sans_bs_css switched on without changing what every
 * other spec checks: a request carrying the cookie ckb-browser-variant=sans-bs gets
 * CookieBarController.sans_bs_css = true for that request only, as if a project had set it in
 * YAML. Registered as a Director middleware by fixtures/_config/variant.yml. See CkBPage for why
 * this never loads in a real install.
 */
class CkBVariantMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        if (Cookie::get('ckb-browser-variant') === 'sans-bs') {
            Config::modify()->set(CookieBarController::class, 'sans_bs_css', true);
        }
        return $delegate($request);
    }
}
