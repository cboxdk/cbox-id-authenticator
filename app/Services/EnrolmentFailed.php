<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * The handset could not be registered with the host.
 *
 * Separate from a sign-in failure because the two need different words on screen: the
 * tokens may be perfectly good while the device record is missing, and telling the user
 * "sign-in failed" in that case would send them round a loop that changes nothing.
 */
final class EnrolmentFailed extends RuntimeException {}
