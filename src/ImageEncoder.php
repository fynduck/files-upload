<?php

namespace Fynduck\FilesUpload;

use Illuminate\Support\Str;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Encodes images for the web without external binaries.
 *
 * With optimizing on, metadata is stripped (EXIF, GPS, comments; the colour profile is
 * kept) and JPEGs are progressive. The biggest saving is the format itself: WebP or AVIF
 * at quality 75-85 is usually 40-70% smaller than JPEG.
 */
class ImageEncoder
{
    public static function encode(ImageInterface $image, string $extension, int $quality, bool $optimize): string
    {
        return (string) $image->encodeUsingFileExtension(
            $extension,
            quality: $quality,
            progressive: $optimize,
            strip: $optimize ?: null,
        );
    }

    /**
     * Pick the bytes to store for an image that was not resized or changed: the source
     * when it is already smaller than the re-encoded output (already-compressed PNGs grow
     * several times when re-encoded).
     */
    public static function smallest(string $encoded, string $source): string
    {
        return strlen($source) <= strlen($encoded) ? $source : $encoded;
    }

    public static function isSameFormat(string $first, string $second): bool
    {
        return self::normalizeExtension($first) === self::normalizeExtension($second);
    }

    private static function normalizeExtension(string $extension): string
    {
        return match (Str::lower($extension)) {
            'jpeg', 'jpe', 'pjpg', 'pjpeg' => 'jpg',
            'tif'   => 'tiff',
            default => Str::lower($extension),
        };
    }
}
