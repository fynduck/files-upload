<?php

namespace Fynduck\FilesUpload;

use Fynduck\FilesUpload\Traits\CheckFile;
use Fynduck\FilesUpload\Traits\GenerateData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;

class UploadFile
{
    use CheckFile, GenerateData;

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
    protected ?string $encode = null;
    protected ?int $quality = 90;

    public static function file($file): self
    {
        return new static($file);
    }

    public function __construct($file)
    {
        $this->file = $file;
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

    public function setBackground(?string $bg): self
    {
        $this->background = $bg;

        return $this;
    }

    public function setBlur(?int $blur = 1): self
    {
        $this->blur = $blur >= 0 ? $blur : null;

        return $this;
    }

    public function setBrightness(?int $brightness): self
    {
        $this->brightness = $brightness >= -100 && $brightness <= 100 ? $brightness : null;

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

    public function setEncodeFormat(?string $encode = null): self
    {
        $candidate = $encode ? Str::lower($encode) : null;

        if ($candidate && $this->isSupport($candidate)) {
            $this->encode = $candidate;
        } elseif ($this->isUploaded()) {
            if ($this->file instanceof UploadedFile) {
                $candidate = Str::lower($this->file->getClientOriginalExtension());
            } elseif (is_string($this->file) && is_file($this->file)) {
                $candidate = Str::lower(pathinfo($this->file, PATHINFO_EXTENSION));
            }

            $this->encode = $candidate && $this->isSupport($candidate) ? $candidate : null;
        }

        return $this;
    }

    public function setEncodeQuality(?int $quality = 90): self
    {
        $this->quality = $quality >= 0 ? min($quality, 100) : 90;

        return $this;
    }

    public function save(string $action = 'resize'): string
    {
        if (!isset($this->folder)) {
            $this->folder = '';
        }

        if (!isset($this->name) || $this->name === '') {
            throw new \InvalidArgumentException('Filename is required.');
        }

        if (!$this->encode) {
            $this->setEncodeFormat();
        }

        if (!$this->encode) {
            throw new \InvalidArgumentException('Unsupported file format.');
        }

        if (!$this->isbase64() && !$this->isUploaded() && !$this->isSvg()) {
            throw new \InvalidArgumentException(
                'Invalid file input. The file must be a base64 string, an uploaded file, or an SVG file.'
            );
        }

        $this->deleteOld();

        if ($this->isbase64()) {
            $this->decodeBase64();
        }

        $this->generateNameFile();

        if (is_string($this->file)) {
            Storage::disk($this->disk)->put($this->getPathFile(), $this->file);
        } elseif ($this->isUploaded()) {
            Storage::disk($this->disk)
                ->putFileAs($this->folder, $this->file, $this->getFullName());
        }

        $pathImage = Storage::disk($this->disk)->path($this->getPathFile());

        if ($this->sizes && !$this->isSvg() && $this->isSupport()) {
            ManipulationImage::load($this->getPathFile())
                ->setDisk($this->disk)
                ->setSizes($this->sizes)
                ->setFolder($this->folder)
                ->setName($this->name)
                ->setOverwrite($this->overwrite)
                ->setBackground($this->background)
                ->setBlur($this->blur)
                ->setBrightness($this->brightness)
                ->setGreyscale($this->greyscale)
                ->setOptimize($this->optimize)
                ->setEncodeFormat($this->encode)
                ->setEncodeQuality($this->quality)
                ->save($action);
        }

        $this->optimize($pathImage);

        return $this->getFullName();
    }

    private function optimize($imagePath): void
    {
        if ($this->optimize && !$this->sizes && !$this->isSvg()) {
            ImageOptimizer::optimize($imagePath);
        }
    }

    private function deleteOld(): void
    {
        if ($this->overwrite) {
            Storage::disk($this->disk)->delete($this->folder . '/' . $this->overwrite);
        }
    }
}
