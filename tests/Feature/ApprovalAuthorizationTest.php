<?php

declare(strict_types=1);

use App\NativeComponents\ApprovalDetail;
use App\Services\DeviceSecurity;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Biometric\Completed;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    fakeSecureStorage();
    anAccount();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.Authenticate', ['started' => true]);
    Http::fake([
        '*/approve' => Http::response(['status' => 'approved']),
        '*/deny' => Http::response(['status' => 'denied']),
        '*/api/v1/approvals/*' => Http::response(['data' => [
            'id' => '01REQUEST', 'status' => 'pending', 'expires_at' => gmdate('c', time() + 120),
        ]]),
    ]);
});

it('does not approve when the bridge merely acknowledges the prompt', function (): void {
    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve');
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('handles native dispatch refusals without waiting forever for a Face ID result', function (array $response): void {
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.Authenticate', $response);
    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve')
        ->assertSet('biometricAttempt', null)->assertSet('error', 'Could not start device authentication. Try again.');
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
})->with([
    'direct refusal' => [['started' => false]],
    'wrapped refusal' => [['status' => 'success', 'data' => ['started' => false]]],
    'missing bridge' => [['status' => 'error', 'code' => 'FUNCTION_NOT_FOUND']],
    'missing result' => [[]],
]);

it('accepts the iOS bridge envelope without treating dispatch as authentication', function (): void {
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.Authenticate', ['status' => 'success', 'data' => ['started' => true]]);
    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve')
        ->assertSet('error', null);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('reuses a recent native authentication only after the user taps approve', function (): void {
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', [
        'unlocked' => true, 'foreground' => true, 'generation' => 0, 'recentAuthentication' => true,
    ]);
    $screen = Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST']);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    $screen->call('approve')->assertSet('outcome', 'approved');
    Native::fakeBridge()->assertNotCalled('AuthenticatorSecurity.Authenticate');
    $screen->call('approve');
    expect(Http::recorded(fn ($request) => $request->method() === 'POST'))->toHaveCount(1);
});

it('does not reuse authentication after a native lock or while inactive', function (bool $unlocked, bool $foreground): void {
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', [
        'unlocked' => $unlocked, 'foreground' => $foreground, 'generation' => 1, 'recentAuthentication' => true,
    ]);
    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve');
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
})->with([[false, true], [false, false], [true, false]]);

it('does not reuse a recent authentication from a different generation', function (): void {
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', [
        'unlocked' => true, 'foreground' => true, 'generation' => 2, 'recentAuthentication' => true,
    ]);
    expect(app(DeviceSecurity::class)->canReuseAuthentication(1))->toBeFalse();
});

it('requires a matching successful event and consumes it once', function (): void {
    $screen = Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve');
    $id = $screen->get('biometricAttempt');
    expect($id)->toBeString()->not->toBeEmpty();
    $screen->emitNative(Completed::class, ['success' => true, 'id' => 'wrong']);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
    $screen->emitNative(Completed::class, ['success' => true, 'id' => $id])->assertSet('outcome', 'approved');
    $screen->emitNative(Completed::class, ['success' => true, 'id' => $id]);
    expect(Http::recorded(fn ($request) => $request->method() === 'POST'))->toHaveCount(1);
});

it('waits for the app to become active when Face ID completes before foreground activation', function (): void {
    $screen = Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve');
    $id = $screen->get('biometricAttempt');
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => false, 'foreground' => false, 'generation' => 0]);

    $screen->emitNative(Completed::class, ['success' => true, 'id' => $id])
        ->call('tick')->assertSet('error', null)->assertSet('biometricAttempt', $id);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');

    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => true, 'foreground' => true, 'generation' => 0]);
    $screen->call('tick')->assertSet('outcome', 'approved')->assertSet('biometricAttempt', null);
    $screen->emitNative(Completed::class, ['success' => true, 'id' => $id])->call('tick');
    expect(Http::recorded(fn ($request) => $request->method() === 'POST'))->toHaveCount(1);
});

it('rejects a queued Face ID result if the app really backgrounded before returning', function (): void {
    $screen = Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve');
    $id = $screen->get('biometricAttempt');
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => false, 'foreground' => false, 'generation' => 0]);
    $screen->emitNative(Completed::class, ['success' => true, 'id' => $id]);

    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => false, 'foreground' => true, 'generation' => 1]);
    $screen->call('tick');
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');

    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => true, 'foreground' => true, 'generation' => 1]);
    $screen->call('tick')->assertSet('biometricAttempt', null)
        ->assertSet('error', 'Authentication expired when the app was locked. Try again.');
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('discards successful authentication queued before leaving or a real request expiry', function (string $action): void {
    $screen = Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve');
    $id = $screen->get('biometricAttempt');
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => false, 'foreground' => false, 'generation' => 0]);
    $screen->emitNative(Completed::class, ['success' => true, 'id' => $id]);

    if ($action === 'expiry') {
        $screen->set('approval', ['expires_at' => gmdate('c', time() - 1)]);
    } else {
        $screen->call($action);
    }
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => true, 'foreground' => true, 'generation' => 0]);
    $screen->call('tick')->assertSet('biometricAttempt', null);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
})->with(['unmount', 'onResume', 'expiry']);

it('ignores cancelled authentication and late events after leaving or denying', function (string $action): void {
    $screen = Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve');
    $id = $screen->get('biometricAttempt');
    if ($action === 'cancel') {
        $screen->emitNative(Completed::class, ['success' => false, 'id' => $id]);
    } else {
        $screen->call($action);
    }
    $screen->emitNative(Completed::class, ['success' => true, 'id' => $id]);
    Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/approve'));
})->with(['cancel', 'unmount', 'onResume', 'deny', 'wasNotMe']);

it('does not approve an expired request after successful authentication', function (): void {
    $screen = Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('approve');
    $id = $screen->get('biometricAttempt');
    $screen->set('approval', ['expires_at' => gmdate('c', time() - 1)])
        ->emitNative(Completed::class, ['success' => true, 'id' => $id])->assertSet('outcome', 'expired');
    Http::assertNotSent(fn ($request) => $request->method() === 'POST');
});

it('sends not-me as a server-side report', function (): void {
    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])->call('wasNotMe')->assertSet('outcome', 'reported');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/deny') && $request['reason'] === 'not_me');
});

it('keeps an API outage distinct from a missing request', function (): void {
    Http::swap(new Factory);
    Http::fake(['*' => Http::response([], 503)]);
    Native::test(ApprovalDetail::class, ['account' => '01ACC', 'id' => '01REQUEST'])
        ->assertSet('missing', false)->assertSee('Check your connection');
});
