<?php

namespace Fynduck\FilesUpload\tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    /**
     * Get package providers.
     *
     * @param  Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            FilesUploadServiceProvider::class,
        ];
    }
}