<?php

namespace Restruct\CookieBar\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBHTMLVarchar;
use SilverStripe\SiteConfig\SiteConfig;

class SiteTreeExtension
extends DataExtension
{
    // Hook into MetaTags to inject CookieBarRunOnInit JS code before any other
    public function MetaTags(&$tags)
    {
        $SiteConf = SiteConfig::current_site_config();
        if (!$SiteConf) {
            $tags .= "\n<!-- " . __CLASS__ . ": no current SiteConfig found... -->";
            return;
        }
        /** @var DBHTMLVarchar $jsRunOnInitScriptTag */
        if($jsRunOnInitScriptTag = $SiteConf->CookieBarRunOnInitScript()){
            $tags .= "\n" . $jsRunOnInitScriptTag->forTemplate();
        }
    }
}