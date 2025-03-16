<?php

namespace Fynduck\FilesUpload\Tests\Unit;

use Fynduck\FilesUpload\UploadFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelImageOptimizer\ImageOptimizerServiceProvider;
use Intervention\Image\Laravel\ServiceProvider;

class UploadFileTest extends Orchestra
{
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

    public function test_file_uploads_successfully()
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $uploadFile = UploadFile::file($file)
            ->setDisk('public')
            ->setFolder('uploads')
            ->setName('test_image')
            ->setExtension('jpg')
            ->setOptimize(true)
            ->setEncodeQuality(80);

        $result = $uploadFile->save();

        $this->assertNotEmpty($result);
        Storage::disk('public')->assertExists('uploads/test_image.jpg');
    }

    public function test_file_sizes_without_params_is_uploaded_successfully()
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $uploadFile = UploadFile::file($file)
            ->setDisk('public')
            ->setFolder('uploads')
            ->setSizes([
                'thumb'  => ['width' => 100, 'height' => 100],
                'medium' => ['width' => 400, 'height' => 400],
                'large'  => ['width' => 600, 'height' => 600],
            ])
            ->setName('test_image')
            ->setOptimize(true)
            ->setEncodeQuality(80);

        $result = $uploadFile->save();

        $this->assertNotEmpty($result);
        Storage::disk('public')->assertExists('uploads/test_image.jpg');
    }

    public function test_file_sizes_is_uploaded_successfully()
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $uploadFile = UploadFile::file($file)
            ->setDisk('public')
            ->setSizes([
                'thumb'  => ['width' => 100, 'height' => 100],
                'medium' => ['width' => 400, 'height' => 400],
                'large'  => ['width' => 600, 'height' => 600],
            ])
            ->setFolder('images')
            ->setName('test-image_'.time())
            ->setOverwrite(null)
            ->setBackground('')
            ->setBlur(0)
            ->setBrightness(0)
            ->setGreyscale(true)
            ->setOptimize(false)
            ->setEncodeFormat('webp')
            ->setEncodeQuality(95);

        $result = $uploadFile->save();

        $this->assertNotEmpty($result);
        Storage::disk('public')->assertExists('uploads/test_image.jpg');
    }

    public function test_file_uploads_with_base64()
    {
        $file = UploadedFile::fake()->image('test.jpg');
        $base64 = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($file));
        $uploadFile = UploadFile::file($base64)
            ->setDisk('public')
            ->setFolder('uploads')
            ->setName('base64_image')
            ->setExtension('jpg')
            ->setOptimize(true)
            ->setEncodeQuality(80);

        $result = $uploadFile->save();

        $this->assertNotEmpty($result);
        Storage::disk('public')->assertExists('uploads/base64_image.jpg');
    }

    public function test_file_uploads_with_svg()
    {
        $file = UploadedFile::fake()->create('test.svg', 100, 'image/svg+xml');
        $uploadFile = UploadFile::file($file)
            ->setDisk('public')
            ->setFolder('uploads')
            ->setName('test_svg')
            ->setExtension('svg')
            ->setOptimize(false);

        $result = $uploadFile->save();

        $this->assertNotEmpty($result);
        Storage::disk('public')->assertExists('uploads/test_svg.svg');
    }

    public function test_file_uploads_with_overwrite()
    {
        $file = UploadedFile::fake()->image('test.jpg');
        Storage::disk('public')->put('uploads/overwrite_image.jpg', 'old content');

        $uploadFile = UploadFile::file($file)
            ->setDisk('public')
            ->setFolder('uploads')
            ->setName('overwrite_image')
            ->setExtension('jpg')
            ->setOverwrite('overwrite_image.jpg')
            ->setOptimize(true)
            ->setEncodeQuality(80);

        $result = $uploadFile->save();

        $this->assertNotEmpty($result);
        Storage::disk('public')->assertExists('uploads/overwrite_image.jpg');
        $this->assertNotEquals('old content', Storage::disk('public')->get('uploads/overwrite_image.jpg'));
    }
}
