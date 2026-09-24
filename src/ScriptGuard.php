<?php

namespace Restruct\CookieBar;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * The one place that decides whether the cookie bar may output anything for the current request.
 *
 * Shared by every output path (the bar's assets and run-if-consent script in
 * ContentControllerExtension::onAfterInit(), and the on-init script, whether it reaches the page
 * through $MetaTags or through $SiteConfig.CookieBarRunOnInitScript placed by hand), so the paths
 * cannot drift apart again: the hand-placed on-init script used to skip both checks below.
 */
final class ScriptGuard
{
    /**
     * False on the Security controller (login, logout, password reset, etc.), and outside `live`
     * unless the SiteConfig's "Also insert scripts in dev/test environments" is ticked.
     */
    public static function scriptsAllowed(?SiteConfig $siteConfig = null): bool
    {
        # Controller::has_curr() was removed in Silverstripe 6 (deprecated in 5.4), so calling it
        # unconditionally fataled every request there. On SS6 curr() simply returns null on an
        # empty stack; on SS5 curr() raises a warning in that case, so has_curr() is still asked
        # first wherever it exists (eg when a page is rendered from CLI with nothing pushed).
        $controller = (method_exists(Controller::class, 'has_curr') && !Controller::has_curr())
            ? null
            : Controller::curr();
        if ($controller instanceof Security) {
            return false;
        }

        // Skip in dev/test unless explicitly enabled
        $siteConfig ??= SiteConfig::current_site_config();
        if (!Director::isLive() && !$siteConfig->CookieBarScriptsInDevTest) {
            return false;
        }

        return true;
    }
}
