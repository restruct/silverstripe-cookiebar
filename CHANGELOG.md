# Changelog

## 2.3.1 / 3.1.0 (unreleased)

**2.3.1 and 3.1.0 are the same code**, tagged twice: 2.3.1 for projects constrained to `^2`
(Silverstripe 5), 3.1.0 so that Silverstripe 6 projects, which Composer resolves to the 3.x tags,
receive these fixes. Both require Silverstripe `^5 || ^6`.

Makes the Silverstripe 6 support that 2.3.0 declared actually work, and fixes the two public setters.
No behaviour changes on Silverstripe 5 other than the setter fix. **Upgrade from 2.3.0 is strongly
recommended on Silverstripe 6**, where 2.3.0 breaks every page.

### Fixed

- **Silverstripe 6: every front-end request fataled** with `Call to undefined method
  SilverStripe\Control\Controller::has_curr()`. The Security-controller check added in 2.3.0 used
  `Controller::has_curr()`, which Silverstripe 6 removed. `sake db:build` died the same way, because a
  build renders the static error pages through `ErrorPageController`.
- **Silverstripe 6: the "RAW JS code to run on page initialisation" script was silently missing.**
  Silverstripe 6 renamed the `SiteTree` extension hook `MetaTags` to `updateMetaTags`; the module
  now implements both. Nothing errored, so the script (typically Google Consent Mode defaults) simply
  stopped being output. This also affected 3.0.0 and 3.0.1.
- **Silverstripe 6: the no-JavaScript accept link (`cookiebar/accept`) threw a TypeError** instead of
  setting the consent cookie: it passed `null` as `Cookie::set()`'s `$secure` argument, which is
  typed `bool` on Silverstripe 6. 3.0.1 fixed the same argument in `isCookieAccepted()` only.
- **The "RAW JS code to run if/after consent" script only ran on the page where the visitor clicked
  Accept**, on every Silverstripe version. It was output together with the bar's assets, which are
  (rightly) left out once the consent cookie exists, so on every later page load the script was
  missing - for example a Google Consent Mode `update` to `granted` ran once and never again. With
  consent, pages now output the `cookieBarRunIfConsent()` function and call it themselves, behind
  the same master switch, `Security`-controller skip and dev/test switch as the rest.
- **`$SiteConfig.CookieBarRunOnInitScript` placed by hand ignored the dev/test switch and the
  `Security`-controller skip** that 2.3.0 added to the `$MetaTags` path, so projects not using
  `$MetaTags` got the on-init script on development copies and on the login pages. Both output
  paths, and the bar itself, now share one check.
- **`CookieBarController::setCookieName()` and `setCookieAge()` threw a TypeError on every call**, on
  every Silverstripe version: they passed a scalar to `config()->merge()`, which only accepts arrays.
  They now use `config()->set()`.

### Changed

- `composer.json` now requires what the module uses: `silverstripe/cms`, `silverstripe/siteconfig`
  and `silverstripe/asset-admin` (`^5 || ^6` / `^2 || ^3`), `silverstripe/vendor-plugin` for the
  exposed client files, and PHP `^8.1`. Up to 2.3.0 only `silverstripe/framework` was required. Any
  project where the module actually worked already had all of these installed.
- Adds a `funding` property, so Packagist shows a Fund link.

### Added

- A behavioural test suite (48 tests) with a regression test for each fix above, and CI running it
  on Silverstripe 5 (PHP 8.1, 8.3) and 6 (PHP 8.3, 8.4), plus a real `dev/build` / `db:build`.
- `.gitattributes` keeps `tests/` and `.github/` out of dist installs.
- The README now documents every CMS setting, config option and the public API, and has a version
  compatibility table.

### Note on the 3.0.x tags

3.0.0 and 3.0.1 (Silverstripe 6 only) were tagged before 2.3.0 and lack its Security-controller and
dev/test switches as well as the fixes above. **3.1.0 supersedes them**: a Silverstripe 6 project on
`^3` or `^3.0` picks it up with a plain `composer update`. 3.1.0 is identical to 2.3.1 and also
installs on Silverstripe 5.

## 2.3.0

- Declares Silverstripe `^5 || ^6`.
- Skips the cookie bar and its scripts on the `Security` controller.
- Adds *Also insert scripts in dev/test environments*: outside `live`, nothing is injected unless it
  is ticked.
