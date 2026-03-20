<?php

namespace Fynduck\FilesUpload;

use Fynduck\FilesUpload\Traits\CheckFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\FileExtension;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;
use RuntimeException;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;

class ManipulationImage
{
    use CheckFile;

    protected string $pathImage;
    protected array $sizes = [];
    protected string $fileName = '';
    protected ?string $overwrite = null;
    protected string $folder;
    protected array $actions = ['resize', 'crop'];
    protected string $disk = 'public';
    protected ?string $background = null;
    protected ?int $blur = null;
    protected ?int $brightness = null;
    protected ?bool $greyscale = false;
    protected ?bool $optimize = false;
    protected ?string $encode = null;
    protected ?int $quality = 90;

    public static function load(string $pathImage): self
    {
        return new static($pathImage);
    }

    public function __construct(string $pathImage)
    {
        $this->pathImage = $pathImage;
    }

    public function setDisk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public function setSizes(array $sizes): self
    {
        $this->sizes = $sizes;

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

    public function setBackground(?string $bg): self
    {
        $this->background = $bg;

        return $this;
    }

    public function setBlur(?int $blur = 1): self
    {
        $this->blur = $blur >= 0 ? min($blur, 100) : null;

        return $this;
    }

    public function setBrightness(?int $brightness): self
    {
        $this->brightness = $brightness >= -100 && $brightness <= 100 ? $brightness : null;

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
        $encode = Str::lower($encode);
        if ($encode && $this->isSupport($encode)) {
            $this->encode = $encode;
        }

        return $this;
    }

    public function setEncodeQuality(?int $quality = 90): self
    {
        $this->quality = $quality >= 0 ? min($quality, 100) : 90;

        return $this;
    }

    public function save(string $action = 'resize'): void
    {
        if (!in_array($action, $this->actions, true)) {
            throw new InvalidArgumentException('Action does not exist.');
        }
        if (!$this->sizes) {
            throw new InvalidArgumentException('Sizes is required.');
        }
        if (!$this->fileName) {
            throw new InvalidArgumentException('Filename is required.');
        }

        $sourceMimeType = $this->sourceMimeType();
        if (!$sourceMimeType || !$this->isSupport($sourceMimeType)) {
            throw new RuntimeException("Format '{$sourceMimeType}' is not supported.");
        }

        $this->action($action);
    }

    private function action(string $action): void
    {
        foreach ($this->sizes as $folderSize => $size) {
            $width = $size['width'] ?? null;
            $height = $size['height'] ?? null;

            if (!$width && !$height) {
                continue;
            }

            $this->deleteOld($folderSize);

            switch ($action) {
                case 'crop':
                    $this->cropImage($folderSize, $width, $height, $size['position'] ?? 'center');
                    break;
                case 'resize':
                    $this->resizeImage($folderSize, $width, $height);
                    break;
            }
        }
    }

    private function cropImage(string $folderSize, ?int $width, ?int $height, string $position): void
    {
        $image = $this->readImage();

        if ($width && $height) {
            $image->cover($width, $height, position: $position);
        } else {
            $image->scale($width, $height);
        }

        $this->applyEffectsAndStore($image, $folderSize);
    }

    private function resizeImage(string $folderSize, ?int $width, ?int $height): void
    {
        $image = $this->readImage();
        $image->scale($width, $height);

        $this->applyEffectsAndStore($image, $folderSize);
    }

    public function optimize($imagePath): void
    {
        if ($this->optimize) {
            ImageOptimizer::optimize($imagePath);
        }
    }

    private function diskFolder(string $folder): string
    {
        return Storage::disk($this->disk)->path($this->getFolder($folder));
    }

    private function getFolder($folder): string
    {
        return trim($this->folder.'/'.$folder, '/');
    }

    private function checkOrCreateFolder(string $folder): void
    {
        if (!$this->checkExist($folder)) {
            Storage::disk($this->disk)->makeDirectory($folder);
        }
    }

    private function checkExist($path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    private function deleteOld(string $folder): void
    {
        if ($this->overwrite) {
            Storage::disk($this->disk)->delete($this->getFolder($folder).'/'.$this->overwrite);
        }
    }

    private function readImage(): ImageInterface
    {
        return Image::withDriver(Driver::class)->read($this->sourcePath());
    }

    private function sourcePath(): string
    {
        return $this->isAbsolutePath($this->pathImage)
            ? $this->pathImage
            : Storage::disk($this->disk)->path(trim($this->pathImage, '/'));
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

    private function applyEffectsAndStore(ImageInterface $image, string $folderSize): void
    {
        if ($this->blur) {
            $image->blur($this->blur);
        }
        if ($this->brightness) {
            $image->brightness($this->brightness);
        }
        if ($this->greyscale) {
            $image->greyscale();
        }
        if ($this->encode) {
            $image->encodeByExtension(FileExtension::create($this->encode));
        }

        $this->checkOrCreateFolder($this->getFolder($folderSize));
        $imagePath = $this->generateImagePath($this->diskFolder($folderSize), $image->origin()->fileExtension());
        $image->save($imagePath, quality: $this->quality);
        $this->optimize($imagePath);
    }

    private function generateImagePath(string $imgFolder, string $extension): string
    {
        return "{$imgFolder}/{$this->fileName}." . ($this->encode ?: $extension);
    }
}
