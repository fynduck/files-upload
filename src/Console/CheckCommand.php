<?php

namespace Fynduck\FilesUpload\Console;

use Fynduck\FilesUpload\ServerCheck;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature = 'files-upload:check {--strict : Fail when something the configuration relies on is missing}';

    protected $description = 'Show the image driver, the formats it can write and the optimizer binaries found on this server';

    public function handle(ServerCheck $serverCheck): int
    {
        $driver = $serverCheck->driver();
        $this->components->twoColumnDetail('<fg=green;options=bold>Image driver</>');
        $this->components->twoColumnDetail('Driver', $driver ? "{$driver['name']} {$driver['version']}" : '<fg=red>cannot be loaded</>');

        foreach ($serverCheck->formats() as $format => $isSupported) {
            $this->components->twoColumnDetail("Encode {$format}", $isSupported ? '<fg=green>yes</>' : '<fg=yellow>no</>');
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green;options=bold>Optimizing</>');
        $this->components->twoColumnDetail('Built-in (metadata strip, progressive JPEG)', config('files-upload.optimize') ? '<fg=green>on</>' : 'off');

        if (! $serverCheck->hasOptimizerPackage()) {
            $this->components->twoColumnDetail('spatie/laravel-image-optimizer', 'not installed (optional)');
        } else {
            $this->components->twoColumnDetail('spatie/laravel-image-optimizer', '<fg=green>installed</>');
            foreach ($serverCheck->binaries() as $binary => $path) {
                $this->components->twoColumnDetail(
                    $binary,
                    $path ?? "<fg=yellow>missing</> — {$serverCheck->binaryHint($binary)}",
                );
            }
        }

        $problems = $serverCheck->problems();
        $this->newLine();

        if ($problems === []) {
            $this->components->info('Ready.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->components->warn($problem);
        }

        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }
}
