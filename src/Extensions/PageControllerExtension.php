<?php

namespace Restruct\CookieBar\Extensions {

    use Restruct\CookieBar\Controls\CookieBarController;
    use SilverStripe\Control\Director;
    use SilverStripe\Core\Extension;
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

        public function onAfterInit()
        {
            if ( self::isCookieBarEnable() ) {
                Requirements::css('restruct/silverstripe-cookiebar:client/dist/css/cookiebar.css');

                Requirements::customScript("

				// check if we should show
				if(document.cookie.indexOf('" . CookieBarController::getCookieName() . "=true')==-1){
					//jQuery('#cookiebar').show();
					$('body').prepend($('#cookiebar'));
				}
				
				// accept action
				$(document).on('click','#acceptcookies',function(e){
					e.preventDefault();
					$.ajax({
						url: '" . CookieBarController::find_link('accept') . "',
						success: function(data, textStatus) {
							if(data === 'success') {
								$('#cookiebar').slideUp(500);
							}
						}
					});				

				});
				
		", "cookiebar-script");

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
    }
}