<?php

namespace Restruct\CookieBar\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
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
        $SiteConf = SiteConfig::current_site_config();
        if (!$SiteConf) {
            $tags .= "\n<!-- " . self::class . ": no current SiteConfig found... -->";
            return;
        }

        /** @var DBHTMLVarchar $jsRunOnInitScriptTag */
        if($jsRunOnInitScriptTag = $SiteConf->CookieBarRunOnInitScript()){
            $tags .= "\n" . $jsRunOnInitScriptTag->forTemplate();
        }
    }
}
