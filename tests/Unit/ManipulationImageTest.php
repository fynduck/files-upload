<?php

use Fynduck\FilesUpload\ManipulationImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelImageOptimizer\ImageOptimizerServiceProvider;

class ManipulationImageTest extends Orchestra
{
    public string $name = 'test2';
    public string $disk = 'public';
    public string $extension = 'jpg';
    public string $folder = 'images';
    public array $sizes = [
        'medium' => ['width' => 200, 'height' => 200],
    ];

    public static $latestResponse;

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::$latestResponse = null;
    }

    protected function getPackageProviders($app)
    {
        return [
            ImageOptimizerServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Image' => \Intervention\Image\Laravel\Facades\Image::class,
        ];
    }

    public function test_base_resize()
    {
        $fullName = $this->name.'.'.$this->extension;

        $file = UploadedFile::fake()->image($fullName);

        Storage::fake($this->disk)->putFileAs($this->folder, $file, $fullName);
        $pathImage = Storage::disk($this->disk)->path($this->folder.'/'.$fullName);

        ManipulationImage::load($pathImage)
            ->setSizes($this->sizes)
            ->setFolder($this->folder)
            ->setExtension($this->extension)
            ->setName($this->name)
            ->save();

        foreach ($this->sizes as $sizeFolder => $size) {
            Storage::disk($this->disk)->assertExists($this->folder.'/'.$sizeFolder.'/'.$fullName);
        }
    }

    public function test_set_all_resize()
    {
        $fullName = $this->name.'.'.$this->extension;

        $file = UploadedFile::fake()->image($fullName);

        Storage::fake($this->disk)->putFileAs($this->folder, $file, $fullName);
        $pathImage = Storage::disk($this->disk)->path($this->folder.'/'.$fullName);

        ManipulationImage::load($pathImage)
            ->setSizes($this->sizes)
            ->setFolder($this->folder)
            ->setExtension($this->extension)
            ->setName($this->name)
            ->setOverwrite($fullName)
            ->setBackground('#ffffff')
            ->setBlur(5)
            ->setBrightness(50)
            ->setGreyscale(true)
            ->setOptimize()
            ->setEncodeFormat('webp')
            ->setEncodeQuality(80)
            ->save();

        foreach ($this->sizes as $sizeFolder => $size) {
            Storage::disk($this->disk)->assertExists($this->folder.'/'.$sizeFolder.'/'.$this->name.'.webp');
        }
    }
}