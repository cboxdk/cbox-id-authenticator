<?php

declare(strict_types=1);

namespace App\Console\Commands;

use GdImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Generates the app icon and splash screen from the SVGs in resources/brand.
 *
 * Committed as a command rather than as two hand-exported PNGs so the marks stay
 * editable: change the SVG, re-run this, and the binaries follow. It also puts the
 * store's constraints somewhere they can be read and asserted rather than remembered.
 *
 * WHAT THE STORE ACTUALLY REJECTS
 * -------------------------------
 * An app icon with an ALPHA CHANNEL is refused outright, and every SVG renderer
 * produces one — so flattening is not tidiness, it is the difference between a build
 * that uploads and one that does not. The icon must also be square and at least
 * 1024x1024, which is what NativePHP's own validateIosIcon() checks before copying it
 * into the asset catalog; failing it there is silent.
 *
 * Corners are left square. iOS applies its own mask, and a pre-rounded icon shows a
 * seam against it.
 *
 * THE SPLASH IS CROPPED, NOT FITTED
 * ---------------------------------
 * SplashView draws it with `.aspectRatio(contentMode: .fill)` and clips. A square image
 * would therefore be scaled until it covered the tallest dimension and lose its sides
 * on every phone. A portrait canvas close to a real handset's ratio, with the mark small
 * and centred, survives any crop.
 */
class BuildBrandAssets extends Command
{
    protected $signature = 'cboxid:brand';

    protected $description = 'Generate public/icon.png and public/splash.png from resources/brand';

    /** The store's minimum, and what NativePHP validates against. */
    private const ICON_SIZE = 1024;

    /**
     * Close to an iPhone 15 Pro Max, so `.fill` crops almost nothing on the tallest
     * handsets and only the sides on the widest.
     */
    private const SPLASH_WIDTH = 1290;

    private const SPLASH_HEIGHT = 2796;

    /**
     * The mark's box, not the shield's width.
     *
     * The SVG carries its own margin, so the shield ends up around 55% of this — 640
     * puts it near a third of the canvas width, which reads as a logo rather than
     * something dropped in the middle. Still small enough that `.fill` cropping the
     * sides on a wide handset never touches it.
     */
    private const SPLASH_MARK = 640;

    public function handle(): int
    {
        try {
            $this->buildIcon();
            $this->buildSplash('splash.png', '#FFFFFF');
            // The dark variant the asset catalog picks up under a dark appearance.
            $this->buildSplash('splash-dark.png', '#0B1220');
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function buildIcon(): void
    {
        $rendered = $this->render('icon.svg', self::ICON_SIZE);

        // Flattened onto an opaque canvas. The source is fully covered by its own
        // background rect, so the colour underneath never shows — it exists to drop the
        // alpha channel, which is the part the store refuses.
        $canvas = $this->canvas(self::ICON_SIZE, self::ICON_SIZE, '#0850AB');
        imagecopy($canvas, $rendered, 0, 0, 0, 0, imagesx($rendered), imagesy($rendered));

        $this->write($canvas, public_path('icon.png'));
        imagedestroy($rendered);

        $this->components->info('icon.png — '.self::ICON_SIZE.'×'.self::ICON_SIZE.', opaque');
    }

    private function buildSplash(string $filename, string $background): void
    {
        $mark = $this->render('mark.svg', self::SPLASH_MARK);
        $canvas = $this->canvas(self::SPLASH_WIDTH, self::SPLASH_HEIGHT, $background);

        // Centred, and centred vertically rather than optically raised: the image is
        // cropped from the edges, so anything off-centre drifts further off on a
        // handset with a different ratio.
        imagecopy(
            $canvas,
            $mark,
            (int) ((self::SPLASH_WIDTH - self::SPLASH_MARK) / 2),
            (int) ((self::SPLASH_HEIGHT - self::SPLASH_MARK) / 2),
            0,
            0,
            self::SPLASH_MARK,
            self::SPLASH_MARK,
        );

        $this->write($canvas, public_path($filename));
        imagedestroy($mark);

        $this->components->info($filename.' — '.self::SPLASH_WIDTH.'×'.self::SPLASH_HEIGHT.' on '.$background);
    }

    /**
     * Rasterise an SVG at a given edge length.
     *
     * qlmanage is macOS's own Quick Look renderer — no ImageMagick or librsvg to
     * install, and the same engine Finder previews with. It names its output after the
     * source, hence the fixed filename below.
     */
    private function render(string $svg, int $size): GdImage
    {
        $source = resource_path('brand/'.$svg);

        if (! File::exists($source)) {
            throw new RuntimeException("Missing {$source}.");
        }

        $temp = storage_path('app/brand-render');
        File::ensureDirectoryExists($temp);

        Process::run(['qlmanage', '-t', '-s', (string) $size, '-o', $temp, $source]);

        $rendered = $temp.'/'.$svg.'.png';

        if (! File::exists($rendered)) {
            throw new RuntimeException("qlmanage produced nothing for {$svg}.");
        }

        $image = @imagecreatefrompng($rendered);
        File::deleteDirectory($temp);

        if (! $image instanceof GdImage) {
            throw new RuntimeException("Could not read the render of {$svg}.");
        }

        // qlmanage honours the requested edge, but a non-square source would come back
        // fitted rather than filled. Both marks are square; assert it rather than
        // silently producing an icon the store will reject for its aspect ratio.
        if (imagesx($image) !== imagesy($image)) {
            throw new RuntimeException("{$svg} did not render square.");
        }

        return $image;
    }

    private function canvas(int $width, int $height, string $hex): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('Could not allocate a canvas.');
        }

        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%2x%2x%2x');

        imagefill($canvas, 0, 0, imagecolorallocate($canvas, (int) $r, (int) $g, (int) $b));

        return $canvas;
    }

    private function write(GdImage $image, string $path): void
    {
        // The whole point: alpha is NOT saved. imagecreatetruecolor() starts opaque, and
        // leaving this on would write an alpha channel anyway.
        imagesavealpha($image, false);

        File::ensureDirectoryExists(dirname($path));
        imagepng($image, $path);
        imagedestroy($image);
    }
}
