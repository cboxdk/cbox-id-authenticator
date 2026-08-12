<?php

declare(strict_types=1);

/**
 * The constraints that decide whether a build uploads at all.
 *
 * Both failures are silent. App Store Connect refuses an icon with an alpha channel
 * after the archive is built, and NativePHP's own validateIosIcon() just declines to
 * copy an icon that is not square or under 1024 — leaving the placeholder in the asset
 * catalog with nothing on screen to say so.
 *
 * Asserted on the committed PNGs rather than on the generator, because a hand-replaced
 * file is exactly as likely as a regenerated one.
 */

/**
 * The IHDR colour type: 6 is RGBA, 4 is grey+alpha. Read from the header rather than
 * through GD, which reports what it would render, not what the file carries.
 */
function pngHasAlpha(string $path): bool
{
    $header = (string) file_get_contents($path, length: 26);

    return in_array(ord($header[25] ?? "\0"), [4, 6], true);
}

it('ships an app icon the store will accept', function (): void {
    $icon = public_path('icon.png');

    expect($icon)->toBeFile();

    [$width, $height] = getimagesize($icon);

    expect($width)->toBe($height)
        ->and($width)->toBeGreaterThanOrEqual(1024)
        // The one that gets an otherwise finished build rejected.
        ->and(pngHasAlpha($icon))->toBeFalse();
});

it('ships a splash that survives being cropped to fill', function (): void {
    foreach (['splash.png', 'splash-dark.png'] as $name) {
        $path = public_path($name);

        expect($path)->toBeFile();

        [$width, $height] = getimagesize($path);

        // Portrait, and close to a handset's ratio. SplashView scales to FILL and clips,
        // so a square or landscape source would be blown up and lose its sides.
        expect($height)->toBeGreaterThan($width)
            ->and($height / $width)->toBeGreaterThan(1.8)
            ->and(pngHasAlpha($path))->toBeFalse();
    }
});

it('keeps the sources the assets are generated from', function (): void {
    // Losing these leaves two PNGs nobody can edit.
    expect(resource_path('brand/icon.svg'))->toBeFile()
        ->and(resource_path('brand/mark.svg'))->toBeFile();
});
