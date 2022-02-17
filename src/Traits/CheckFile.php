<?php

namespace Fynduck\FilesUpload\Traits;

trait CheckFile
{
    /**
     * Check if file is uploaded from form
     * @return bool
     */
    private function is_uploaded(): bool
    {
        return is_uploaded_file($this->file);
    }

    /**
     * Check if file is base64
     * @return bool
     */
    private function is_base64(): bool
    {
        return (bool)preg_match("/data:([a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+).base64,.*/", $this->file);
    }

    /**
     * Check if file is svg
     * @return bool
     */
    private function is_svg(): bool
    {
        if (strpos($this->extension, 'svg') !== false) {
            return true;
        }

        return false;
    }
}