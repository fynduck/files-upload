<?php

namespace Fynduck\FilesUpload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class UploadFile
{
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

    protected $sizes = [];

    protected $background = null;

    protected $blur;

    protected $brightness;

    protected $greyscale = false;

    protected $optimize;

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

    public function setBlur(int $blur = 1): UploadFile
    {
        $this->blur = $blur >= 0 ? $blur : null;

        return $this;
    }

    public function setBrightness(?int $brightness): UploadFile
    {
        $this->brightness = $brightness >= -100 && $brightness <= 100 ? $brightness : null;

        return $this;
    }

    public function setGreyscale(bool $greyscale = true): UploadFile
    {
        $this->greyscale = $greyscale;

        return $this;
    }

    public function setOptimize(bool $optimize = true): UploadFile
    {
        $this->optimize = $optimize;

        return $this;
    }

    private function optimize($imagePath)
    {
        if ($this->optimize) {
            $optimizerChain = OptimizerChainFactory::create();

            $optimizerChain->optimize($imagePath);
        }
    }

    private function is_uploaded(): bool
    {
        return is_uploaded_file($this->file);
    }

    private function is_base64(): bool
    {
        return (bool)preg_match("/data:([a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+).base64,.*/", $this->file);
    }

    private function is_svg(): bool
    {
        if (strpos($this->extension, 'svg') !== false) {
            return true;
        }

        return false;
    }

    private function decodeBase64()
    {
        [$type, $this->file] = explode(';', $this->file);
        [, $this->file] = explode(',', $this->file);

        if (!$this->extension) {
            $this->extension = explode('/', $type)[1];
        }

        $this->file = base64_decode($this->file);
    }

    private function getPathFile(): string
    {
        return $this->folder . '/' . $this->getFullName();
    }

    private function getFullName(): string
    {
        $extension = $this->extension;

        if ($this->is_svg()) {
            $extension = 'svg';
        }

        return "$this->name.$extension";
    }

    public function save(string $action = 'resize-crop'): string
    {
        $this->deleteOld();

        $this->generateNameFile();

        if ($this->is_uploaded()) {
            Storage::disk($this->disk)->putFileAs($this->folder, $this->file, $this->getFullName());
        } else {
            Storage::disk($this->disk)->put($this->getPathFile(), $this->file);
        }

        $pathImage = Storage::disk($this->disk)->path($this->getPathFile());

        if ($this->sizes && !$this->is_svg()) {
            ManipulationImage::load($pathImage)
                ->setSizes($this->sizes)
                ->setFolder($this->folder)
                ->setName($this->getFullName())
                ->setOverwrite($this->overwrite)
                ->setBackground($this->background)
                ->setBlur($this->blur)
                ->setBrightness($this->brightness)
                ->setGreyscale($this->greyscale)
                ->setOptimize($this->optimize)
                ->save($action);
        }

        $this->optimize($pathImage);

        return $this->getFullName();
    }

    private function deleteOld()
    {
        if ($this->overwrite) {
            Storage::disk($this->disk)->delete($this->folder . '/' . $this->overwrite);
        }
    }

    private function generateNameFile()
    {
        $fileName = $this->name;

        /**
         * extract extension if not defined
         */
        if (!$this->extension) {
            if ($this->is_uploaded()) {
                $this->extension = $this->file->getClientOriginalExtension();
            } else {
                $this->decodeBase64();
            }
        }

        /**
         * extract name if not defined
         */
        if (!$this->name) {
            if ($this->is_uploaded()) {
                $fileName .= '_' . $this->file->getClientOriginalName();
            } else {
                $fileName .= '_' . Str::random();
            }
        }

        /**
         * replace prohibited symbols
         */
        $pattern = ['/\s+/', '/,/'];
        $replace = ['_', '_'];
        $fileName = preg_replace($pattern, $replace, $fileName);

        $fileName = Str::slug($fileName, '_');

        $path = $this->folder ? $this->folder . '/' : '';
        $path .= $fileName . '.' . $this->extension;

        /**
         * Verify if exist file of this name, if exist change file name
         */
        if (Storage::disk($this->disk)->exists($path)) {
            $fileName .= '_' . Str::random(8);
        }

        $this->name = $fileName;
    }
}