<?php

namespace Fynduck\FilesUpload;

use Intervention\Image\Laravel\Facades\Image;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * What this server can do with images: the image driver and the formats it can write,
 * and — when spatie/laravel-image-optimizer is installed — which optimizer binaries it
 * finds. Built-in optimizing (setOptimize) needs none of the binaries.
 */
class ServerCheck
{
    /**
     * Formats files-upload can encode to.
     *
     * @var list<string>
     */
    public const FORMATS = ['jpg', 'png', 'gif', 'webp', 'avif'];

    /**
     * Binaries spatie/image-optimizer runs, with the files they handle and how to install them.
     *
     * @var array<string, array{handles: string, install: string}>
     */
    public const BINARIES = [
        'jpegoptim' => ['handles' => 'jpg', 'install' => 'apt install jpegoptim'],
        'optipng'   => ['handles' => 'png', 'install' => 'apt install optipng'],
        'pngquant'  => ['handles' => 'png', 'install' => 'apt install pngquant'],
        'gifsicle'  => ['handles' => 'gif', 'install' => 'apt install gifsicle'],
        'cwebp'     => ['handles' => 'webp', 'install' => 'apt install webp'],
        'avifenc'   => ['handles' => 'avif', 'install' => 'apt install libavif-bin'],
        'svgo'      => ['handles' => 'svg', 'install' => 'npm install -g svgo'],
    ];

    /**
     * @return array{name: string, version: string}|null null when the driver cannot be loaded
     */
    public function driver(): ?array
    {
        try {
            $driver = Image::getFacadeRoot()->driver;

            return ['name' => $driver->id(), 'version' => $driver->version()];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, bool> format => whether the driver can write it
     */
    public function formats(): array
    {
        $formats = [];
        foreach (self::FORMATS as $format) {
            try {
                $formats[$format] = Image::getFacadeRoot()->driver->supports($format);
            } catch (Throwable) {
                $formats[$format] = false;
            }
        }

        return $formats;
    }

    public function hasOptimizerPackage(): bool
    {
        return class_exists(ImageOptimizer::class);
    }

    /**
     * @return array<string, string|null> binary => its path, or null when missing
     */
    public function binaries(): array
    {
        $finder = new ExecutableFinder();
        $binaryPath = config('image-optimizer.binary_path');
        $extraDirectories = is_string($binaryPath) && $binaryPath !== '' ? [rtrim($binaryPath, '/')] : [];

        $binaries = [];
        foreach (array_keys(self::BINARIES) as $binary) {
            $binaries[$binary] = $finder->find($binary, null, $extraDirectories);
        }

        return $binaries;
    }

    /**
     * Problems that matter for the configured setup: an unloadable driver, and — when
     * optimizing with the spatie package — missing binaries.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        if ($this->driver() === null) {
            return ['The image driver cannot be loaded; check intervention-image.driver and the gd/imagick extension.'];
        }

        if (! config('files-upload.optimize') || ! $this->hasOptimizerPackage()) {
            return [];
        }

        $missingBinaries = array_keys(array_filter($this->binaries(), static fn (?string $path): bool => $path === null));
        if ($missingBinaries === []) {
            return [];
        }

        return ['Optimizer binaries missing: ' . implode(', ', $missingBinaries) . '. Install them, or remove spatie/laravel-image-optimizer to rely on built-in optimizing only.'];
    }

    public function binaryHint(string $binary): string
    {
        $details = self::BINARIES[$binary];

        return "optimizes {$details['handles']}; install: {$details['install']}";
    }
}
