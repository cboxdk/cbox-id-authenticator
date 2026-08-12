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
it('applies the right safe-area insets on every screen', function (string $component, array $params, int $expected): void {
    fakeSecureStorage();

    Native::test($component, $params);

    // 1 is both edges, 2 is top only, 3 bottom only.
    expect(Native::fakeBridge()->lastPublish()['layout']['safe_area'] ?? null)->toBe($expected);
})->with([
    'connect' => [Connect::class, [], 1],
    // Its bottom-bar is pinned with .safeAreaInset(.bottom) and owns that edge itself,
    // so the root must claim only the top or the content is inset twice.
    'approvals' => [Approvals::class, [], 2],
    'settings' => [Settings::class, [], 1],
    'oauth callback' => [OAuthCallback::class, [], 1],
    'approval detail' => [ApprovalDetail::class, ['account' => '01A', 'id' => '01B'], 1],
]);

it('keeps the native: prefix on the bottom bar, which is silently dropped without it', function (): void {
    // `<bottom-bar>` produces NOTHING — no element, no error. The precompiler leaves the
    // tag unrecognised and promotes its child, so the row lands back in the column
    // exactly as before and the fix looks applied while doing nothing at all.
    fakeSecureStorage();

    Native::test(Approvals::class);

    $types = [];
    $walk = function (array $node) use (&$walk, &$types): void {
        $types[] = $node['type'] ?? '';
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $walk($child);
            }
        }
    };
    $walk(Native::fakeBridge()->lastPublish());

    expect($types)->toContain('bottom_bar');
});
