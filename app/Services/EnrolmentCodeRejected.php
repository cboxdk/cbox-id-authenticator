<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * The scanned code was refused.
 *
 * Carries a message written for the person holding the phone, because it is shown to
 * them verbatim — "expired, get a fresh one" is actionable in a way that "invalid
 * token" is not.
 */
final class EnrolmentCodeRejected extends RuntimeException {}
