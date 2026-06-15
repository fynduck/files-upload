<?php

namespace Fynduck\FilesUpload;

use Illuminate\Support\ServiceProvider;

class FilesUploadServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/files-upload.php' => $this->app->configPath('files-upload.php'),
        ], 'files-upload-config');
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/files-upload.php', 'files-upload');
    }
}
