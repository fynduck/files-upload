<?php

namespace Fynduck\FilesUpload;

use Fynduck\FilesUpload\Traits\CheckFile;
use Fynduck\FilesUpload\Traits\GenerateData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class UploadFile
{
    use CheckFile;
    use GenerateData;

    /**
     * @var UploadedFile|string
     */
    protected $file;

    protected $base64;

    protected $name;

    protected $extension;

    protected $overwrite;

    protected $disk = 'public';

    protected $folder;

    protected $manipulationImage;

    protected $formats = ['jpeg', 'jpg', 'png', 'gif', 'webp'];

    protected $sizes = [];

    protected $background = null;

    protected $blur;

    protected $brightness;

    protected $greyscale = false;

    protected $optimize;

    protected $encode = null;

    protected $quality;

    public static function file($file): UploadFile
    {
        return new static($file);
    }

    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * @param string $disk
     * @return $this
     */
    public function setDisk(string $disk): UploadFile
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * @param string $folder
     * @return $this
     */
    public function setFolder(string $folder): UploadFile
    {
        $this->folder = $folder;

        return $this;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): UploadFile
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param string $extension
     * @return $this
     */
    public function setExtension(string $extension): UploadFile
    {
        $this->extension = $extension;

        return $this;
    }

    /**
     * @param string|null $overwrite
     * @return $this
     */
    public function setOverwrite(?string $overwrite): UploadFile
    {
        $this->overwrite = $overwrite;

        return $this;
    }

    /**
     * @param array $sizes
     * @return $this
     */
    public function setSizes(array $sizes): UploadFile
    {
        $this->sizes = $sizes;

        return $this;
    }

    /**
     * @param string|null $bg
     * @return $this
     */
    public function setBackground(?string $bg): UploadFile
    {
        $this->background = $bg;

        return $this;
    }

    /**
     * @param int $blur
     * @return $this
     */
    public function setBlur(int $blur = 1): UploadFile
    {
        $this->blur = $blur >= 0 ? $blur : null;

        return $this;
    }

    /**
     * @param int|null $brightness
     * @return $this
     */
    public function setBrightness(?int $brightness): UploadFile
    {
        $this->brightness = $brightness >= -100 && $brightness <= 100 ? $brightness : null;

        return $this;
    }

    /**
     * @param bool $greyscale
     * @return $this
     */
    public function setGreyscale(bool $greyscale = true): UploadFile
    {
        $this->greyscale = $greyscale;

        return $this;
    }

    /**
     * @param bool $optimize
     * @return $this
     */
    public function setOptimize(bool $optimize = true): UploadFile
    {
        $this->optimize = $optimize;

        return $this;
    }

    /**
     * @param string|null $encode
     * @return $this
     */
    public function setEncodeFormat(?string $encode = null): UploadFile
    {
        if ($encode && in_array(Str::lower($encode), $this->formats)) {
            $this->encode = $encode;
        }

        return $this;
    }

    /**
     * @param int|null $quality
     * @return $this
     */
    public function setEncodeQuality(?int $quality = 90): UploadFile
    {
        if ($quality >= 0 && $quality <= 100) {
            $this->quality = $quality;
        } else {
            $this->quality = 90;
        }

        return $this;
    }

    /**
     * Optimise image
     * @param $imagePath
     * @return void
     */
    private function optimize($imagePath)
    {
        if ($this->optimize) {
            $optimizerChain = OptimizerChainFactory::create();

            $optimizerChain->optimize($imagePath);
        }
    }

    /**
     * @param string $action
     * @return string
     */
    public function save(string $action = 'resize-crop'): string
    {
        $this->deleteOld();

        if (!$this->is_base64() && !$this->is_svg() && !$this->is_uploaded()) {
            return '';
        }

        if ($this->is_base64() || $this->is_svg()) {
            $this->generateNameFile();
            Storage::disk($this->disk)->put($this->getPathFile(), $this->file);
        } else if ($this->is_uploaded()) {
            $this->generateNameFile();
            Storage::disk($this->disk)->putFileAs($this->folder, $this->file, $this->getFullName());
        }

        $pathImage = Storage::disk($this->disk)->path($this->getPathFile());

        if ($this->sizes && !$this->is_svg()) {
            ManipulationImage::load($pathImage)
                ->setSizes($this->sizes)
                ->setFolder($this->folder)
                ->setName($this->getFullName(true))
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

        return $this->getFullName(true);
    }

    /**
     * Delete old image
     */
    private function deleteOld()
    {
        if ($this->overwrite) {
            Storage::disk($this->disk)->delete($this->folder . '/' . $this->overwrite);
        }
    }
}