<?php

namespace Fynduck\FilesUpload\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait GenerateData
{
    /**
     * Generate file name before save
     * @return void
     */
    private function generateNameFile()
    {
        $fileName = $this->name;

        /**
         * extract extension if not defined
         */
        if (!$this->extension) {
            if ($this->is_base64()) {
                $this->decodeBase64();
            } else if ($this->is_uploaded()) {
                $this->setExtension($this->file->getClientOriginalExtension());
            }
        }

        /**
         * extract name if not defined
         */
        if (!$fileName) {
            if ($this->is_uploaded()) {
                $fileName .= '_' . $this->file->getClientOriginalName();
            } else {
                $fileName .= '_' . Str::random();
            }
        }

        $fileName = $this->checkProhibitedSymbols($fileName);

        $path = $this->generatePathToFile($fileName);

        /**
         * Verify if exists file with this name, if exist change file name
         */
        if (Storage::disk($this->disk)->exists($path)) {
            $fileName .= '_' . Str::random(8);
        }

        /**
         * add extension prefix to name
         */
        if ($this->encode) {
            $fileName = $this->extension . '_' . $fileName;
        }

        $this->name = $fileName;
    }

    /**
     * Replace prohibited symbols
     * @param string $name
     * @return string
     */
    private function checkProhibitedSymbols(string $name): string
    {
        $pattern = ['/\s+/', '/,/'];
        $replace = ['_', '_'];

        return Str::slug(preg_replace($pattern, $replace, $name), '_');
    }

    /**
     * @param string $name
     * @return string
     */
    private function generatePathToFile(string $name): string
    {
        $path = '';
        if ($this->folder) {
            $path = $this->folder . '/';
        }

        $path .= $name . '.' . $this->extension;

        return $path;
    }

    /**
     * Set extension and file from base64
     */
    private function decodeBase64()
    {
        [$type, $this->file] = explode(';', $this->file);
        [, $this->file] = explode(',', $this->file);

        if (!$this->extension) {
            $this->setExtension(explode('/', $type)[1]);
        }

        $this->file = base64_decode($this->file);
    }

    /**
     * @return string
     */
    private function getPathFile(): string
    {
        return $this->folder . '/' . $this->getFullName();
    }

    /**
     * @param bool $resize
     * @return string
     */
    private function getFullName(bool $resize = false): string
    {
        $fileExtension = $this->extension;

        if ($resize && $this->encode) {
            $fileExtension = $this->encode;
        }

        if ($this->is_svg()) {
            $fileExtension = 'svg';
        }

        return "$this->name.$fileExtension";
    }
}