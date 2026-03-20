<?php

namespace Fynduck\FilesUpload\Tests;

use Fynduck\FilesUpload\FilesUploadServiceProvider;
use Intervention\Image\Laravel\ServiceProvider as InterventionServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\LaravelImageOptimizer\ImageOptimizerServiceProvider;

class TestCase extends Orchestra
{
    use WithWorkbench;

    protected function getPackageProviders($app): array
    {
        return [
            FilesUploadServiceProvider::class,
            ImageOptimizerServiceProvider::class,
            InterventionServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/files-upload-tests',
            'throw' => false,
        ]);

        $app['config']->set('image.driver', 'gd');
    }
}
