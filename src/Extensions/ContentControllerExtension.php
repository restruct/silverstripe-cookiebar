<?php

namespace Restruct\CookieBar\Extensions {

    use Restruct\CookieBar\Controls\CookieBarController;
    use SilverStripe\Core\Extension;
    use SilverStripe\SiteConfig\SiteConfig;
    use SilverStripe\View\Requirements;

    /**
     * ContentController extension to include CookieBar assets and markup
     */
    class ContentControllerExtension extends Extension
    {

        /**
         * @return bool
         */
        public static function cookieBarEnabled(): bool
        {
            return SiteConfig::current_site_config()->CookieBarEnable;
        }

        /**
         * @return void
         */
        public function onAfterInit()
        {
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
                        $jsToRunIfConsent
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
        }

        /**
         * @return string
         */
        public function getAcceptCookiesLink() : string
        {
            return CookieBarController::find_link('accept');
        }

        /**
         * @return bool
         */
        public function CookieConsent() : bool
        {
            return CookieBarController::isCookieAccepted();
        }
    }
}
