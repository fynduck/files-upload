<?php

namespace Fynduck\FilesUpload\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait GenerateData
{
    private function generateNameFile(): void
    {
        if (!$this->extension) {
            $this->is_base64() ? $this->decodeBase64() : $this->setExtension($this->file->getClientOriginalExtension());
        }

        $fileName = $this->name ?: ($this->is_base64() || $this->is_svg() ? Str::random() : $this->file->getClientOriginalName());
        $fileName = $this->checkProhibitedSymbols($fileName);
        $path = $this->generatePathToFile($fileName);

        if (Storage::disk($this->disk)->exists($path)) {
            $fileName .= '_' . Str::random(8);
        }

        $this->name = $this->encode ? $fileName . '_' . $this->extension : $fileName;
    }

    private function checkProhibitedSymbols(string $name): string
    {
        return Str::slug(preg_replace(['/\\s+/', '/,/'], ['_', '_'], $name), '_');
    }

    private function generatePathToFile(string $name): string
    {
        return ($this->folder ? $this->folder . '/' : '') . $name . '.' . $this->extension;
    }

    private function decodeBase64(): void
    {
        [$type, $this->file] = explode(';', $this->file);
        [, $this->file] = explode(',', $this->file);
        $this->file = base64_decode($this->file);
        $this->setExtension(explode('/', $type)[1]);
    }

    private function getPathFile(): string
    {
        return $this->folder . '/' . $this->getFullName();
    }

    private function getFullName(): string
    {
        $fileExtension = !$this->is_svg() && $this->encode ? $this->encode : $this->extension;

        return "$this->name.$fileExtension";
    }
}