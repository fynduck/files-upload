<?php

namespace Fynduck\FilesUpload\Traits;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Laravel\Facades\Image;

trait CheckFile
{
    private function isUploaded(): bool
    {
        if ($this->file instanceof UploadedFile) {
            return true;
        }

        return is_string($this->file) && (is_uploaded_file($this->file) || is_file($this->file));
    }

    private function isBase64(): bool
    {
        return (bool)preg_match("/data:[a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+;base64,.*/", $this->file);
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
        return Image::withDriver(config('image.driver'))->driver()->supports(($encode ?? $this->encode) ?: '');
    }
}
