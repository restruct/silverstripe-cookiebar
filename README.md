# Cookiebar Module #

*Maintained by [Restruct](https://github.com/restruct). If this module saves you time, you can
[support ongoing maintenance](https://github.com/sponsors/restruct).*

Configurable notice about cookies, a link to a page about them and an 'accept' link to close the notice (based on the cookiebar module by Aram Balakjian & Steve Heyes).

<img width="804" alt="Screenshot 2022-07-02 at 14 13 29" src="https://user-images.githubusercontent.com/1005986/177000331-e8613a6f-8ce9-4920-a8fe-e012c78ac50b.png">

## Requirements

* Silverstripe CMS 5 or 6 (`silverstripe/cms`, `silverstripe/siteconfig`, `silverstripe/asset-admin`)
* PHP 8.1 or newer (Silverstripe 6 itself needs 8.3)
* A project `PageController` class: `CookieBarController` (the `cookiebar/accept` action) extends
  the project's own `PageController`, which every standard CMS project has

## Version compatibility

| Branch | Module version | Silverstripe | PHP |
|--------|----------------|--------------|-----|
| `master` | `2.3.1` and `3.1.0` (same code) | `^5 \|\| ^6` | `^8.1` |
| (tags only) | `3.0.0`, `3.0.1` | `^6` | not declared (Silverstripe 6: 8.3+); superseded by `3.1.0` |
| (tags only) | `2.2.4` | `^4 \|\| ^5` | not declared (follows Silverstripe) |
| (tags only) | `2.0` - `2.2.3` | `^4.4` | not declared (follows Silverstripe) |

Silverstripe 4 reached end of life in April 2025 and is no longer supported or tested here. Projects
still on it should stay on the `2.2.x` tags, which remain available.

`master` is the only maintained line and supports Silverstripe 5 and 6 from one codebase. Its
current release is tagged twice with identical code: `2.3.1` for projects constrained to `^2`, and
`3.1.0` so that Silverstripe 6 projects on `^3` receive the same fixes. The older `3.0.0` and `3.0.1`
tags (Silverstripe 6 only) predate this line and are superseded by `3.1.0`; see
[CHANGELOG.md](CHANGELOG.md).

**`composer.json` is the source of truth** for exact constraints; this table is a quick reference.

## Installation

Install via `composer require restruct/silverstripe-cookiebar` and add $CookieBar just before the closing body tag:

```
...
    $CookieBar
</body>
```

The included template uses bootstrap for its layout. In case the site theme does not use bootstrap, a 'sans-bs' CSS file can be included which provides a fallback layout (see config).

The module applies its extensions itself (to `SiteTree`, `ContentController` and `SiteConfig`); run
`dev/build` (Silverstripe 5) or `sake db:build` (Silverstripe 6) after installing to add its
`SiteConfig` fields.

## How it works

* The bar is switched on and edited per site in the CMS, under *Settings > CookieBar*.
* While the bar is enabled and the visitor has not consented, every page built on
  `ContentController` loads the bar's CSS and `CookieBar.js`. The script shows the bar from the
  `$CookieBar` markup and, on *Accept*, writes the consent cookie itself.
* Visitors without JavaScript follow the *Accept* link instead, to `cookiebar/accept`, which sets the
  same cookie server side and redirects back (an AJAX request gets the plain response `success`).
* Once consent has been given, the bar's CSS and JS are no longer loaded at all.
* Nothing is injected on the `Security` controller (login, logout, lost password).
* **In `dev` and `test` environments nothing is injected** unless *Also insert scripts in dev/test
  environments* is ticked, so tracking scripts do not run on development copies by accident.

## CMS settings (SiteConfig)

| Field | Purpose |
|-------|---------|
| Enable Cookie Bar | Master switch. Off by default. |
| Cookie Bar Title | Heading in the bar. |
| Accept/Close Link Text | Text on the accept button. Default `Accept`. |
| More Information Link Text | Text on the link to the information page. Default `Read more about Cookies`. |
| Cookie Information Page | Page the information link points at; the link is hidden when empty. |
| Cookie bar Content | Body text (hidden on mobile). A default text is provided. |
| Image (optional) | jpg, jpeg, gif or png, shown at 80px height. |
| Optional RAW JS code to run on page initialisation | Output in `$MetaTags`, before other scripts, whether or not the visitor has consented: for example Google Consent Mode defaults (the field's placeholder shows an example). Not output in `dev` and `test` environments unless *Also insert scripts in dev/test environments* is ticked, nor on the `Security` controller (login, logout, lost password). HTML tags are stripped. |
| Optional RAW JS code to run if/after consent | Wrapped in a function `cookieBarRunIfConsent()`, which `CookieBar.js` calls when the visitor accepts. It is output together with the bar's assets, so only on pages rendered for a visitor who had not yet consented (a page served from a full-page cache also calls it once the consent cookie exists). HTML tags are stripped. |
| Also insert scripts in dev/test environments | See *How it works* above. |

The on-init script is added through `$MetaTags`. If your templates do not call `$MetaTags()`, place
`$SiteConfig.CookieBarRunOnInitScript` in the `<head>` yourself.

## Configuration

Config options (with their defaults):
```yml
Restruct\CookieBar\Controls\CookieBarController:
  sans_bs_css: false # include no-bootstrap version CSS
  cookie_name: 'cookie_consent' # name of cookie with timestamp of consent
  cookie_age: 365 # cookie expiration in days
  cookie_refresh: true # refresh consent cookie upon each request
```

A cookie named `Restruct_CookiesAccepted`, set by older versions of this module, is still accepted as
consent.

Prefab CSS inclusion can be blocked altogether from `_config.php`:
```php
Requirements::block('restruct/silverstripe-cookiebar:client/dist/css/cookiebar.css');
```

## Public API

For templates, on any `ContentController`:

* `$CookieBar` - the bar markup (a `text/x-template` script block), or nothing when the bar is disabled.
* `$CookieConsent` - whether the visitor has consented. Also available as `$SiteConfig.CookieConsent`.
* `$AcceptCookiesLink` - the no-JavaScript accept URL.

From PHP, static methods on `Restruct\CookieBar\Controls\CookieBarController`:

* `isCookieAccepted(): bool` - whether consent exists (refreshes the cookie when `cookie_refresh` is on).
* `getCookieName()` / `setCookieName(string)` and `getCookieAge()` / `setCookieAge(int)` - read or
  override `cookie_name` and `cookie_age` at runtime.
* `find_link($action)` - the controller URL for an action, eg `find_link('accept')`.

And `Restruct\CookieBar\Extensions\ContentControllerExtension::cookieBarEnabled(): bool` for the
current site's master switch.

## Running the tests

The module cannot be tested on its own: it needs a host Silverstripe project (built from
`silverstripe/recipe-cms` plus `silverstripe/recipe-testing`). Require it there through a Composer
**path repository with `symlink: true`** - `/tests` is `export-ignore`, so a dist or mirrored install
contains no tests - add the `Restruct\CookieBar\Tests\` namespace to the host's `autoload-dev`
(pointing at `vendor/restruct/silverstripe-cookiebar/tests/`), then:

```bash
# Silverstripe 5 (PHPUnit 9) - the path must come before flush=1
vendor/bin/phpunit vendor/restruct/silverstripe-cookiebar/tests flush=1

# Silverstripe 6 (PHPUnit 11) - a flush=1 argument is ignored, use the env var
SS_PHPUNIT_FLUSH=1 vendor/bin/phpunit vendor/restruct/silverstripe-cookiebar/tests
```

CI runs the same suite against Silverstripe 5 and 6 on every push; see `.github/workflows/ci.yml`.
