<?php

namespace Fynduck\FilesUpload;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadFile
{
    /**
     * @var UploadedFile|string
     */
    protected $file;

    protected $base64;

    protected $name;

    protected $extension;

    protected $overwrite = true;

    protected $disk = 'public';

    protected $folder;

    protected $manipulationImage;

    protected $sizes = [];

    protected $background = null;

    public static function file($file): UploadFile
    {
        return new static($file);
    }

    public function __construct($file)
    {
        $this->file = $file;
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

    public function setOverwrite(bool $overwrite): UploadFile
    {
        $this->overwrite = $overwrite;

        return $this;
    }

    public function setSizes(array $sizes): UploadFile
    {
        $this->sizes = $sizes;

        return $this;
    }

    public function setBackground(string $bg): UploadFile
    {
        $this->background = $bg;

        return $this;
    }

    private function is_uploaded(): bool
    {
        return is_uploaded_file($this->file);
    }

    private function is_base64(): bool
    {
        return (bool)preg_match("/data:([a-zA-Z0-9]+\/[a-zA-Z0-9-.+]+).base64,.*/", $this->file);
    }

    private function decodeBase64()
    {
        [$type, $this->file] = explode(';', $this->file);
        [, $this->file] = explode(',', $this->file);

        if (!$this->extension)
            $this->extension = explode('/', $type)[1];

        $this->file = base64_decode($this->file);
    }

    private function getPathFile(): string
    {
        return $this->folder . '/' . $this->getFullName();
    }

    private function getFullName(): string
    {
        return "$this->name.$this->extension";
    }

    public function save(): string
    {
        if ($this->overwrite && $this->name) {
            $this->deleteOld();
        }

        $this->generateNameFile();

        if ($this->is_uploaded()) {
            Storage::disk($this->disk)->putFileAs($this->folder, $this->file, $this->getFullName());
        } else {
            Storage::disk($this->disk)->put($this->getPathFile(), $this->file);
        }

        $pathImage = Storage::disk($this->disk)->path($this->getPathFile());

        if ($this->sizes) {
            ManipulationImage::load($pathImage)
                ->setSizes($this->sizes)
                ->setFolder($this->folder)
                ->setName($this->getFullName())
                ->setBackground($this->background)
                ->save();
        }

        return $this->getFullName();
    }

    private function deleteOld()
    {
        $name = null;
        if ($this->extension) {
            $name = $this->name . '.' . $this->extension;
        } elseif ($this->is_uploaded()) {
            $name = $this->name . '.' . $this->file->getClientOriginalExtension();
        }

        if ($name) {
            Storage::disk($this->disk)->delete($this->folder . '/' . $name);
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
        if (Storage::disk($this->disk)->exists($path))
            $fileName .= '_' . Str::random(8);

        $this->name = $fileName;
    }
}