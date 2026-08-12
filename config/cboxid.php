<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Hosts this build will connect to
    |--------------------------------------------------------------------------
    |
    | A suffix list. `cboxid.com` admits the apex and every subdomain under it.
    |
    | This is a DELIVERY constraint, not a trust one. A push token is scoped to the
    | Firebase project the binary was built with, so only a host that can send through
    | THAT project can alert this handset. Cbox's own hosts go through Cbox's server; a
    | self-hosted installation cannot, whatever it configures — its own Firebase project
    | cannot address a token minted by ours, and the platform falls back to
    | NullPushTransport. An account there would be polling-only, and an authenticator
    | that cannot alert you is not doing the job it was installed for.
    |
    | Everything else in the app is host-agnostic and stays that way: client ids are
    | discovered per host, secrets are namespaced per account, and nothing is compiled
    | in. Lifting this list is all that stands between here and self-hosted support —
    | either when a relay exists, or for an operator building their own binary against
    | their own Firebase project, who changes this one value and nothing else.
    |
    */

    'allowed_host_suffixes' => [
        'cboxid.com',
    ],

];
