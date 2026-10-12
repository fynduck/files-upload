<?php

namespace Fynduck\FilesUpload;

use Fynduck\FilesUpload\Data\ImageSize;
use Fynduck\FilesUpload\Data\ManipulationOptions;
use Fynduck\FilesUpload\Enums\ImageAction;
use Fynduck\FilesUpload\Events\ImageSizesGenerated;
use Fynduck\FilesUpload\Jobs\GenerateImageSizesJob;
use Fynduck\FilesUpload\Traits\CheckFile;
use Fynduck\FilesUpload\Traits\GenerateData;
use Illuminate\Bus\Batch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;
use Throwable;

class UploadFile
{
    use CheckFile;
    use GenerateData;

    protected UploadedFile|string $file;

    protected string $name;

    protected ?string $overwrite = null;

    protected string $disk = 'public';

    protected string $folder;

    protected array $sizes = [];

    protected ?string $background = null;

    protected ?int $blur = null;

    protected ?int $brightness = null;

    protected ?bool $greyscale = false;

    protected ?bool $optimize = true;

    /**
     * Format chosen by the caller; null keeps the uploaded format. When set, the
     * original and every size variant are re-encoded to it and share its extension.
     */
    protected ?string $encode = null;

    /**
     * Format of the input itself (client extension, file extension or data URI type).
     */
    protected ?string $sourceExtension = null;

    protected ?int $quality = 90;

    protected ?float $focalX = null;

    protected ?float $focalY = null;

    protected bool $upscale = true;

    protected ?bool $queue = null;

    protected ?string $queueConnection = null;

    protected ?string $queueName = null;

    protected ?bool $queuePerSize = null;

    public static function file($file): self
    {
        return new static($file);
    }

    public function __construct($file)
    {
        $this->file = $file;
        $this->disk = config('files-upload.disk', $this->disk);
        $this->optimize = config('files-upload.optimize', $this->optimize);
        $this->quality = config('files-upload.quality', $this->quality);
    }

