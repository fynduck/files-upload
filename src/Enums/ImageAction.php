<?php

namespace Fynduck\FilesUpload\Enums;

use InvalidArgumentException;

/**
 * How a size is produced from the source image.
 */
enum ImageAction: string
{
    /**
     * Scale to fit inside the width and height, keeping the source ratio.
     */
    case Resize = 'resize';

    /**
     * Cut the exact width and height ratio (around the focal point when one is set).
     */
    case Crop = 'crop';

    /**
     * The action itself, or the action named by a string ('resize', 'crop').
     */
    public static function resolve(self|string $action): self
    {
        return $action instanceof self
            ? $action
            : self::tryFrom($action) ?? throw new InvalidArgumentException('Action does not exist.');
    }
}
