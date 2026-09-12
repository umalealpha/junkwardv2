<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;

class FixCacheDirectories extends Command
{
    protected $signature = 'cache:fix-directories';
    protected $description = 'Create and fix permissions for all cache directories';

    public function handle(): int
    {
        $directories = [
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
                $this->info("✓ Created: {$dir}");
            }
            @chmod($dir, 0775);
        }

        // Clear all caches
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('view:clear');

        $this->info('✓ All cache directories fixed!');
        return 0;
    }
}
