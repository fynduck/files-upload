<?php

namespace Fynduck\FilesUpload;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class ManipulationImage
{
    protected $pathImage;

    protected $sizes;

    protected $fileName;

    protected $overwrite;

    protected $folder;

    protected $formats = ['jpeg', 'jpg', 'png', 'gif', 'webp'];

    protected $actions = ['resize', 'resize-crop', 'crop'];

    protected $disk = 'public';

    protected $background = null;

    protected $blur;

    protected $brightness;

    protected $greyscale;

    protected $optimize;

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
        $this->brightness = $this->brightness = $brightness >= -100 && $brightness <= 100 ? $brightness : null;;

        return $this;
    }

    public function setGreyscale(bool $greyscale = true): ManipulationImage
    {
        $this->greyscale = $greyscale;

        return $this;
    }

    public function setOptimize(bool $optimize = true): ManipulationImage
    {
        $this->optimize = $optimize;

        return $this;
    }

    public function save(string $action = 'resize-crop')
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

        $explodedImage = explode('.', $this->fileName);
        $extension = array_pop($explodedImage);

        if (!in_array($extension, $this->formats)) {
            throw new \Error("Format '$extension' is not supported");
        }

        $this->action($action);
    }

    private function action($action)
    {
        foreach ($this->sizes as $folderSize => $size) {
            if ($size['width'] || $size['height']) {
                $this->deleteOld($folderSize);

                switch ($action) {
                    case 'crop':
                        $this->cropImage($folderSize, $size);
                        break;
                    case 'resize-crop':
                        $this->resizeCropImage($folderSize, $size);
                        break;
                    case 'resize':
                        $this->resizeImage($folderSize, $size);
                        break;
                }
            }
        }
    }

    /**
     * Crop image
     * @param $folderSize
     * @param $size
     */
    private function cropImage($folderSize, $size)
    {
        /**
         * Get original image
         */
        $image = Image::make($this->pathImage);

        /**
         * Verify width / height for resize
         */
        if ($size['width'] && $size['height']) {
            $image->crop($size['width'], $size['height']);
        } else {
            if ($size['width']) {
                $image->widen($size['width']);
            } else {
                $image->heighten($size['height']);
            }
        }

        $folderSave = $this->diskFolder() . $this->getFolder($folderSize);

        /**
         * Check if exist folder if not exist create folder
         */
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

        $imagePath = $folderSave . '/' . $this->fileName;

        /**
         * Save cropped image
         */
        $image->save($imagePath);

        $this->optimize($imagePath);
    }

    /**
     * Resize image
     * @param $folderSize
     * @param $size
     */
    private function resizeImage($folderSize, $size)
    {
        /**
         * Get original image
         */
        $image = Image::make($this->pathImage);

        $widthImg = $image->width();
        $heightImg = $image->height();

        /**
         * Verify width / height for crop
         */
        if ($size['width'] && $size['height']) {
            if (($widthImg / $size['width']) > ($heightImg / $size['height'])) {
                $image->resize(
                    $size['width'],
                    null,
                    function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    }
                );
            } else {
                $image->resize(
                    null,
                    $size['height'],
                    function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    }
                );
            }
        } else {
            $image->resize(
                $size['width'],
                $size['height'],
                function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                }
            );
        }

        $folderSave = $this->diskFolder() . $this->getFolder($folderSize);

        /**
         * Check if exist folder if not exist create folder
         */
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

        $imagePath = $folderSave . '/' . $this->fileName;
        /**
         * Save resize
         */
        $image->save($imagePath);

        $this->optimize($imagePath);
    }

    /**
     * Resize && crop image
     * @param $folderSize
     * @param $size
     */
    private function resizeCropImage($folderSize, $size)
    {
        /**
         * Get original image
         */
        $image = Image::make($this->pathImage);

        $widthImg = $image->width();
        $heightImg = $image->height();

        /**
         * Verify width / height for crop
         */
        if ($size['width'] && $size['height']) {
            if (($widthImg / $size['width']) > ($heightImg / $size['height'])) {
                $image->resize(
                    $size['width'],
                    null,
                    function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    }
                );
            } else {
                $image->resize(
                    null,
                    $size['height'],
                    function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    }
                );
            }

            /**
             * Set final size image
             */
            $widthImg = $size['width'];
            $heightImg = $size['height'];
        } else {
            $image->resize(
                $size['width'],
                $size['height'],
                function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                }
            );

            /**
             * Set final size image
             */
            $widthImg = $image->width();
            $heightImg = $image->height();
        }

        /**
         * Add background if width || height less than new resize
         */
        $background = Image::canvas($widthImg, $heightImg, $this->background);
        $image = $background->insert($image, 'center');

        $folderSave = $this->diskFolder() . $this->getFolder($folderSize);

        /**
         * Check if exist folder if not exist create folder
         */
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

        $imagePath = $folderSave . '/' . $this->fileName;
        /**
         * Save resize
         */
        $image->save($imagePath);

        $this->optimize($imagePath);
    }

    public function optimize($imagePath)
    {
        if ($this->optimize) {
            $optimizerChain = OptimizerChainFactory::create();

            $optimizerChain->optimize($imagePath);
        }
    }

    private function diskFolder()
    {
        return Storage::disk($this->disk)->getDriver()->getAdapter()->getPathPrefix();
    }

    private function getFolder($folder): string
    {
        return trim($this->folder . '/' . $folder, '/');
    }

    private function checkOrCreateFolder(string $folder)
    {
        if (!$this->checkExist($folder)) {
            Storage::disk($this->disk)->makeDirectory($folder);
        }
    }

    private function checkExist($path): bool
    {
        return Storage::disk($this->disk)->exists($path);
    }

    private function deleteOld(string $folder)
    {
        if ($this->overwrite) {
            Storage::disk($this->disk)->delete($this->getFolder($folder) . '/' . $this->overwrite);
        }
    }
}