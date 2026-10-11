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
        return ($this->folder ? trim($this->folder, '/') . '/' : '') . $name . '.' . $this->extension();
    }

    /**
     * Decode a data URI payload into raw bytes; its media type gives the source format.
     */
    private function decodeBase64(): void
    {
        [$type, $payload] = explode(';', $this->file, 2);
        [, $payload] = explode(',', $payload, 2);
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 payload.');
        }

        $subtype = Str::lower(explode('/', $type)[1] ?? '');

        $this->file = $decoded;
        $this->sourceExtension = $subtype === 'svg+xml' ? 'svg' : $subtype;
    }

    private function getPathFile(): string
    {
        return trim($this->folder . '/' . $this->getFullName(), '/');
    }

    private function getFullName(): string
    {
        return "$this->name.{$this->extension()}";
    }
}
