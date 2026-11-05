<?php

declare(strict_types=1);

use App\NativeComponents\About;
use App\NativeComponents\Settings;
use App\Services\DeviceSecurity;
use App\Support\AppLanguages;
use Illuminate\Support\Facades\File;
use Native\Mobile\Testing\Native;

it('matches the Cbox ID language catalogue and preserves translation parameters', function (): void {
    $locales = ['en', 'da', 'de', 'sv', 'nb', 'fr', 'fi', 'es', 'it', 'nl'];
    expect(array_keys(AppLanguages::NAMES))->toBe($locales);
    expect(config('nativephp.supported_locales'))->toBe($locales);
    expect(array_keys(config('nativephp.permission_localizations')))->toBe($locales);
    $english = json_decode(File::get(lang_path('en.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach ($locales as $locale) {
        $strings = json_decode(File::get(lang_path($locale.'.json')), true, flags: JSON_THROW_ON_ERROR);
        expect(array_keys($strings))->toBe(array_keys($english), $locale);
        foreach ($strings as $key => $text) {
            expect($text)->toBeString()->not->toBeEmpty();
            preg_match_all('/(?<![a-zA-Z]):[a-z_]+/', $key, $expected);
            preg_match_all('/(?<![a-zA-Z]):[a-z_]+/', $text, $actual);
            sort($expected[0]);
            sort($actual[0]);
            expect($actual[0])->toBe($expected[0], $locale.': '.$key);
        }
        foreach (config('nativephp.permissions') as $key => $englishText) {
            expect(config('nativephp.permission_localizations.'.$locale.'.'.$key))->toBe($strings[$englishText]);
        }
    }
});

it('uses the selected iOS language and safely handles region variants', function (mixed $value, string $expected): void {
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['locale' => $value]);
    expect(app(DeviceSecurity::class)->locale())->toBe($expected);
})->with([
    ['da-DK', 'da'], ['de-DE', 'de'], ['sv-SE', 'sv'], ['nb-NO', 'nb'], ['no_NO', 'nb'],
    ['fr-CA', 'fr'], ['fi_FI', 'fi'], ['es-MX', 'es'], ['it-IT', 'it'], ['nl-BE', 'nl'],
    ['en-GB', 'en'], ['DA-dk', 'da'], ['ja-JP', 'en'], ['', 'en'], [null, 'en'], [42, 'en'],
]);

it('renders translated help topics and opens the system language settings', function (string $locale): void {
    fakeSecureStorage();
    app()->setLocale($locale);
    Native::test(About::class)
        ->assertSee(__('Codes not working?'))
        ->assertDontSee(__('Check that automatic date and time is enabled in iPhone Settings. Use the newest code before its timer runs out.'))
        ->call('toggle', 'codes')
        ->assertSee(__('Check that automatic date and time is enabled in iPhone Settings. Use the newest code before its timer runs out.'))
        ->call('toggle', 'codes')->assertSet('expanded', null)
        ->assertAccessible();
    Native::test(Settings::class)->assertSee(AppLanguages::NAMES[$locale])->call('languageSettings');
    Native::fakeBridge()->assertCalled('System.OpenAppSettings');
})->with(array_keys(AppLanguages::NAMES));
