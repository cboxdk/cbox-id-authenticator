<?php

declare(strict_types=1);

return [
    // Foreground approvals and offline codes do not require Firebase.
    'push_enabled' => (bool) env('AUTHENTICATOR_PUSH_ENABLED', false),

    /*
     * Associated Domains for the "Open Cbox ID app" Universal Link
     * (`https://{host}/authenticator/approve/{id}`). Each host must serve
     * `/.well-known/apple-app-site-association` naming this app's TEAMID.bundle-id.
     * Cbox ID points the link at the login host, or at the app host when the sign-in page
     * is itself on the login host (iOS keeps a same-host link in Safari), so both are here.
     */
    'universal_link_domains' => array_values(array_filter(array_map('trim', explode(',',
        (string) env('AUTHENTICATOR_UNIVERSAL_LINK_DOMAINS', 'login.cboxid.com,app.cboxid.com'))))),
];
