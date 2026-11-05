<?php

declare(strict_types=1);

use App\NativeComponents\Launch;
use App\NativeComponents\OAuthCallback;
use Native\Mobile\Testing\Native;

/**
 * @return list<string> every element type in the tree, depth-first
 */
function treeTypes(array $node, array $carry = []): array
{
    $carry[] = $node['type'] ?? '';

    foreach ($node['children'] ?? [] as $child) {
        if (is_array($child)) {
            $carry = treeTypes($child, $carry);
        }
    }

    return $carry;
}

it('gives every signed-in screen the same chrome', function (string $uri): void {
    fakeSecureStorage();

    Native::visit($uri);

    expect(treeTypes(Native::fakeBridge()->lastPublish()))
        ->toContain('native_root_tabs')
        ->not->toContain('bottom_nav')
        ->not->toContain('top_bar');
})->with([
    '/approvals',
    '/approvals/01A/01B',
    '/settings',
    '/connect',
    '/connect?host=untrusted.example',
    '/about',
]);

it('names all three tabs', function (): void {
    fakeSecureStorage();

    Native::visit('/approvals');

    $labels = [];
    $walk = function (array $node) use (&$walk, &$labels): void {
        if (($node['type'] ?? '') === 'bottom_nav_item') {
            $labels[] = $node['props']['label'] ?? '';
        }
        foreach ($node['children'] ?? [] as $child) {
            if (is_array($child)) {
                $walk($child);
            }
        }
    };
    $walk(Native::fakeBridge()->lastPublish());

    expect($labels)->toBe(['Codes', 'Requests', 'Settings']);
});

it('keeps account deep links in the settings tab', function (string $uri): void {
    fakeSecureStorage();
    Native::visit($uri)->assertElement('bottom_nav_item', fn (array $node): bool => ($node['props']['url'] ?? '') === '/settings' && ($node['props']['active'] ?? false));
})->with(['/connect', '/connect?host=untrusted.example', '/account/missing', '/about', '/licenses']);

it('leaves the safe areas to the layout on the screens it wraps', function (string $uri): void {
    // buildChromeColumn() applies neither edge when both bars exist — each handles its
    // own — so a `safe-area` class on the screen would be inset on top of that.
    fakeSecureStorage();

    Native::visit($uri);

    expect(Native::fakeBridge()->lastPublish()['layout']['safe_area'] ?? null)->toBeNull();
})->with([
    '/approvals',
    '/settings',
    '/connect',
    '/connect?host=untrusted.example',
    '/about',
]);

it('keeps the insets on the screens with no chrome at all', function (string $component): void {
    fakeSecureStorage();

    Native::test($component);

    // 1 is both edges. Nothing else applies them here.
    expect(Native::fakeBridge()->lastPublish()['layout']['safe_area'] ?? null)->toBe(1);
})->with([
    'launch' => Launch::class,
    'oauth callback' => OAuthCallback::class,
]);

it('keeps a route to the scanner now that the list has no controls of its own', function (): void {
    // Add account left the approvals screen with the hand-rolled row. Settings is the
    // only way to it, so losing it there would strand anyone with one account.
    fakeSecureStorage();

    Native::visit('/settings')->assertSee('Add another account');
});
