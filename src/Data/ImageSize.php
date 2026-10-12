<?php

namespace Fynduck\FilesUpload\Data;

use Fynduck\FilesUpload\Enums\ImageAction;

/**
 * A single requested size variant.
 *
 * Replaces the loose ['width' => .., 'height' => .., 'position' => ..] arrays.
 * Holds only scalars and an enum so it serializes cleanly into a queued job.
 */
class ImageSize
{
    /**
     * Action for this size only; null uses the action passed to save().
     */
    public readonly ?ImageAction $action;

    public function __construct(
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly string $position = 'center',
        ImageAction|string|null $action = null,
    ) {
        $this->action = $action === null ? null : ImageAction::resolve($action);
    }

    public static function make(
        ?int $width = null,
        ?int $height = null,
        string $position = 'center',
        ImageAction|string|null $action = null,
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
            'action'   => $this->action?->value,
        ];
    }
}
