<?php

namespace Fynduck\FilesUpload\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait GenerateData
{
    private function generateNameFile(): void
    {
        $fileName = $this->checkProhibitedSymbols($this->name);
        $path = $this->generatePathToFile($fileName);

        if (Storage::disk($this->disk)->exists($path)) {
            $fileName .= '_' . Str::random(8);
        }

        $this->name = $fileName;
    }

    private function checkProhibitedSymbols(string $name): string
    {
        return Str::slug(preg_replace(['/\\s+/', '/,/'], ['_', '_'], $name), '_');
    }

    private function generatePathToFile(string $name): string
    {
        return ($this->folder ? trim($this->folder, '/') . '/' : '') . $name . '.' . $this->encode;
    }

    private function decodeBase64(): void
    {
        [$type, $this->file] = explode(';', $this->file);
        [, $this->file] = explode(',', $this->file);
        $decoded = base64_decode($this->file, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 payload.');
        }

        $this->file = $decoded;
        $this->setEncodeFormat(explode('/', $type)[1]);
    }

    private function getPathFile(): string
    {
        return trim($this->folder . '/' . $this->getFullName(), '/');
    }

    private function getFullName(): string
    {
        return "$this->name.$this->encode";
    }
}
