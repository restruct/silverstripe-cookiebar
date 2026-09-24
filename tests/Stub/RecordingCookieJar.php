<?php

namespace Restruct\CookieBar\Tests\Stub;

use SilverStripe\Control\CookieJar;
use SilverStripe\Dev\TestOnly;

/**
 * A CookieJar that records every set() call, so a test can assert WHAT the module tried to write
 * (name, value, expiry in days, secure flag) rather than only whether a cookie ended up readable.
 *
 * The signature is written to be compatible with both parents: Silverstripe 5's CookieJar::set()
 * is untyped with seven parameters, Silverstripe 6's is typed, has an eighth ($sameSite) and
 * returns void. Untyped parameters widen both, and a void return is allowed over an untyped one.
 *
 * Not a DataObject, so it is safe to live in tests/ of an installed module (see the SOP trap about
 * fixtures that break consumers' temp-database builds).
 */
class RecordingCookieJar extends CookieJar implements TestOnly
{
    /** @var array<int, array> one entry per set() call, in call order */
    public array $calls = [];

    public function set(
        $name,
        $value,
        $expiry = 90,
        $path = null,
        $domain = null,
        $secure = false,
        $httpOnly = true,
        $sameSite = ''
    ): void {
        $this->calls[] = [
            'name' => $name,
            'value' => $value,
            'expiry' => $expiry,
            'secure' => $secure,
            'httpOnly' => $httpOnly,
        ];

        parent::set(...func_get_args());
    }
}
