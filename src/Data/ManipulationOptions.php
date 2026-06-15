<?php

namespace Fynduck\FilesUpload\Data;

use Illuminate\Support\Str;

/**
 * Shared effect/encode settings used by both UploadFile and ManipulationImage.
 *
 * This is the single home for clamping/normalisation (blur, brightness, quality),
 * removing the duplicated forwarding setters and the previous setBlur drift between
 * the two classes. Holds only scalars so it serialises cleanly into a queued job.
 */
class ManipulationOptions
{
    public function __construct(
        public string $disk = 'public',
        public string $folder = '',
        public string $name = '',
        public ?string $overwrite = null,
        public ?string $background = null,
        public ?int $blur = null,
        public ?int $brightness = null,
        public bool $greyscale = false,
        public bool $optimize = true,
        public ?string $encode = null,
        public int $quality = 90,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            $data['disk'] ?? 'public',
            $data['folder'] ?? '',
            $data['name'] ?? '',
            $data['overwrite'] ?? null,
            $data['background'] ?? null,
            self::normalizeBlur($data['blur'] ?? null),
            self::normalizeBrightness($data['brightness'] ?? null),
            (bool) ($data['greyscale'] ?? false),
            (bool) ($data['optimize'] ?? true),
            self::normalizeEncode($data['encode'] ?? null),
            self::normalizeQuality($data['quality'] ?? 90),
        );
    }

    public static function normalizeBlur(?int $blur): ?int
    {
        if ($blur === null || $blur < 0) {
            return null;
        }

        return min($blur, 100);
    }

    public static function normalizeBrightness(?int $brightness): ?int
    {
        if ($brightness === null) {
            return null;
        }

        return ($brightness >= -100 && $brightness <= 100) ? $brightness : null;
    }

    public static function normalizeQuality(?int $quality): int
    {
        if ($quality === null || $quality < 0) {
            return 90;
        }

        return min($quality, 100);
    }

    public static function normalizeEncode(?string $encode): ?string
    {
        return $encode ? Str::lower($encode) : null;
    }

    public function toArray(): array
    {
        return [
            'disk'       => $this->disk,
            'folder'     => $this->folder,
            'name'       => $this->name,
            'overwrite'  => $this->overwrite,
            'background' => $this->background,
            'blur'       => $this->blur,
            'brightness' => $this->brightness,
            'greyscale'  => $this->greyscale,
            'optimize'   => $this->optimize,
            'encode'     => $this->encode,
            'quality'    => $this->quality,
        ];
    }
}
