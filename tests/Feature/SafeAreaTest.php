<?php

declare(strict_types=1);

use App\NativeComponents\ApprovalDetail;
use App\NativeComponents\Approvals;
use App\NativeComponents\Connect;
use App\NativeComponents\OAuthCallback;
use App\NativeComponents\Settings;
use Native\Mobile\Testing\Native;

/**
 * Every screen here is chrome-less — no NativeLayout, no nav bar — so nothing applies
 * the status-bar and home-indicator insets on their behalf. Without `safe-area` on the
 * root, a title sits under the notch and the bottom row under the home indicator, which
 * is exactly how the app shipped to the first device.
 *
 * Asserted on the PUBLISHED TREE rather than the markup: `safe-area` is a Tailwind class
 * the EDGE parser has to recognise, and a typo would leave the class in the Blade file
 * and nothing in the layout the native side reads.
 */
it('applies both safe-area insets on every screen', function (string $component, array $params): void {
    fakeSecureStorage();

    Native::test($component, $params);

    expect(Native::fakeBridge()->lastPublish()['layout']['safe_area'] ?? null)
        // 1 is both edges; 2 is top only and 3 bottom only. A screen that owns its whole
        // surface needs both.
        ->toBe(1);
})->with([
    'connect' => [Connect::class, []],
    'approvals' => [Approvals::class, []],
    'settings' => [Settings::class, []],
    'oauth callback' => [OAuthCallback::class, []],
    'approval detail' => [ApprovalDetail::class, ['account' => '01A', 'id' => '01B']],
]);
