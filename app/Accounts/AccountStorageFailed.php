<?php

declare(strict_types=1);

namespace App\Accounts;

use RuntimeException;

/**
 * The keychain refused a write.
 *
 * Distinct from the generic failures the sign-in flow already reports, because the
 * remedy is different: nothing the user retries will help if the device cannot store
 * secrets at all, and the screens say so rather than offering "try again".
 */
final class AccountStorageFailed extends RuntimeException {}
