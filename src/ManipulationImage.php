<?php

namespace Fynduck\FilesUpload;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\FileExtension;
use Intervention\Image\Laravel\Facades\Image;
use Spatie\LaravelImageOptimizer\Facades\ImageOptimizer;

class ManipulationImage
{
    protected string $pathImage;
    protected array $sizes;
    protected string $fileName;
    protected string $extension;
    protected ?string $overwrite;
    protected string $folder;
    protected array $formats = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
    protected array $actions = ['resize', 'resize-crop', 'crop'];
    protected string $disk = 'public';
    protected ?string $background = null;
    protected ?int $blur;
    protected ?int $brightness;
    protected ?bool $greyscale;
    protected ?bool $optimize;
    protected ?string $encode = null;
    protected ?int $quality;

    public static function load(string $pathImage): ManipulationImage
    {
        return new static($pathImage);
    }

    public function __construct(string $pathImage)
    {
        $this->pathImage = $pathImage;
    }

    public function setDisk(string $disk): ManipulationImage
    {
        $this->disk = $disk;

        return $this;
    }

    public function setSizes(array $sizes): ManipulationImage
    {
        $this->sizes = $sizes;

        return $this;
    }

    public function setName(string $name): ManipulationImage
    {
        $this->fileName = $name;

        return $this;
    }

    public function setOverwrite(?string $overwrite): ManipulationImage
    {
        $this->overwrite = $overwrite;

        return $this;
    }

    public function setFolder(string $folder): ManipulationImage
    {
        $this->folder = $folder;

        return $this;
    }

    public function setBackground(?string $bg): ManipulationImage
    {
        $this->background = $bg;

        return $this;
    }

    public function setBlur(?int $blur = 1): ManipulationImage
    {
        $this->blur = $blur >= 0 ? $blur : null;

        return $this;
    }

    public function setBrightness(?int $brightness): ManipulationImage
    {
        $this->brightness = $brightness >= -100 && $brightness <= 100 ? $brightness : null;

        return $this;
    }

    public function setGreyscale(?bool $greyscale = false): ManipulationImage
    {
        $this->greyscale = $greyscale;

        return $this;
    }

    public function setOptimize(?bool $optimize = true): ManipulationImage
    {
        $this->optimize = $optimize;

        return $this;
    }

    public function setExtension(string $extension): ManipulationImage
    {
        $extension = Str::lower($extension);
        if (!$this->encode && in_array($extension, $this->formats)) {
            $this->extension = $extension;
        }
        return $this;
    }

    public function setEncodeFormat(?string $encode = null): ManipulationImage
    {
        $encode = Str::lower($encode);
        if ($encode && in_array($encode, $this->formats)) {
            $this->encode = $encode;
            $this->extension = $encode;
        }

        return $this;
    }

    public function setEncodeQuality(?int $quality = 90): ManipulationImage
    {
        $this->quality = ($quality && $quality >= 0 && $quality <= 100) ? $quality : 90;

        return $this;
    }

    public function save(string $action = 'resize'): void
    {
        if (!in_array($action, $this->actions)) {
            throw new \Error('Action does\'t exist');
        }
        if (!$this->sizes) {
            throw new \Error('Sizes is required');
        }
        if (!$this->fileName) {
            throw new \Error('Filename is required');
        }
        if (!in_array($this->extension, $this->formats)) {
            throw new \Error("Format '$this->extension' is not supported");
        }
        $this->action($action);
    }

    private function action($action): void
    {
        foreach ($this->sizes as $folderSize => $size) {
            if (!$size['width'] && !$size['height']) {
                continue;
            }
            $this->deleteOld($folderSize);
            switch ($action) {
                case 'crop':
                    $this->cropImage($folderSize, $size);
                    break;
                case 'resize':
                    $this->resizeImage($folderSize, $size);
                    break;
            }
        }
    }

    private function cropImage($folderSize, $size): void
    {
        $image = Image::read(Storage::disk($this->disk)->get($this->pathImage));
        if ($size['width'] && $size['height']) {
            $image->crop($size['width'], $size['height'], background: $this->background);
        } else {
            $image->scale($size['width'], $size['height']);
        }
        $this->checkOrCreateFolder($this->getFolder($folderSize));
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
        $imagePath = $this->generateImagePath($this->diskFolder($folderSize));
        $image->save($imagePath, quality: $this->quality);
        $this->optimize($imagePath);
    }

    private function resizeImage($folderSize, $size): void
    {
        $image = Image::read($this->pathImage);
        $image->scale($size['width'], $size['height']);

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
        $imagePath = $this->generateImagePath($this->diskFolder($folderSize));
        $image->save($imagePath, quality: $this->quality);
        $this->optimize($imagePath);
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

    private function generateImagePath(string $imgFolder): string
    {
        return "$imgFolder/$this->fileName.$this->extension";
    }
}