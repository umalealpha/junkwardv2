<?php

namespace AlphaDirect\Providers;

use Illuminate\Support\ServiceProvider;

class EnsureCacheDirectoriesProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Automatically create cache directories if missing
        $directories = [
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/views'),
            storage_path('logs'),
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && !is_writable($dir)) {
                @chmod($dir, 0775);
            }
        }
    }
}
