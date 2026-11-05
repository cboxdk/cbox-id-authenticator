<?php

declare(strict_types=1);
use App\Events\BackupFileResult;
use App\NativeComponents\AddCode;
use App\NativeComponents\CodeBackup;
use App\NativeComponents\Codes;
use App\NativeComponents\Launch;
use App\NativeComponents\Settings;
use App\Services\AppSecurity;
use App\Totp\EncryptedBackup;
use App\Totp\TotpStore;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

it('saves and displays codes without a Cbox account', function (): void {
    fakeSecureStorage();
    Native::test(AddCode::class)
        ->set('issuer', 'Example')->set('account', 'alice')->set('secret', 'JBSWY3DPEHPK3PXP')
        ->call('save')->assertReplacedWith('/codes');
    expect(app(TotpStore::class)->all())->toHaveCount(1);
    Native::test(Codes::class)->assertSee('Example')->assertSee('alice')->assertSee('Copy code for alice');
    Native::test(Launch::class)->assertReplacedWith('/codes');
});

it('lets a scanned TOTP be reviewed before saving and clears the setup key after locking', function (): void {
    fakeSecureStorage();
    $screen = Native::test(AddCode::class)
        ->call('scanned', 'otpauth://totp/Example:alice?secret=JBSWY3DPEHPK3PXP')
        ->assertSet('account', 'alice')->assertSet('issuer', 'Example');
    expect(app(TotpStore::class)->isEmpty())->toBeTrue();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => false, 'foreground' => false, 'generation' => 1]);
    $screen->call('guard')->assertSet('secret', '');
    $screen->call('save');
    expect(app(TotpStore::class)->isEmpty())->toBeTrue();
});

it('copies a fresh code with its actual expiration and clears displayed codes while locked', function (): void {
    fakeSecureStorage();
    app(TotpStore::class)->merge([testTotpEntry()]);
    $id = app(TotpStore::class)->all()[0]->id;
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.CopyCode', ['success' => true]);
    $screen = Native::test(Codes::class)->call('copy', $id);
    Native::fakeBridge()->assertCalled('AuthenticatorSecurity.CopyCode', fn (array $p): bool => preg_match('/^[0-9]{6}$/D', $p['code']) === 1 && $p['expires'] > time() && $p['expires'] <= time() + 30);
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => false, 'foreground' => false, 'generation' => 1]);
    $screen->call('refresh')->assertSet('codes', []);
});

it('keeps the TOTP vault and app lock when the last Cbox account is removed', function (): void {
    $storage = fakeSecureStorage();
    $account = anAccount();
    app(TotpStore::class)->merge([testTotpEntry()]);
    $before = $storage[TotpStore::KEY];
    Http::fake(['*' => Http::response([], 200)]);
    Native::test(Settings::class)->call('signOut', $account->id);
    expect($storage[TotpStore::KEY])->toBe($before)->and(app(AppSecurity::class)->hasSecrets())->toBeTrue();
    Native::fakeBridge()->assertCalled('AuthenticatorSecurity.Configure', fn (array $p): bool => $p['enabled'] === true);
});

it('requires explicit confirmation before deleting a code', function (): void {
    fakeSecureStorage();
    app(TotpStore::class)->merge([testTotpEntry()]);
    $id = app(TotpStore::class)->all()[0]->id;
    $screen = Native::visit('/codes/detail/'.$id)->call('remove');
    expect(app(TotpStore::class)->all())->toHaveCount(1);
    $screen->call('requestRemoval')->call('remove')->assertReplacedWith('/codes');
    expect(app(TotpStore::class)->isEmpty())->toBeTrue();
});

it('sends only encrypted data to the file picker and clears the password', function (): void {
    fakeSecureStorage();
    app(TotpStore::class)->merge([testTotpEntry()]);
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.SaveBackup', ['success' => true]);
    Native::test(CodeBackup::class)->set('password', 'a good backup password')
        ->set('confirmation', 'a good backup password')->call('export')->assertSet('password', '')->assertSet('confirmation', '');
    Native::fakeBridge()->assertCalled('AuthenticatorSecurity.SaveBackup', function (array $p): bool {
        expect($p['contents'])->not->toContain(testTotpEntry()->secret)->not->toContain('a good backup password');

        return count(app(EncryptedBackup::class)->decrypt($p['contents'], 'a good backup password')) === 1;
    });
});

it('correlates file callbacks and previews imports before the vault changes', function (): void {
    fakeSecureStorage();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.PickBackup', ['success' => true]);
    $screen = Native::test(CodeBackup::class)->call('chooseFile');
    $calls = array_values(array_filter(Native::fakeBridge()->calls, fn (array $c): bool => $c['method'] === 'AuthenticatorSecurity.PickBackup'));
    $id = $calls[0]['params']['id'];
    $encrypted = app(EncryptedBackup::class)->encrypt([testTotpEntry()], 'a good backup password');
    $screen->emitNative(BackupFileResult::class, ['id' => 'wrong', 'result' => 'picked', 'contents' => $encrypted])->assertSet('hasFile', false);
    $screen->emitNative(BackupFileResult::class, ['id' => $id, 'result' => 'picked', 'contents' => $encrypted])
        ->assertSet('hasFile', true)->set('password', 'a good backup password')->call('inspect')
        ->assertSet('preview', true)->assertSet('added', 1)->assertSet('password', '');
    expect(app(TotpStore::class)->isEmpty())->toBeTrue();
    $screen->call('import')->assertSet('preview', false)->assertSet('hasFile', false);
    expect(app(TotpStore::class)->all())->toHaveCount(1);
});

it('discards an import preview when the app backgrounds even if already unlocked on return', function (): void {
    fakeSecureStorage();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.PickBackup', ['success' => true]);
    $screen = Native::test(CodeBackup::class)->call('chooseFile');
    $calls = array_values(array_filter(Native::fakeBridge()->calls, fn (array $c): bool => $c['method'] === 'AuthenticatorSecurity.PickBackup'));
    $encrypted = app(EncryptedBackup::class)->encrypt([testTotpEntry()], 'a good backup password');
    $screen->emitNative(BackupFileResult::class, ['id' => $calls[0]['params']['id'], 'result' => 'picked', 'contents' => $encrypted])
        ->set('password', 'a good backup password')->call('inspect')->assertSet('preview', true);
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => true, 'foreground' => true, 'generation' => 1]);
    $screen->call('import')->assertSet('preview', false);
    expect(app(TotpStore::class)->isEmpty())->toBeTrue();
});

it('renders all code routes using native chrome', function (string $path): void {
    fakeSecureStorage();
    Native::visit($path);
    expect(treeTypes(Native::fakeBridge()->lastPublish()))->toContain('native_root_tabs')->not->toContain('web_view');
})->with(['/codes', '/codes/add', '/codes/backup']);

it('can connect the first Cbox account from the requests tab without losing the code tab', function (): void {
    fakeSecureStorage();
    Native::visit('/approvals')->assertSee('Connect an account')
        ->call('addAccount')->assertNavigatedTo('/connect');
});
