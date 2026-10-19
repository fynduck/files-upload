<?php

namespace Fynduck\FilesUpload;

use Fynduck\FilesUpload\Data\ImageSize;
use Fynduck\FilesUpload\Data\ManipulationOptions;
use Fynduck\FilesUpload\Enums\ImageAction;
use Fynduck\FilesUpload\Modifiers\Gd\BoxCoverModifier;
use Fynduck\FilesUpload\Traits\CheckFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Geometry\Point;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use Intervention\Image\Size;
use InvalidArgumentException;
use RuntimeException;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;

class ManipulationImage
{
    use CheckFile;

    protected string $pathImage;

    /**
     * @var array<string, ImageSize>
     */
    protected array $sizes = [];

    protected string $fileName = '';

    protected ?string $overwrite = null;

    protected string $folder;

    protected string $disk = 'public';

    protected ?string $background = null;

    protected ?int $blur = null;

    protected ?int $brightness = null;

    protected ?bool $greyscale = false;

    protected ?bool $optimize = false;

    protected ?string $encode = null;

    protected ?int $quality = 90;

    protected ?float $focalX = null;

    protected ?float $focalY = null;

    protected bool $upscale = true;

    /**
     * Source bytes, read once per save() and decoded for each size.
     */
    private ?string $sourceBytes = null;

    public static function load(string $pathImage): self
    {
        return new static($pathImage);
    }

    public function __construct(string $pathImage)
    {
        $this->pathImage = $pathImage;
        $this->disk = config('files-upload.disk', $this->disk);
    }

    public function withOptions(ManipulationOptions $options): self
    {
        $this->disk = $options->disk;
        $this->folder = trim($options->folder, '/');
        $this->fileName = $options->name;
        $this->overwrite = $options->overwrite;
        $this->background = $options->background;
        $this->blur = $options->blur;
        $this->brightness = $options->brightness;
        $this->greyscale = $options->greyscale;
        $this->optimize = $options->optimize;
        $this->encode = $options->encode;
        $this->quality = $options->quality;
        $this->focalX = $options->focalX;
        $this->focalY = $options->focalY;
        $this->upscale = $options->upscale;

        return $this;
    }

    public function setDisk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public function setSizes(array $sizes): self
    {
        $normalized = array_map(static fn ($size) => $size instanceof ImageSize ? $size : ImageSize::fromArray($size), $sizes);

        $this->sizes = $normalized;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->fileName = $name;

        return $this;
    }

    public function setOverwrite(?string $overwrite): self
    {
        $this->overwrite = $overwrite;

        return $this;
    }

    public function setFolder(string $folder): self
    {
        $this->folder = trim($folder, '/');

        return $this;
    }

    /**
     * @deprecated Use setEncodeFormat() instead.
     */
    public function setExtension(?string $extension = null): self
    {
        return $this->setEncodeFormat($extension);
    }

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

    public function setGreyscale(?bool $greyscale = false): self
    {
        $this->greyscale = $greyscale;

        return $this;
    }

    public function setOptimize(?bool $optimize = true): self
    {
        $this->optimize = $optimize;

        return $this;
    }

    public function setEncodeFormat(?string $encode = null): self
    {
        $encode = ManipulationOptions::normalizeEncode($encode);
        if ($encode && $this->isSupport($encode)) {
            $this->encode = $encode;
        }

        return $this;
    }

    public function setEncodeQuality(?int $quality = 90): self
    {
        $this->quality = ManipulationOptions::normalizeQuality($quality);

        return $this;
    }

    /**
     * Crop sizes around this point (fractions of the image, 0,0 = top-left) instead of
     * the size's position; null clears it.
     */
    public function setFocalPoint(int|float|null $x, int|float|null $y): self
    {
        $this->focalX = ManipulationOptions::normalizeFocal($x);
        $this->focalY = ManipulationOptions::normalizeFocal($y);

        return $this;
    }

    public function setUpscale(bool $upscale = true): self
    {
        $this->upscale = $upscale;

        return $this;
    }

