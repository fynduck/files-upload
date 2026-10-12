<?php

namespace Fynduck\FilesUpload\Traits;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

trait CheckFile
{
    /**
     * Extensions an image may have been stored with — used to find every old size of a
     * replaced image, whatever format it was encoded to.
     *
     * @var list<string>
     */
    protected $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff', 'heic'];

    private function isUploaded(): bool
    {
        if ($this->file instanceof UploadedFile) {
            return true;
        }

        return is_string($this->file) && (is_uploaded_file($this->file) || is_file($this->file));
    }

    private function isBase64(): bool
    {
        return (bool) preg_match("/data:[a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+;base64,.*/", $this->file);
    }

    private function isSvg(): bool
    {
        if ($this->file instanceof UploadedFile) {
            return $this->file->getClientMimeType() === 'image/svg+xml'
                || strtolower($this->file->getClientOriginalExtension()) === 'svg';
        }

        return is_string($this->file) && is_file($this->file) && mime_content_type($this->file) === 'image/svg+xml';
    }

    public function isSupport(?string $encode = null): bool
    {
        return $this->imageManager()->driver()->supports(($encode ?? $this->encode) ?: '');
    }

    /**
     * Intervention's manager built from config/image.php. Not resolved through the `image`
     * container binding: Laravel 13 registers its own image manager under that name.
     */
    protected function imageManager(): ImageManager
    {
        $driver = match (config('image.driver')) {
            null, '', 'gd' => GdDriver::class,
            'imagick' => ImagickDriver::class,
            default   => config('image.driver'),
        };

        return new ImageManager(
            $driver,
            autoOrientation: (bool) config('image.options.autoOrientation', true),
            decodeAnimation: (bool) config('image.options.decodeAnimation', true),
            blendingColor: config('image.options.blendingColor', 'ffffff'),
            strip: (bool) config('image.options.strip', false),
        );
    }
}
