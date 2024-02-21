<?php

namespace Restruct\CookieBar\Extensions {

    use Restruct\CookieBar\Controls\CookieBarController;
    use SilverStripe\Control\Director;
    use SilverStripe\Core\Convert;
    use SilverStripe\Core\Extension;
    use SilverStripe\ORM\FieldType\DBHTMLVarchar;
    use SilverStripe\SiteConfig\SiteConfig;
    use SilverStripe\View\Requirements;

    class PageControllerExtension extends Extension
    {

        /**
         * @return bool
         */
        public static function isCookieBarEnable(): bool
        {
            return SiteConfig::current_site_config()->CookieBarEnable;
        }

        public function CookieBarRunOnInitScript()
        {
            if($jsToRunOnInit = SiteConfig::current_site_config()->CookieBarRunOnInit){
                $jsToRunOnInit = strip_tags($jsToRunOnInit); // just to be sure no <html> gets included...

                return DBHTMLVarchar::create()->setValue("<script>$jsToRunOnInit</script>");
            }
        }

        public function onAfterInit()
        {
            if ( self::isCookieBarEnable() ) {
                Requirements::css('restruct/silverstripe-cookiebar:client/dist/css/cookiebar.css');
//                Requirements::css('restruct/silverstripe-cookiebar:client/dist/css/cookiebar-layout-sans-bs.css'); // non-bootstrap fallback layout
                Requirements::javascript('restruct/silverstripe-cookiebar:client/dist/js/CookieBar.js');

                // Inject optional JS code to run on init and on consent
//                if($jsToRunOnInit = SiteConfig::current_site_config()->CookieBarRunOnInit){
//                    $jsToRunOnInit = strip_tags($jsToRunOnInit); // just to be sure no <html> gets included...
////                    Requirements::customScript($jsToRunOnInit, 'cookiebar_run_on_init');
//                    Requirements::insertHeadTags("<script>$jsToRunOnInit</script>", 'cookiebar_run_on_init');
//                }
                if($jsToRunOnConsent = SiteConfig::current_site_config()->CookieBarRunOnConsent){
                    $jsToRunOnConsent = strip_tags($jsToRunOnConsent); // just to be sure no <html> gets included...
                    Requirements::customScript("function cookieBarRunOnConsent() {
                        $jsToRunOnConsent
                    }", 'cookiebar_run_on_consent');
                }
            }
        }

        /**
         * insert javascript into the requirements & output the cookiebar markup
         */
        public function CookieBar()
        {
            if ( self::isCookieBarEnable() ) {
                //$isCookieAccepted = CookieBarController::isCookieAccepted();

                return $this->owner->renderWith('Restruct\\CookieBar\\CookieBar');
            }
        }

        public function getAcceptCookiesLink()
        {
            return CookieBarController::find_link('accept');
        }

        public function CookieConsent()
        {
            return CookieBarController::isCookieAccepted();
        }
    }
}
