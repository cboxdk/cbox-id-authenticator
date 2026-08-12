<?php

declare(strict_types=1);

namespace App\Support;

use App\NativeComponents\OAuthCallback;

/**
 * Recovers the query string from a deep link, because the router drops it.
 *
 * THE ROUTER HAS NO NOTION OF A QUERY STRING
 * ------------------------------------------
 * `NativeRouter::resolve()` matches the URI verbatim — exact key lookup, then
 * `#^pattern$#` with `{param}` widened to `[^/]+`. Nothing splits a `?` off first, and
 * nothing populates a component's navigation data from one. Two consequences, both of
 * which this app hits:
 *
 *   /oauth/callback?code=…&state=…   resolves to NULL. The `$` anchor cannot match, so
 *                                    the OAuth callback screen is never reached at all
 *                                    and the router exits to the web view.
 *
 *   /approvals/01A/01B?iss=…         resolves, but `[^/]+` is greedy and swallows the
 *                                    query, so the id parameter becomes `01B?iss=…`.
 *
 * Deep links are the app's entire re-entry surface — the OAuth callback and every
 * notification tap — so neither is a corner. Until the router grows query handling,
 * the screens recover it themselves from the URI the router recorded.
 *
 * @see OAuthCallback for the callback case
 */
final class DeepLinkQuery
{
    /**
     * The query parameters of a URI, however deeply the router mangled it.
     *
     * @return array<string, string>
     */
    public static function parse(?string $uri): array
    {
        if ($uri === null) {
            return [];
        }

        $mark = mb_strpos($uri, '?');

        if ($mark === false) {
            return [];
        }

        $parsed = [];
        parse_str(mb_substr($uri, $mark + 1), $parsed);

        return array_filter($parsed, is_string(...));
    }

    /**
     * A route parameter with any query string that leaked into it removed.
     *
     * `[^/]+` does not stop at a `?`, so the last parameter of a matched route carries
     * the query with it. An approval id read straight from the route would be
     * `01J…?iss=https://…`, which matches nothing on the server.
     */
    public static function cleanParam(string $value): string
    {
        $mark = mb_strpos($value, '?');

        return $mark === false ? $value : mb_substr($value, 0, $mark);
    }
}
