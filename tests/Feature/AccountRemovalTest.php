<?php

declare(strict_types=1);

use App\Accounts\Account;
use App\Accounts\AccountStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

it('requires the matching destructive confirmation and returns to the accounts list', function (): void {
    fakeSecureStorage();
    $account = anAccount();
    app(AccountStore::class)->put(new Account('second', 'https://other.cboxid.com', 'Second account'));
    Http::fake(['*' => Http::response([], 200)]);
    $screen = Native::visit('/account/'.$account->id)->call('signOut', $account->id);
    expect(app(AccountStore::class)->find($account->id))->not->toBeNull();
    $screen->call('requestRemoval', $account->id);
    $id = lastRemovalDialogId();
    $screen->emitNative(ButtonPressed::class, ['id' => 'wrong', 'index' => 1, 'label' => 'Remove account']);
    expect(app(AccountStore::class)->find($account->id))->not->toBeNull();
    $screen->emitNative(ButtonPressed::class, ['id' => $id, 'index' => 1, 'label' => 'Remove account'])
        ->assertReplacedWith('/settings');
    expect(app(AccountStore::class)->find($account->id))->toBeNull();
    expect(app(AccountStore::class)->find('second'))->not->toBeNull();
});

it('keeps the account on cancellation and ignores an old dialog after returning', function (): void {
    $storage = fakeSecureStorage();
    $account = anAccount();
    $before = $storage->getArrayCopy();
    Http::fake();
    $screen = Native::visit('/account/'.$account->id)->call('requestRemoval', $account->id);
    $id = lastRemovalDialogId();
    $screen->emitNative(ButtonPressed::class, ['id' => $id, 'index' => 0, 'label' => 'Keep account'])
        ->call('signOut', $account->id);
    expect($storage->getArrayCopy())->toBe($before);
    $screen->call('requestRemoval', $account->id);
    $id = lastRemovalDialogId();
    $screen->call('onResume')->emitNative(ButtonPressed::class, ['id' => $id, 'index' => 1, 'label' => 'Remove account']);
    expect($storage->getArrayCopy())->toBe($before);
    Http::assertNothingSent();
});

it('shows connected account context without another confirmation after login', function (): void {
    fakeSecureStorage();
    anAccount();
    Http::fake(['*' => Http::response(['data' => []])]);
    Native::visit('/approvals')->assertSee('alice')->assertSee('You are all set')->assertDontSee('Got it');
    Native::fakeBridge()->assertNotCalled('Dialog.Alert');
});
