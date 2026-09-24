<?php

namespace Restruct\CookieBar\Extensions {

    use Restruct\CookieBar\Controls\CookieBarController;
    use Restruct\CookieBar\ScriptGuard;
    use SilverStripe\Control\Controller;
    use SilverStripe\Control\Director;
    use SilverStripe\Core\Extension;
    use SilverStripe\Security\Security;
    use SilverStripe\SiteConfig\SiteConfig;
    use SilverStripe\View\Requirements;

    /**
     * ContentController extension to include CookieBar assets and markup
     */
    class ContentControllerExtension extends Extension
    {

        public static function cookieBarEnabled(): bool
        {
            return SiteConfig::current_site_config()->CookieBarEnable;
        }

        /**
         * @return void
         */
        public function onAfterInit()
        {
            // Skip on Security controller (login, logout, password reset, etc.)
            # Controller::has_curr() was removed in Silverstripe 6 (deprecated in 5.4), so calling it
            # unconditionally fataled every request there. On SS6 curr() simply returns null on an
            # empty stack; on SS5 curr() raises a warning in that case, so has_curr() is still asked
            # first wherever it exists (eg when a page is rendered from CLI with nothing pushed).
            # Both checks now live in ScriptGuard::scriptsAllowed(), shared with the on-init script paths.
            //$controller = Controller::has_curr() ? Controller::curr() : null;
//            $controller = (method_exists(Controller::class, 'has_curr') && !Controller::has_curr())
//                ? null
//                : Controller::curr();
//            if ($controller instanceof Security) {
//                return;
//            }

            // Skip in dev/test unless explicitly enabled
//            $siteConfig = SiteConfig::current_site_config();
//            if (!Director::isLive() && !$siteConfig->CookieBarScriptsInDevTest) {
//                return;
//            }
            if (!ScriptGuard::scriptsAllowed()) {
                return;
            }

            if (self::cookieBarEnabled() && !self::CookieConsent()) {
                if (CookieBarController::config()->get('sans_bs_css')) {
                    Requirements::css('restruct/silverstripe-cookiebar:client/dist/css/cookiebar-layout-sans-bs.css'); // non-bootstrap fallback layout (columns)
                } else {
                    Requirements::css('restruct/silverstripe-cookiebar:client/dist/css/cookiebar.css');
                }

//                Requirements::javascript('restruct/silverstripe-cookiebar:client/dist/js/CookieBar.js');
                Requirements::javascriptTemplate('restruct/silverstripe-cookiebar:client/dist/js/CookieBar.js',
                    [
                        'ConsentCookieKey'  => CookieBarController::getCookieName(),
                        'ConsentExpiration' => CookieBarController::getCookieAge(),
                    ]);

                // Inject optional JS code to run if/after consent
                # Only defined here: CookieBar.js calls it when the visitor clicks Accept.
//                if ($jsToRunIfConsent = SiteConfig::current_site_config()->CookieBarRunOnConsent) {
//                    $jsToRunIfConsent = strip_tags($jsToRunIfConsent); // just to be sure no <html> gets included...
//                    Requirements::customScript("function cookieBarRunIfConsent() {
//                        {$jsToRunIfConsent}
//                    }", 'cookiebar_run_if_consent');
//                }
                if ($runIfConsentFunction = self::runIfConsentFunction()) {
                    Requirements::customScript($runIfConsentFunction, 'cookiebar_run_if_consent');
                }
            } elseif (self::cookieBarEnabled()) {
                # Consent already exists, so the bar's CSS and CookieBar.js are (rightly) not loaded. The
                # run-if-consent script used to be output only together with them, so it ran on the one page
                # where the visitor clicked Accept and never again (eg Google Consent Mode 'update' was
                # missing on every later page load). Output it here as well and call it ourselves, since
                # CookieBar.js is not on the page to do so. The ScriptGuard check above applies.
                if ($runIfConsentFunction = self::runIfConsentFunction()) {
                    # Custom scripts are written at the end of <body> by default, but a project may move
                    # them to <head>; wait for the DOM in that case, as CookieBar.js would have.
                    Requirements::customScript($runIfConsentFunction . "
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', cookieBarRunIfConsent);
                    } else {
                        cookieBarRunIfConsent();
                    }", 'cookiebar_run_if_consent');
                }
            }
        }

        /**
         * The CookieBarRunOnConsent code wrapped in function cookieBarRunIfConsent(), or null when empty.
         * Shared by the before-consent path (CookieBar.js calls it on Accept) and the after-consent path.
         */
        private static function runIfConsentFunction(): ?string
        {
            $jsToRunIfConsent = SiteConfig::current_site_config()->CookieBarRunOnConsent;
            if (!$jsToRunIfConsent) {
                return null;
            }
            $jsToRunIfConsent = strip_tags($jsToRunIfConsent); // just to be sure no <html> gets included...

            return "function cookieBarRunIfConsent() {
                        {$jsToRunIfConsent}
                    }";
        }

        /**
         * insert javascript into the requirements & output the cookiebar markup
         */
        public function CookieBar()
        {
            if (self::cookieBarEnabled()) {
                return $this->owner->renderWith('Restruct\\CookieBar\\CookieBar');
            }

            return null;
        }

        public function getAcceptCookiesLink() : string
        {
            return CookieBarController::find_link('accept');
        }

        /**
         * The accept URL relative to the web root (eg /cookiebar/accept, or /subdir/cookiebar/accept),
         * for the <noscript> bar. getAcceptCookiesLink() returns the document-relative 'cookiebar/accept',
         * which a browser resolves against the current page and so 404s on any page below the site root.
         */
        public function getAcceptCookiesRootLink() : string
        {
            return Controller::join_links(Director::baseURL(), CookieBarController::find_link('accept'));
        }

        /**
         * Whether to output the <noscript> copy of the bar: under the same conditions the JS bar appears
         * (the bar's assets pass ScriptGuard and no consent exists yet). $CookieBar itself already
         * returns nothing when the bar is disabled.
         */
        public function ShowNoScriptCookieBar() : bool
        {
            return ScriptGuard::scriptsAllowed() && !CookieBarController::isCookieAccepted();
        }

        public function CookieConsent() : bool
        {
            return CookieBarController::isCookieAccepted();
        }
    }
}
