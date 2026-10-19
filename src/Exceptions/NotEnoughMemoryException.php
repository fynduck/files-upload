<?php

namespace Fynduck\FilesUpload\Exceptions;

use RuntimeException;

/**
 * The image cannot be decoded within what is left of PHP's memory_limit. Thrown
 * before decoding: running out of memory there is a fatal error that kills the
 * process (a queue worker reports only that the job was attempted too many times).
 * Retrying does not help; raise memory_limit or use the vips driver.
 */
class NotEnoughMemoryException extends RuntimeException
{
    public static function forImage(int $width, int $height, int $neededBytes, int $freeBytes, string $memoryLimit): self
    {
        return new self(sprintf(
            'Decoding the %dx%d image with GD needs about %d MB, but only %d MB of the %s memory_limit is free. Raise memory_limit or use the vips driver.',
            $width,
            $height,
            (int) ceil($neededBytes / 1048576),
            intdiv(max(0, $freeBytes), 1048576),
            $memoryLimit,
        ));
    }
}
