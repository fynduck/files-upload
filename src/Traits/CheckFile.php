<?php

namespace Fynduck\FilesUpload\Traits;

trait CheckFile
{
    private function is_uploaded(): bool
    {
        return is_uploaded_file($this->file) || is_file($this->file);
    }

    private function is_base64(): bool
    {
        return (bool)preg_match("/data:[a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+;base64,.*/", $this->file);
    }

    private function is_svg(): bool
    {
        return str_contains($this->extension, 'svg');
    }
}