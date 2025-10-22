<?php

namespace Restruct\CookieBar\Controls {

    use PageController;
    use SilverStripe\Control\Cookie;
    use SilverStripe\Control\Director;

    /**
     * Class CookieBarController to handle cookie consent actions
     */
    class CookieBarController extends PageController
    {
        /**
         * @var string
         * URL segment for this controller
         */
        const URLSegment = "cookiebar";

        /**
         * @var bool
         */
        private static $sans_bs_css = false; // optionally include CSS with column-layout for sites not using Bootstrap

        /**
         * @var string
         */
        private static $cookie_name = 'cookie_consent';

        /**
         * @var int
         */
        private static $cookie_age = 365;

        /**
         * @var bool
         */
        private static $cookie_refresh = true;

        /**
         * @var array
         */
        private static $allowed_actions = [
            'accept',
        ];

        /**
         * @param $action
         * @return string
         */
        public static function find_link($action = false): string
        {
            return static::singleton()->Link($action);
        }

        /**
         * @param $action
         * @return string
         */
        public function Link($action = null)
        {
            return "cookiebar/$action";
        }

        /**
         * This action will only get requested if JS is unsupported (or blocked), else the cookie will get set directly from JS
         * @return \SilverStripe\Control\HTTPResponse|string
         */
        public function accept()
        {
            if ( !self::isCookieAccepted() ) {
                //$name, $value, $expiry = 90, $path = null, $domain = null, $secure = false, $httpOnly = false
                Cookie::set(self::getCookieName(), time(), self::getCookieAge() ?: 365, null, null, null, false);
            }

            if ( Director::is_ajax() ) {
                return 'success';
            }

            return $this->redirectBack();
        }

        /**
         * @return bool
         */
        public static function isCookieAccepted(): bool
        {
            $cookieVal = Cookie::get(self::getCookieName()) ?: Cookie::get('Restruct_CookiesAccepted'); // fallback to legacy cookie

            // 'Refresh' cookie if required
            if($cookieVal && self::config()->get('cookie_refresh')) {
                Cookie::set(self::getCookieName(), $cookieVal, self::getCookieAge(), null, null, null, false);
            }

            return $cookieVal !== null;

        }


        /**
         * @return string
         */
        public static function getCookieName(): string
        {
            return self::config()->get('cookie_name');
        }

        /**
         * @param string $cookie_name
         */
        public static function setCookieName(string $cookie_name): void
        {
            self::config()->merge('cookie_name', $cookie_name);
        }


        /**
         * @return int
         */
        public static function getCookieAge(): int
        {
            return self::config()->get('cookie_age');
        }


        /**
         * @param int $cookie_age
         */
        public static function setCookieAge(int $cookie_age): void
        {
            self::config()->merge('cookie_age', $cookie_age);
        }
    }
}
