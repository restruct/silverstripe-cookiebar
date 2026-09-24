<?php

namespace Restruct\CookieBar\Extensions {

    use Restruct\CookieBar\Controls\CookieBarController;
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
            //$controller = Controller::has_curr() ? Controller::curr() : null;
            $controller = (method_exists(Controller::class, 'has_curr') && !Controller::has_curr())
                ? null
                : Controller::curr();
            if ($controller instanceof Security) {
                return;
            }

            // Skip in dev/test unless explicitly enabled
            $siteConfig = SiteConfig::current_site_config();
            if (!Director::isLive() && !$siteConfig->CookieBarScriptsInDevTest) {
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
                if ($jsToRunIfConsent = SiteConfig::current_site_config()->CookieBarRunOnConsent) {
                    $jsToRunIfConsent = strip_tags($jsToRunIfConsent); // just to be sure no <html> gets included...
                    Requirements::customScript("function cookieBarRunIfConsent() {
                        {$jsToRunIfConsent}
                    }", 'cookiebar_run_if_consent');
                }
            }
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

        public function CookieConsent() : bool
        {
            return CookieBarController::isCookieAccepted();
        }
    }
}