    public function save(ImageAction|string $action = ImageAction::Resize): void
    {
        $action = ImageAction::resolve($action);
        if (! $this->sizes) {
            throw new InvalidArgumentException('Sizes is required.');
        }
        if (! $this->fileName) {
            throw new InvalidArgumentException('Filename is required.');
        }

        $sourceMimeType = $this->sourceMimeType();
        if (! $sourceMimeType || ! $this->isSupport($sourceMimeType)) {
            throw new RuntimeException("Format '{$sourceMimeType}' is not supported.");
        }

        try {
            $this->action($action);
        } finally {
            $this->sourceBytes = null;
        }
    }

    private function action(ImageAction $action): void
    {
        foreach ($this->sizes as $folderSize => $size) {
            if (! $size->hasDimensions()) {
                continue;
            }

            $this->renderSize($folderSize, $size, $size->action ?? $action);
        }
    }

    private function renderSize(string $folderSize, ImageSize $size, ImageAction $action): void
    {
        $this->deleteOld($folderSize);

        $image = $this->readImage();
        $sourceWidth = $image->width();
        $sourceHeight = $image->height();

        match ($action) {
            ImageAction::Crop   => $this->cropImage($image, $size->width, $size->height, $size->position),
            ImageAction::Resize => $this->scaleImage($image, $size->width, $size->height),
            ImageAction::Pad    => $this->padImage($image, $size->width, $size->height, $size->position),
        };

        $isResized = $image->width() !== $sourceWidth || $image->height() !== $sourceHeight;
        $this->applyEffectsAndStore($image, $folderSize, $isResized);
    }

    private function cropImage(ImageInterface $image, ?int $width, ?int $height, string $position): void
    {
        if (! $width || ! $height) {
            $this->scaleImage($image, $width, $height);
        } elseif (($this->focalX !== null && $this->focalY !== null) || ! $this->upscale) {
            $this->cropAroundFocalPoint($image, $width, $height);
        } else {
            $image->cover($width, $height, $position);
        }
    }

    /**
     * Fit the image inside width x height and fill the rest of that canvas with the
     * background colour. Without upscaling a smaller source keeps its size on the canvas.
     * With one side missing there is nothing to pad, so it is scaled.
     */
    private function padImage(ImageInterface $image, ?int $width, ?int $height, string $position): void
    {
        if (! $width || ! $height) {
            $this->scaleImage($image, $width, $height);

            return;
        }

        $background = $this->background ?? $image->backgroundColor();

        $this->upscale
            ? $image->contain($width, $height, $background, $position)
            : $image->containDown($width, $height, $background, $position);
    }

    private function scaleImage(ImageInterface $image, ?int $width, ?int $height): void
    {
        $this->upscale ? $image->scale($width, $height) : $image->scaleDown($width, $height);
    }

    /**
     * Cut the largest area of the target ratio centred on the focal point (centre when
     * none is set), kept inside the image, and resize it to the target size. Without
     * upscaling a source smaller than the target keeps the crop's own size.
     */
    private function cropAroundFocalPoint(ImageInterface $image, int $width, int $height): void
    {
        $sourceWidth = $image->width();
        $sourceHeight = $image->height();
        $targetRatio = $width / $height;

        if ($sourceWidth / $sourceHeight > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = max(1, (int) round($sourceHeight * $targetRatio));
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = max(1, (int) round($sourceWidth / $targetRatio));
        }

        $offsetX = $this->cropOffset($this->focalX ?? 0.5, $sourceWidth, $cropWidth);
        $offsetY = $this->cropOffset($this->focalY ?? 0.5, $sourceHeight, $cropHeight);

        $box = new Size($cropWidth, $cropHeight, new Point($offsetX, $offsetY));
        $isScaled = $this->upscale || $cropWidth > $width;

        $this->coverBox($image, $box, $isScaled ? $width : $cropWidth, $isScaled ? $height : $cropHeight);
    }

