<?php

namespace Restruct\CkBrowser;

use PageController;
use SilverStripe\View\Requirements;
use SilverStripe\View\SSViewer;

/**
 * BROWSER-TEST FIXTURE ONLY - renders CkBPage with a minimal page template that has $MetaTags in
 * <head> and $CookieBar just before </body> (README "Installation").
 *
 * The template is a string because a fixture cannot ship a templates/ directory: the runner copies
 * tests/browser/fixtures/ into app/src/BrowserFixtures/, where the template manifest does not look.
 */
class CkBPageController extends PageController
{
    private const TEMPLATE = <<<'SS'
<!DOCTYPE html>
<html lang="en">
<head>
<% base_tag %>
$MetaTags
</head>
<body>
<main id="ckb-page">
<h1>$Title</h1>
$Content
<nav><a id="ckb-to-child" href="/cookie-home/cookie-child">Child page</a> <a id="ckb-to-home" href="/cookie-home">Home</a></nav>
</main>
$CookieBar
</body>
</html>
SS;

    public function index()
    {
        if (class_exists(\SilverStripe\TemplateEngine\SSTemplateEngine::class)) {
            # Silverstripe 6
            $html = (new \SilverStripe\TemplateEngine\SSTemplateEngine())
                ->renderString(self::TEMPLATE, new \SilverStripe\View\ViewLayerData($this));
        } else {
            # Silverstripe 5
            $html = SSViewer::fromString(self::TEMPLATE)->process($this);
        }
        # Neither string renderer adds the Requirements (on both majors only SSViewer::process() of a
        # file template does), so add them the way that would: the module's CSS/JS from onAfterInit().
        return Requirements::includeInHTML((string) $html);
    }
}
