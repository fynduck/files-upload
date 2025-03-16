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
    protected string $extension = '';
    protected ?string $overwrite = null;
    protected string $disk = 'public';
    protected string $folder;
    protected array $formats = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
    protected array $sizes = [];
    protected ?string $background = null;
    protected ?int $blur = null;
    protected ?int $brightness = null;
    protected ?bool $greyscale = false;
    protected ?bool $optimize;
    protected ?string $encode = null;
    protected ?int $quality;

    public static function file($file): UploadFile
    {
        return new static($file);
    }

    public function __construct($file)
    {
        $this->file = $file;
    }

    public function setDisk(string $disk): UploadFile
    {
        $this->disk = $disk;

        return $this;
    }

    public function setFolder(string $folder): UploadFile
    {
        $this->folder = $folder;

        return $this;
    }

    public function setName(string $name): UploadFile
    {
        $this->name = $name;

        return $this;
    }

    public function setExtension(string $extension): UploadFile
    {
        $this->extension = $extension;

        return $this;
    }

    public function setOverwrite(?string $overwrite): UploadFile
    {
        $this->overwrite = $overwrite;

        return $this;
    }

    public function setSizes(array $sizes): UploadFile
    {
        $this->sizes = $sizes;

        return $this;
    }

    public function setBackground(?string $bg): UploadFile
    {
        $this->background = $bg;

        return $this;
    }

    public function setBlur(?int $blur = 1): UploadFile
    {
        $this->blur = $blur >= 0 ? $blur : null;

        return $this;
    }

    public function setBrightness(?int $brightness): UploadFile
    {
        $this->brightness = $brightness >= -100 && $brightness <= 100 ? $brightness : null;

        return $this;
    }

    public function setGreyscale(bool $greyscale = false): UploadFile
    {
        $this->greyscale = $greyscale;

        return $this;
    }

    public function setOptimize(bool $optimize = true): UploadFile
    {
        $this->optimize = $optimize;

        return $this;
    }

    public function setEncodeFormat(?string $encode = null): UploadFile
    {
        if ($encode && in_array(Str::lower($encode), $this->formats)) {
            $this->encode = $encode;
        }

        return $this;
    }

    public function setEncodeQuality(?int $quality = 90): UploadFile
    {
        $this->quality = ($quality && $quality >= 0 && $quality <= 100) ? $quality : 90;

        return $this;
    }

    private function optimize($imagePath): void
    {
        if ($this->optimize && !$this->sizes && !$this->is_svg()) {
            ImageOptimizer::optimize($imagePath);
        }
    }

    public function save(string $action = 'resize'): string
    {
        $this->deleteOld();

        $this->generateNameFile();

        if (!$this->is_base64() && !$this->is_svg() && !$this->is_uploaded()) {
            return '';
        }

        if ($this->is_base64() || $this->is_svg()) {
            Storage::disk($this->disk)->put($this->getPathFile(), $this->file);
        } elseif ($this->is_uploaded()) {
            Storage::disk($this->disk)
                ->putFileAs($this->folder, $this->file, $this->getFullName());
        }

        $pathImage = Storage::disk($this->disk)->path($this->getPathFile());

        if ($this->sizes && !$this->is_svg()) {
            ManipulationImage::load($pathImage)
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
                ->setExtension($this->extension)
                ->setEncodeFormat($this->encode)
                ->setEncodeQuality($this->quality)
                ->save($action);
        }

        $this->optimize($pathImage);

        return $this->getFullName();
    }

    private function deleteOld(): void
    {
        if ($this->overwrite) {
            Storage::disk($this->disk)->delete($this->folder.'/'.$this->overwrite);
        }
    }
}
