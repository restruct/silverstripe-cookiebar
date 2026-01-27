<?php

namespace Restruct\CookieBar\Extensions;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * Class SiteTreeExtension to inject CookieBarRunOnInit JS code into MetaTags
 */
class SiteTreeExtension extends Extension
{
    // Hook into MetaTags to inject CookieBarRunOnInit JS code before any other
    /**
     * @param $tags
     * @return void
     */
    public function MetaTags(&$tags)
    {
        // Skip on Security controller (login, logout, password reset, etc.)
        $controller = Controller::has_curr() ? Controller::curr() : null;
        if ($controller instanceof Security) {
            return;
        }

        $SiteConf = SiteConfig::current_site_config();
        if (!$SiteConf) {
            $tags .= "\n<!-- " . self::class . ": no current SiteConfig found... -->";
            return;
        }

        // Skip in dev/test unless explicitly enabled
        if (!Director::isLive() && !$SiteConf->CookieBarScriptsInDevTest) {
            return;
        }

        /** @var DBHTMLVarchar $jsRunOnInitScriptTag */
        if($jsRunOnInitScriptTag = $SiteConf->CookieBarRunOnInitScript()){
            $tags .= "\n" . $jsRunOnInitScriptTag->forTemplate();
        }
    }
}
