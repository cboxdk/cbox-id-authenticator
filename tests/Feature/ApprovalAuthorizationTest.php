<?php

declare(strict_types=1);

use App\NativeComponents\ApprovalDetail;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Biometric\Completed;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    fakeSecureStorage();
    anAccount();
    Native::fakeBridge()->respondTo('Biometric.Prompt', []);
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
