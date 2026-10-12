<?php

namespace Fynduck\FilesUpload\Tests\Feature;

use Fynduck\FilesUpload\ServerCheck;
use Fynduck\FilesUpload\Tests\TestCase;
use Illuminate\Support\Facades\File;

class CheckCommandTest extends TestCase
{
    private string $originalPath;

    private string $binaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        // Only binaries the test creates are found: PATH points at an empty directory.
        $this->originalPath = (string) getenv('PATH');
        $this->binaryDirectory = sys_get_temp_dir() . '/files-upload-binaries-' . uniqid();
        File::makeDirectory($this->binaryDirectory);
        putenv('PATH=' . $this->binaryDirectory);
        config(['files-upload.optimize' => true, 'image-optimizer.binary_path' => '']);
    }

    protected function tearDown(): void
    {
        putenv('PATH=' . $this->originalPath);
        File::deleteDirectory($this->binaryDirectory);

        parent::tearDown();
    }

    public function test_it_reports_missing_optimizer_binaries_with_install_hints(): void
    {
        $this->artisan('files-upload:check')
            ->expectsOutputToContain('apt install jpegoptim')
            ->expectsOutputToContain('Optimizer binaries missing: jpegoptim')
            ->assertSuccessful();
    }

    public function test_strict_mode_fails_when_binaries_are_missing(): void
    {
        $this->artisan('files-upload:check', ['--strict' => true])->assertFailed();
    }

    public function test_strict_mode_passes_when_every_binary_is_found(): void
    {
        foreach (array_keys(ServerCheck::BINARIES) as $binary) {
            File::put("{$this->binaryDirectory}/{$binary}", "#!/bin/sh\n");
            chmod("{$this->binaryDirectory}/{$binary}", 0755);
        }

        $this->artisan('files-upload:check', ['--strict' => true])
            ->expectsOutputToContain('Ready.')
            ->assertSuccessful();
    }

    public function test_missing_binaries_are_not_a_problem_when_optimizing_is_off(): void
    {
        config(['files-upload.optimize' => false]);

        $this->artisan('files-upload:check', ['--strict' => true])->assertSuccessful();
    }

    public function test_the_about_command_shows_the_driver_and_formats(): void
    {
        $this->artisan('about', ['--only' => 'files upload'])
            ->expectsOutputToContain('Image driver')
            ->expectsOutputToContain('webp')
            ->assertSuccessful();
    }
}
