<?php

declare(strict_types=1);
use App\Events\BackupFileResult;
use App\NativeComponents\AddCode;
use App\NativeComponents\CodeBackup;
use App\NativeComponents\Codes;
use App\NativeComponents\Launch;
use App\NativeComponents\Settings;
use App\Services\AppSecurity;
use App\Support\ServiceIcon;
use App\Totp\EncryptedBackup;
use App\Totp\TotpEntry;
use App\Totp\TotpStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Alert\ButtonPressed;
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
    $screen = Native::test(Codes::class)->call('copy', $id)->assertSet('copiedId', $id)->assertSee('Copied');
    Native::fakeBridge()->assertCalled('AuthenticatorSecurity.CopyCode', fn (array $p): bool => preg_match('/^[0-9]{6}$/D', $p['code']) === 1 && $p['expires'] > time() && $p['expires'] <= time() + 30);
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => false, 'foreground' => false, 'generation' => 1]);
    $screen->call('refresh')->assertSet('codes', [])->assertSet('copiedId', null)
        ->assertSet('selectedCode', null)->assertSet('selectedId', null);
});

it('keeps the chosen service through refreshes and resolves filtering and removal without stale codes', function (): void {
    fakeSecureStorage();
    $first = testTotpEntry();
    $second = new TotpEntry(str_repeat('b', 32), 'GitHub', 'work@example.test', 'JBSWY3DPEHPK3PXP', 'SHA256', 8, 60);
    app(TotpStore::class)->merge([$first, $second]);
    [$a, $b] = app(TotpStore::class)->all();
    $screen = Native::test(Codes::class)->assertSet('selectedId', $a->id)
        ->call('select', $b->id)->call('refresh')->assertSet('selectedId', $b->id);
    expect($screen->get('selectedCode')['code'])->toMatch('/^\d{4} \d{4}$/')
        ->and($screen->get('selectedCode')['period'])->toBe(60)
        ->and($screen->get('selectedCode')['icon'])->toBe('github');
    $screen->call('select', 'unknown')->assertSet('selectedId', $b->id)
        ->set('search', '  example  ')->assertSet('selectedId', $b->id)
        ->set('search', ' ALICE ')->assertSet('selectedId', $a->id)
        ->set('search', 'nothing matches')->assertSet('selectedId', null)->assertSet('selectedCode', null)
        ->set('search', '')->call('select', $b->id);
    app(TotpStore::class)->remove($b->id);
    $screen->call('onResume')->assertSet('selectedId', $a->id);
});

it('remembers only a valid display preference without persisting codes or selection', function (): void {
    fakeSecureStorage();
    Cache::forget('codes.display');
    Native::test(Codes::class)->assertSet('display', 'grid')->call('show', 'list');
    Native::test(Codes::class)->assertSet('display', 'list')->call('show', 'invalid')->assertSet('display', 'list');
    expect(Cache::get('codes.display'))->toBe('list');
    Native::test(Codes::class)->call('show', 'grid');
    Native::test(Codes::class)->assertSet('display', 'grid')->assertSet('selectedId', null);
});

it('uses bundled service icons with an honest fallback for unfamiliar issuers', function (): void {
    $manifest = json_decode(file_get_contents(resource_path('brand/services/source.json')), true);
    foreach (array_keys($manifest['colors']) as $slug) {
        expect(file_exists(public_path('service-icons/'.$slug.'.png')))->toBeTrue();
    }
    expect(ServiceIcon::forIssuer(' GitHub '))->toBe('github')
        ->and(ServiceIcon::forIssuer('github.com.evil.test'))->toBeNull()
        ->and(ServiceIcon::forIssuer('../../google'))->toBeNull()
        ->and(ServiceIcon::forIssuer('Custom service'))->toBeNull();
});

it('keeps search results and accessibility text sizes in a scrollable list', function (): void {
    fakeSecureStorage();
    app(TotpStore::class)->merge([testTotpEntry()]);
    Native::test(Codes::class)->call('show', 'grid')->assertSee('Your services')
        ->set('search', 'alice')->assertDontSee('Your services')->assertSee('Copy code for alice@example.test');
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => true, 'foreground' => true, 'generation' => 0, 'largeText' => true]);
    Native::test(Codes::class)->assertSet('largeText', true)->assertDontSee('Your services')
        ->assertDontSee('Grid view')->assertSee('Copy code for alice@example.test')->assertAccessible();
});

it('keeps the TOTP vault and app lock when the last Cbox account is removed', function (): void {
    $storage = fakeSecureStorage();
    $account = anAccount();
    app(TotpStore::class)->merge([testTotpEntry()]);
    $before = $storage[TotpStore::KEY];
    Http::fake(['*' => Http::response([], 200)]);
    $screen = Native::test(Settings::class)->call('requestRemoval', $account->id);
    $screen->emitNative(ButtonPressed::class, ['id' => lastRemovalDialogId(), 'index' => 1, 'label' => 'Remove account']);
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
