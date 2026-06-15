<?php

namespace Fynduck\FilesUpload\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once every requested size variant has been generated.
 *
 * In per-size (batch) mode it is dispatched from the batch then() callback; in
 * single-job mode it is dispatched at the end of the job. Apps can listen to mark a
 * record as "variants ready", warm a CDN, etc.
 */
class ImageSizesGenerated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<int, string>  $sizes  The size folder keys that were generated.
     */
    public function __construct(
        public readonly string $disk,
        public readonly string $folder,
        public readonly string $name,
        public readonly array $sizes,
        public readonly string $sourcePath,
    ) {}
}
