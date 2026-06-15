<?php

namespace Fynduck\FilesUpload\Data;

/**
 * A single requested size variant.
 *
 * Replaces the loose ['width' => .., 'height' => .., 'position' => ..] arrays.
 * Holds only scalars so it serializes cleanly into a queued job.
 */
class ImageSize
{
    public function __construct(
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly string $position = 'center',
        public readonly ?string $action = null,
    ) {}

    public static function make(
        ?int $width = null,
        ?int $height = null,
        string $position = 'center',
        ?string $action = null,
    ): self {
        return new self($width, $height, $position, $action);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['width']) ? (int) $data['width'] : null,
            isset($data['height']) ? (int) $data['height'] : null,
            $data['position'] ?? 'center',
            $data['action'] ?? null,
        );
    }

    /**
     * Whether this size has at least one dimension to work with.
     */
    public function hasDimensions(): bool
    {
        return (bool) ($this->width || $this->height);
    }

    public function toArray(): array
    {
        return [
            'width'    => $this->width,
            'height'   => $this->height,
            'position' => $this->position,
            'action'   => $this->action,
        ];
    }
}
