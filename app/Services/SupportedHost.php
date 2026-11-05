<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Whether this build will connect to a given host.
 *
 * Separate from {@see EnrolmentCode}, which asks only whether a string is SHAPED like a
 * host. This asks whether it is one we can actually serve, and the two deserve
 * different answers on screen: "that isn't an address" and "this app doesn't connect to
 * that host" send someone looking in completely different places.
 *
 * The reason is delivery, not trust — see config/cboxid.php. A self-hosted installation
 * is not less trustworthy; it simply cannot reach this handset, because a push token is
 * scoped to the Firebase project the binary was built with. Saying so plainly matters:
 * an operator refused here should know it is a limitation of this binary, not a
 * judgement about their server.
 */
final class SupportedHost
{
    public static function allows(string $host): bool
    {
        $host = mb_strtolower(trim($host));

        // The loopback exception the sign-in flow already depends on, so the flow stays
        // testable against a local `php artisan serve` without a deployment. Port and
        // all — EnrolmentCode has already established the shape.
        if (preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d{1,5})?$/', $host) === 1) {
            return app()->environment(['local', 'testing']);
        }

        if (preg_match('/^[a-z0-9]+(?:[a-z0-9.-]*[a-z0-9])?$/', $host) !== 1 || str_contains($host, '..')) {
            return false;
        }

        foreach (self::suffixes() as $suffix) {
            // The apex itself, or anything below it. Compared with a leading dot so
            // `notcboxid.com` cannot pass as a subdomain of `cboxid.com` — a plain
            // str_ends_with would let it.
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * What to tell someone whose host was refused.
     */
    public static function refusal(): string
    {
        $suffixes = implode(', ', self::suffixes());

        return __('This app connects to :hosts accounts only. A self-hosted Cbox ID needs its own build to send approval prompts to your phone.', ['hosts' => $suffixes]);
    }

    /**
     * @return list<string>
     */
    private static function suffixes(): array
    {
        $configured = config('cboxid.allowed_host_suffixes', []);

        return array_values(array_filter(
            is_array($configured) ? $configured : [],
            fn ($suffix): bool => is_string($suffix) && $suffix !== '',
        ));
    }
}
