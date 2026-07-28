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
    /**
     * The handler a disabled or loading button points at. The press attribute cannot be
     * wrapped in a Blade directive (the precompiler parses directives inside an
     * element's attribute list as attributes), so an inert button is pointed here
     * instead of having its handler removed.
     */
    public function noop(): void {}

    public function goApprovals(): void
    {
        $this->replace('/approvals');
    }

    public function goConnect(): void
    {
        $this->replace('/');
    }

    public function goSettings(): void
    {
        $this->navigate('/settings');
    }

    public function goBack(): void
    {
        $this->back();
    }
}
