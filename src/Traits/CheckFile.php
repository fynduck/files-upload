<?php

namespace Fynduck\FilesUpload\Traits;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

trait CheckFile
{
    /**
     * Extensions an image may have been stored with — used to find every old variant
     * of a replaced image, whatever format it was encoded to.
     *
     * @var list<string>
     */
    protected array $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'tiff', 'heic'];

    private function isUploaded(): bool
    {
        if ($this->file instanceof UploadedFile) {
            return true;
        }

        return is_string($this->file)
            && ! str_contains($this->file, "\0")
            && (is_uploaded_file($this->file) || is_file($this->file));
    }

    private function isBase64(): bool
    {
        return is_string($this->file)
            && (bool) preg_match("/^data:[a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+;base64,.*/s", $this->file);
    }

    private function isSvg(): bool
    {
        if ($this->file instanceof UploadedFile) {
            return $this->file->getClientMimeType() === 'image/svg+xml'
                || strtolower($this->file->getClientOriginalExtension()) === 'svg';
        }

        return is_string($this->file) && is_file($this->file) && mime_content_type($this->file) === 'image/svg+xml';
    }

    /**
     * Whether the configured image driver can read and write this format (an extension
     * or a media type). Without an argument, checks the requested encode format.
     */
    public function isSupport(?string $format = null): bool
    {
        $format = strtolower(($format ?? $this->encode) ?: '');

        if ($format === '' || in_array($format, ['svg', 'image/svg+xml'], true)) {
            return false;
        }

        try {
            return Image::getFacadeRoot()->driver->supports($format);
        } catch (Throwable) {
            return false;
        }
    }
}
