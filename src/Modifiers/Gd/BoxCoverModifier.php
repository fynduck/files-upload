<?php

namespace Fynduck\FilesUpload\Modifiers\Gd;

use Intervention\Image\Drivers\Gd\Modifiers\CoverModifier;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\SizeInterface;

/**
 * GD's cover with a box placed by the caller (around a focal point) instead of
 * one placed by an alignment: the box is resampled straight into a canvas of the
 * target size, so no full-resolution crop sits in memory next to the decoded
 * source, and the pixels come out the same as a crop followed by a resize.
 */
class BoxCoverModifier extends CoverModifier
{
    public function __construct(int $width, int $height, private readonly SizeInterface $box)
    {
        parent::__construct($width, $height);
    }

    protected function cropSize(ImageInterface $image): SizeInterface
    {
        return $this->box;
    }

    /**
     * Intervention accepts a specialized modifier only from the driver's own
     * namespace; this one belongs to GD through its parent.
     */
    protected function belongsToDriver(object $driver): bool
    {
        return str_starts_with(
            (string) get_parent_class($this),
            substr($driver::class, 0, (int) strrpos($driver::class, '\\')),
        );
    }
}
