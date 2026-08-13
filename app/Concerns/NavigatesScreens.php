<?php

declare(strict_types=1);

namespace App\Concerns;

/**
 * Navigation targets as METHODS rather than `@navigate` attributes.
 *
 * The EDGE precompiler cannot interpolate into `@navigate` — `@navigate="{{ $x }}"`
 * compiles the braces straight into PHP and fails — so a reusable button component
 * cannot carry a route. Naming a method instead keeps navigation in PHP, where it is
 * also testable.
 */
trait NavigatesScreens
{
    public function goApprovals(): void
    {
        $this->replace('/approvals');
    }

    /**
     * Back to the start, not to `/connect`.
     *
     * `/` is the router: it sends a handset with no accounts to the scanner and one with
     * accounts to the approvals list. Pointing straight at `/connect` would strand
     * someone who already has an account on a screen offering to add another when what
     * failed was signing in.
     */
    public function goConnect(): void
    {
        $this->replace('/');
    }

    public function goBack(): void
    {
        $this->back();
    }
}
