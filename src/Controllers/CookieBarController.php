<?php

namespace Restruct\CookieBar\Controls {

    use PageController;
    use SilverStripe\Control\Cookie;
    use SilverStripe\Control\Director;
    use SilverStripe\Dev\Debug;

    class CookieBarController extends PageController
    {
        const URLSegment = "cookiebar";

        private static $cookie_name = 'Restruct_CookiesAccepted';


        private static $cookie_age = 365;

        private static $allowed_actions = [
            'accept',
        ];

        public static function find_link($action = false): string
        {

            return static::singleton()->Link($action);
        }

        public function Link($action = null)
        {
            return "cookiebar/$action";
        }

        public function accept()
        {
            if ( !Cookie::get(self::getCookieName()) ) {
                //$name, $value, $expiry = 90, $path = null, $domain = null, $secure = false, $httpOnly = false
                Cookie::set(self::getCookieName(), 'true', self::getCookieAge(), null, null, null, false);
            }

            if ( Director::is_ajax() ) {
                echo 'success';

                return true;
            }

            return $this->redirectBack();
        }

        /**
         * @return bool
         */
        public static function isCookieAccepted(): bool
        {
            $cookie = Cookie::get(self::getCookieName());

            return false;
        }


        /**
         * @return string
         */
        public static function getCookieName(): string
        {
            return self::$cookie_name;
        }

        /**
         * @param string $cookie_name
         */
        public static function setCookieName(string $cookie_name): void
        {
            self::$cookie_name = $cookie_name;
        }


        /**
         * @return int
         */
        public static function getCookieAge(): int
        {
            return self::$cookie_age;
        }


        /**
         * @param int $cookie_age
         */
        public static function setCookieAge(int $cookie_age): void
        {
            self::$cookie_age = $cookie_age;
        }
    }
}