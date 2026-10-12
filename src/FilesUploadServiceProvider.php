<?php

namespace Fynduck\FilesUpload;

use Fynduck\FilesUpload\Console\CheckCommand;
use Illuminate\Foundation\Console\AboutCommand;
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

        if ($this->app->runningInConsole()) {
            $this->commands([CheckCommand::class]);
            $this->addAboutSection();
        }
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/files-upload.php', 'files-upload');
    }

    /**
     * A "Files Upload" section in `php artisan about`; details in `files-upload:check`.
     */
    private function addAboutSection(): void
    {
        AboutCommand::add('Files Upload', static function (): array {
            $serverCheck = new ServerCheck();
            $driver = $serverCheck->driver();
            $formats = array_keys(array_filter($serverCheck->formats()));
            $missingBinaries = $serverCheck->hasOptimizerPackage()
                ? array_keys(array_filter($serverCheck->binaries(), static fn (?string $path): bool => $path === null))
                : [];

            return [
                'Image driver'     => $driver ? "{$driver['name']} {$driver['version']}" : '<fg=red>cannot be loaded</>',
                'Encode formats'   => implode(', ', $formats),
                'Optimizing'       => config('files-upload.optimize') ? 'built-in' . ($serverCheck->hasOptimizerPackage() ? ' + spatie' : '') : 'off',
                'Missing binaries' => $missingBinaries === [] ? 'none' : '<fg=yellow>' . implode(', ', $missingBinaries) . '</> (files-upload:check)',
            ];
        });
    }
}