    /**
     * Cut the box and bring it to width x height. GD does it in one resampling step,
     * so no full-resolution crop is held next to the source within PHP's memory
     * limit; Imagick and vips allocate outside that limit and crop, then resize.
     */
    private function coverBox(ImageInterface $image, Size $box, int $width, int $height): void
    {
        if ($image->driver()->id() === 'GD') {
            $image->modify(new BoxCoverModifier($width, $height, $box));

            return;
        }

        $image->crop($box->width(), $box->height(), $box->pivot()->x(), $box->pivot()->y());

        if ($image->width() !== $width || $image->height() !== $height) {
            $image->resize($width, $height);
        }
    }

    private function cropOffset(float $focal, int $sourceLength, int $cropLength): int
    {
        $offset = (int) round($focal * $sourceLength - $cropLength / 2);

        return max(0, min($offset, $sourceLength - $cropLength));
    }

    public function optimize(string $imagePath): void
    {
        if ($this->optimize && class_exists(ImageOptimizer::class) && is_file($imagePath)) {
            ImageOptimizer::optimize($imagePath);
        }
    }

    private function getFolder(string $folder): string
    {
        return trim($this->folder . '/' . $folder, '/');
    }

    /**
     * Delete the size's file named by setOverwrite(), in whatever image extension it was
     * stored with.
     */
    private function deleteOld(string $folder): void
    {
        if (! $this->overwrite) {
            return;
        }

        $sizeFolder = $this->getFolder($folder);
        $oldBaseName = pathinfo($this->overwrite, PATHINFO_FILENAME);
        $paths = [trim($sizeFolder . '/' . basename($this->overwrite), '/')];
        foreach ($this->imageExtensions as $extension) {
            $paths[] = trim("{$sizeFolder}/{$oldBaseName}.{$extension}", '/');
        }

        Storage::disk($this->disk)->delete(array_values(array_unique($paths)));
    }

    private function readImage(): ImageInterface
    {
        $this->sourceBytes ??= $this->isAbsolutePath($this->pathImage)
            ? (string) file_get_contents($this->pathImage)
            : (string) Storage::disk($this->disk)->get(trim($this->pathImage, '/'));

        return Image::decodeBinary($this->sourceBytes);
    }

    private function sourceMimeType(): ?string
    {
        if ($this->isAbsolutePath($this->pathImage)) {
            return mime_content_type($this->pathImage) ?: null;
        }

        return Storage::disk($this->disk)->mimeType(trim($this->pathImage, '/'));
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\\\\/', $path) === 1;
    }

    private function applyEffectsAndStore(ImageInterface $image, string $folderSize, bool $isResized): void
    {
        $hasEffects = $this->blur || $this->brightness || $this->greyscale;

        if ($this->blur) {
            $image->blur($this->blur);
        }
        if ($this->brightness) {
            $image->brightness($this->brightness);
        }
        if ($this->greyscale) {
            $image->grayscale();
        }

        $relativePath = $this->getFolder($folderSize) . '/' . $this->fileName . '.' . $this->extension();
        $bytes = ImageEncoder::encode($image, $this->extension(), (int) $this->quality, (bool) $this->optimize, $this->background);

        if ($this->optimize && ! $isResized && ! $hasEffects && $this->isSourceReusable($image)) {
            $bytes = ImageEncoder::smallest($bytes, (string) $this->sourceBytes);
        }

        Storage::disk($this->disk)->put($relativePath, $bytes);

        if ($this->optimize) {
            $this->optimize(Storage::disk($this->disk)->path($relativePath));
        }
    }

    /**
     * The source bytes can stand in for an unchanged size when they are the same format
     * and upright as stored (no EXIF rotation that decoding applied).
     */
    private function isSourceReusable(ImageInterface $image): bool
    {
        $orientation = (int) ($image->exif('IFD0.Orientation') ?? 1);

        return $orientation <= 1
            && ImageEncoder::isSameFormat($this->extension(), (string) pathinfo($this->pathImage, PATHINFO_EXTENSION));
    }

    /**
     * The encode format, or the source's own extension — the same rule UploadFile uses
     * for the original, so the original and its sizes always share one extension.
     */
    private function extension(): string
    {
        return $this->encode ?: strtolower(pathinfo($this->pathImage, PATHINFO_EXTENSION));
    }
}
