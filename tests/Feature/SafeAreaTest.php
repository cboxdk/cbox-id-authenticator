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
    'approvals' => [Approvals::class, [], 1],
    'settings' => [Settings::class, [], 1],
    'oauth callback' => [OAuthCallback::class, [], 1],
    'approval detail' => [ApprovalDetail::class, ['account' => '01A', 'id' => '01B'], 1],
]);

it('keeps the navigation controls out of native chrome these screens do not have', function (): void {
    // `<native:bottom-bar>` renders only under chrome: both branches calling
    // NativeComponent::resolveBottomBar() are gated on a tab bar or a nav bar, and no
    // screen here uses a NativeLayout. Wrapped in one, the node is hoisted out of the
    // tree and attached to nothing — the bar disappears and takes every navigation
    // control with it.
    //
    // The previous version of this spec asserted the node was PRESENT in the published
    // tree. It passed while the device showed no bottom row at all, which is the whole
    // lesson: presence in the tree says nothing about being rendered.
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

    expect($types)->not->toContain('bottom_bar');
});

it('offers a way to Settings and to adding an account', function (): void {
    // The controls themselves, which is what actually went missing.
    fakeSecureStorage();

    Native::test(Approvals::class)
        ->assertSee('Add account')
        ->assertSee('Settings');
});
