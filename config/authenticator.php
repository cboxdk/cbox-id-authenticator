<?php

declare(strict_types=1);

return [
    // Foreground approvals and offline codes do not require Firebase.
    'push_enabled' => (bool) env('AUTHENTICATOR_PUSH_ENABLED', false),
];
