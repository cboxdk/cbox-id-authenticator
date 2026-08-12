<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The OAuth redirect URI, in ONE place.
 *
 * It must be byte-identical at `/authorize` and at `/token` — the server compares them
 * exactly, and a trailing slash is enough to fail the exchange. It was computed
 * separately in the connect screen and the callback screen, which is a two-line drift
 * away from a failure whose message ("invalid_grant") says nothing about the cause.
 *
 * WHY THE PRIVATE-USE SCHEME AND NOT THE HTTPS APP LINK
 * -----------------------------------------------------
 * RFC 8252 §7.2 prefers a claimed HTTPS URI, because a private-use scheme can be
 * registered by a malicious app on the same handset and the OS gives no guarantee about
 * which one wins. The platform registers both. But an App Link is bound to one host, and
 * this app connects to many — a build pinned to `cbox.cboxid.com` could never complete a
 * sign-in against a self-hosted installation. Multi-account settles the choice.
 *
 * A single slash, not two: `scheme:/path` has no authority component. `scheme://path`
 * would make `path` the authority and change the URI the server compares.
 */
final class RedirectUri
{
    public static function current(): string
    {
        $scheme = (string) config('nativephp.deeplink_scheme', 'com.cboxid.authenticator');

        return $scheme.':/oauth/callback';
    }
}