    public function setDisk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public function setFolder(string $folder): self
    {
        $this->folder = trim($folder, '/');

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    /**
     * @deprecated Use setEncodeFormat() instead.
     */
    public function setExtension(?string $extension = null): self
    {
        return $this->setEncodeFormat($extension);
    }

    /**
     * Name of the image this upload replaces (e.g. the model's current `image` value).
     *
     * Once the new file and its sizes are stored, the old original and the old file in
     * every size folder (whatever extension it was encoded to) are deleted. The new file
     * never reuses a name that is still on disk, so it gets a fresh name and URL.
     */
    public function setOverwrite(?string $overwrite): self
    {
        $this->overwrite = $overwrite;

        return $this;
    }

    public function setSizes(array $sizes): self
    {
        $this->sizes = $sizes;

        return $this;
    }

    /**
     * Colour that fills the padding of `pad` sizes and the transparent areas of images
     * encoded to a format without alpha (jpg). Null keeps the driver default (white).
     */
    public function setBackground(?string $background): self
    {
        $this->background = ManipulationOptions::normalizeBackground($background);

        return $this;
    }

    public function setBlur(?int $blur = 1): self
    {
        $this->blur = ManipulationOptions::normalizeBlur($blur);

        return $this;
    }

    public function setBrightness(?int $brightness): self
    {
        $this->brightness = ManipulationOptions::normalizeBrightness($brightness);

        return $this;
    }

    public function setGreyscale(bool $greyscale = false): self
    {
        $this->greyscale = $greyscale;

        return $this;
    }

    public function setOptimize(bool $optimize = true): self
    {
        $this->optimize = $optimize;

        return $this;
    }

    /**
     * Re-encode the original and every size to this format (jpg, png, gif, webp, avif…).
     * Null, or a format the image driver cannot write, keeps the uploaded format.
     */
    public function setEncodeFormat(?string $encode = null): self
    {
        $candidate = ManipulationOptions::normalizeEncode($encode);

        $this->encode = $candidate && $this->isSupport($candidate) ? $candidate : null;

        return $this;
    }

    public function setEncodeQuality(?int $quality = 90): self
    {
        $this->quality = ManipulationOptions::normalizeQuality($quality);

        return $this;
    }

    /**
     * Crop sizes around this point instead of the size's position. Coordinates are
     * fractions of the image (0,0 = top-left, 1,1 = bottom-right); null clears it.
     */
    public function setFocalPoint(int|float|null $x, int|float|null $y): self
    {
        $this->focalX = ManipulationOptions::normalizeFocal($x);
        $this->focalY = ManipulationOptions::normalizeFocal($y);

        return $this;
    }

    /**
     * Whether sizes larger than the source may be enlarged (default) or are capped at
     * the source dimensions.
     */
    public function setUpscale(bool $upscale = true): self
    {
        $this->upscale = $upscale;

        return $this;
    }

    /**
     * Generate the size variants in the background (queued) instead of on the request
     * thread. Null arguments fall back to the package config.
     */
    public function queue(?string $connection = null, ?string $queue = null, ?bool $perSize = null): self
    {
        $this->queue = true;
        $this->queueConnection = $connection;
        $this->queueName = $queue;
        $this->queuePerSize = $perSize;

        return $this;
    }

    /**
     * Store the file and its sizes; returns the stored file name (name + extension).
     *
     * Raster images get sizes and may be re-encoded. Anything else (SVG, video, PDF…)
     * is stored as uploaded and its sizes are skipped.
     */
    public function save(ImageAction|string $action = ImageAction::Resize): string
    {
        $action = ImageAction::resolve($action);

        if (! isset($this->folder)) {
            $this->folder = '';
        }

        if (! isset($this->name) || $this->name === '') {
            throw new \InvalidArgumentException('Filename is required.');
        }

        $this->resolveSource();

        if (! $this->extension()) {
            throw new \InvalidArgumentException('Unsupported file format.');
        }

        $isRasterImage = $this->isRasterImage();
        if (! $isRasterImage) {
            $this->encode = null;
        }

        $this->generateNameFile();
        $this->storeOriginal($isRasterImage);

        try {
            if ($this->sizes && $isRasterImage) {
                $this->processSizes($action);
            }

            $this->optimize($isRasterImage);
        } catch (Throwable $exception) {
            Storage::disk($this->disk)->delete($this->writtenPaths());

            throw $exception;
        }

        $this->deleteReplaced();

        return $this->getFullName();
    }

    /**
     * File extension the stored original and its sizes get.
     */
    private function extension(): ?string
    {
        return $this->encode ?? $this->sourceExtension;
    }

    private function resolveSource(): void
    {
        if ($this->isBase64()) {
            $this->decodeBase64();

            return;
        }

        if ($this->file instanceof UploadedFile) {
            $this->sourceExtension = Str::lower($this->file->getClientOriginalExtension() ?: (string) $this->file->guessExtension()) ?: null;

            return;
        }

        if ($this->isUploaded()) {
            $this->sourceExtension = Str::lower(pathinfo($this->file, PATHINFO_EXTENSION)) ?: null;

            return;
        }

        throw new \InvalidArgumentException('Unsupported file format.');
    }

    private function isRasterImage(): bool
    {
        return $this->sourceExtension !== 'svg' && ! $this->isSvg() && $this->isSupport($this->sourceExtension);
    }

    private function storeOriginal(bool $isRasterImage): void
    {
        $storage = Storage::disk($this->disk);

        if ($isRasterImage && $this->encode && ! ImageEncoder::isSameFormat($this->encode, (string) $this->sourceExtension)) {
            $image = is_string($this->file) && ! $this->isUploaded()
                ? Image::decodeBinary($this->file)
                : Image::decodePath($this->file instanceof UploadedFile ? $this->file->getRealPath() : $this->file);

            $storage->put(
                $this->getPathFile(),
                ImageEncoder::encode($image, $this->encode, (int) $this->quality, (bool) $this->optimize, $this->background),
            );

            return;
        }

        if (is_string($this->file) && ! $this->isUploaded()) {
            $storage->put($this->getPathFile(), $this->file);

            return;
        }

        $storage->putFileAs($this->folder, $this->file, $this->getFullName());
    }

    /**
     * Render the requested sizes, either inline or via the queue.
     */
    private function processSizes(ImageAction $action): void
    {
        $options = $this->buildOptions();
        $sizes = $this->normalizedSizes();

        if (! $sizes) {
            return;
        }

        if ($this->queueEnabled()) {
            $this->dispatchSizes($action, $options, $sizes);

            return;
        }

        ManipulationImage::load($this->getPathFile())
            ->withOptions($options)
            ->setSizes($sizes)
            ->save($action);
    }

    private function dispatchSizes(ImageAction $action, ManipulationOptions $options, array $sizes): void
    {
        $sourcePath = $this->getPathFile();
        $connection = $this->queueConnection ?? config('files-upload.queue.connection');
        $queue = $this->queueName ?? config('files-upload.queue.queue');
        $perSize = $this->queuePerSize ?? config('files-upload.queue.per_size', true);

        if (! $perSize) {
            $pending = GenerateImageSizesJob::dispatch($sourcePath, $action, $options, $sizes, dispatchEvent: true);

            if ($connection) {
                $pending->onConnection($connection);
            }
            if ($queue) {
                $pending->onQueue($queue);
            }

            return;
        }

        $jobs = [];
        foreach ($sizes as $folder => $size) {
            $jobs[] = new GenerateImageSizesJob($sourcePath, $action, $options, [$folder => $size]);
        }

        $batch = Bus::batch($jobs)
            ->then(static function (Batch $batch) use ($options, $sizes, $sourcePath): void {
                ImageSizesGenerated::dispatch(
                    $options->disk,
                    $options->folder,
                    $options->name,
                    array_keys($sizes),
                    $sourcePath,
                );
            });

        if ($connection) {
            $batch->onConnection($connection);
        }
        if ($queue) {
            $batch->onQueue($queue);
        }

        $batch->dispatch();
    }

    private function queueEnabled(): bool
    {
        return $this->queue ?? (bool) config('files-upload.queue.enabled', false);
    }

    /**
     * @return array<string, ImageSize>
     */
    private function normalizedSizes(): array
    {
        return array_map(static fn ($size) => $size instanceof ImageSize ? $size : ImageSize::fromArray($size), $this->sizes);
    }

    private function buildOptions(): ManipulationOptions
    {
        return new ManipulationOptions(
            disk: $this->disk,
            folder: $this->folder,
            name: $this->name,
            background: $this->background,
            blur: $this->blur,
            brightness: $this->brightness,
            greyscale: (bool) $this->greyscale,
            optimize: (bool) $this->optimize,
            encode: $this->encode,
            quality: (int) $this->quality,
            focalX: $this->focalX,
            focalY: $this->focalY,
            upscale: $this->upscale,
        );
    }

    private function optimize(bool $isRasterImage): void
    {
        if (! $this->optimize || $this->sizes || ! $isRasterImage || ! class_exists(ImageOptimizer::class)) {
            return;
        }

        $absolutePath = Storage::disk($this->disk)->path($this->getPathFile());
        if (is_file($absolutePath)) {
            ImageOptimizer::optimize($absolutePath);
        }
    }

    /**
     * Delete the image named by setOverwrite(): the original and the same-named file in
     * every size folder, in any image extension — sizes removed from the settings since,
     * or encoded to another format, included. Files the new upload just wrote are kept.
     */
    private function deleteReplaced(): void
    {
        $oldName = $this->overwrite ? basename(trim($this->overwrite)) : '';
        if ($oldName === '' || $oldName === $this->getFullName()) {
            return;
        }

        $storage = Storage::disk($this->disk);
        $oldBaseName = pathinfo($oldName, PATHINFO_FILENAME);
        $paths = [$this->pathInFolder($this->folder, $oldName)];

        foreach ($this->sizeFolders() as $sizeFolder) {
            $paths[] = $this->pathInFolder($sizeFolder, $oldName);
            foreach ($this->imageExtensions as $extension) {
                $paths[] = $this->pathInFolder($sizeFolder, "{$oldBaseName}.{$extension}");
            }
        }

        $paths = array_values(array_diff(array_unique($paths), $this->writtenPaths()));
        $storage->delete($paths);
    }

    /**
     * Size folders to clean: every folder under the upload folder, plus the requested
     * size keys (the only ones when uploading to the disk root, so unrelated top-level
     * folders are never touched).
     *
     * @return list<string>
     */
    private function sizeFolders(): array
    {
        $folders = array_map(fn (string $sizeKey): string => $this->pathInFolder($this->folder, $sizeKey), array_keys($this->sizes));

        if ($this->folder !== '') {
            $folders = [...$folders, ...Storage::disk($this->disk)->directories($this->folder)];
        }

        return array_values(array_unique($folders));
    }

    /**
     * @return list<string>
     */
    private function writtenPaths(): array
    {
        $paths = [$this->getPathFile()];
        foreach (array_keys($this->sizes) as $sizeKey) {
            $paths[] = $this->pathInFolder($this->pathInFolder($this->folder, (string) $sizeKey), $this->getFullName());
        }

        return $paths;
    }

    private function pathInFolder(string $folder, string $name): string
    {
        return trim(trim($folder, '/') . '/' . $name, '/');
    }
}
