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
    # This is the Silverstripe 5 hook name; see updateMetaTags() below for Silverstripe 6.
    /**
     * @param $tags
     * @return void
     */
    public function MetaTags(&$tags)
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

    /**
     * Silverstripe 6 name of the same hook.
     *
     * SiteTree::MetaTags() fires extend('MetaTags') on Silverstripe 5 but extend('updateMetaTags') on
     * Silverstripe 6 (6.0.0 changelog, "Changes to some extension hook names"). Nothing errors when a
     * hook name stops being fired, so without this method the CookieBarRunOnInit script silently
     * disappeared from every page on Silverstripe 6. Each major fires exactly one of the two names,
     * so the script is never added twice.
     *
     * @param string $tags
     * @return void
     */
    public function updateMetaTags(&$tags)
    {
        $this->MetaTags($tags);
    }
}
