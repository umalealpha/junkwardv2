<?php

namespace AlphaDirect\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\File\File;

/**
 * StorageService
 *
 * Universal put/get/exists with S3 as primary and local 'public' disk as fallback.
 * Use this everywhere you'd normally write to `Storage::disk('s3')` — uploads
 * will never hard-fail just because S3 is mis-configured or unreachable.
 *
 * Every returned array includes `disk` so downloads know where to look.
 */
class StorageService
{
    public const PRIMARY  = 's3';
    public const FALLBACK = 'public';

    /**
     * Write binary contents. Tries S3, falls back to local on any failure.
     *
     * @param  string          $path     Storage path (e.g. "quotes/123.pdf")
     * @param  string|resource $contents Binary contents or stream
     * @param  array           $options  ['ContentType' => ..., 'visibility' => 'public'|'private']
     * @return array{path: string, disk: string, url: string, size: int}
     */
    public function putWithFallback(string $path, $contents, array $options = []): array
    {
        $size = is_string($contents) ? strlen($contents) : null;

        // On local dev S3 is often misconfigured (read-only bucket / network
        // round-trip / dummy creds), so prefer the local `public` disk first.
        // Production keeps S3 → public so prod files end up on the bucket
        // where the read-side download path expects them.
        $order = app()->environment('local')
            ? [self::FALLBACK, self::PRIMARY]
            : [self::PRIMARY, self::FALLBACK];
        foreach ($order as $disk) {
            try {
                $ok = Storage::disk($disk)->put($path, $contents, $options);
                if ($ok !== false) {
                    Log::info("StorageService: wrote to {$disk}", ['path' => $path, 'size' => $size]);
                    return [
                        'path' => $path,
                        'disk' => $disk,
                        'url'  => $this->urlFor($disk, $path),
                        'size' => $size ?? Storage::disk($disk)->size($path),
                    ];
                }
                Log::warning("StorageService: put returned false on {$disk}", ['path' => $path]);
            } catch (\Throwable $e) {
                Log::warning("StorageService: {$disk} write failed: {$e->getMessage()}", ['path' => $path]);
            }
        }

        throw new \RuntimeException("StorageService: all disks failed for path {$path}");
    }

    /**
     * Store an uploaded file (Request::file(...) or any Symfony UploadedFile/File).
     *
     * @param  string                 $directory Relative directory
     * @param  File|UploadedFile      $file
     * @return array{path: string, disk: string, url: string, size: int}
     */
    public function putFileWithFallback(string $directory, $file, array $options = []): array
    {
        foreach ([self::PRIMARY, self::FALLBACK] as $disk) {
            try {
                $path = Storage::disk($disk)->putFile($directory, $file, $options);
                if ($path) {
                    return [
                        'path' => $path,
                        'disk' => $disk,
                        'url'  => $this->urlFor($disk, $path),
                        'size' => $file->getSize() ?: Storage::disk($disk)->size($path),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning("StorageService: {$disk} putFile failed: {$e->getMessage()}", ['dir' => $directory]);
            }
        }

        throw new \RuntimeException("StorageService: all disks failed for directory {$directory}");
    }

    /**
     * Find which disk holds the file (checks public first, then s3).
     * Returns the disk name, or null if not found anywhere.
     */
    public function resolveDisk(string $path): ?string
    {
        foreach ([self::FALLBACK, self::PRIMARY] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) return $disk;
            } catch (\Throwable $e) {
                // ignore; try next disk
            }
        }
        return null;
    }

    /**
     * Get contents, checking both disks.
     */
    public function getWithFallback(string $path): ?string
    {
        $disk = $this->resolveDisk($path);
        if ($disk === null) return null;
        try {
            return Storage::disk($disk)->get($path);
        } catch (\Throwable $e) {
            Log::warning("StorageService: get failed on {$disk}: {$e->getMessage()}", ['path' => $path]);
            return null;
        }
    }

    /**
     * Return a URL for a file, auto-detecting disk.
     */
    public function urlWithFallback(string $path): ?string
    {
        $disk = $this->resolveDisk($path);
        if ($disk === null) return null;
        return $this->urlFor($disk, $path);
    }

    /**
     * Delete from whichever disk(s) hold the file.
     */
    public function deleteWithFallback(string $path): bool
    {
        $deleted = false;
        foreach ([self::PRIMARY, self::FALLBACK] as $disk) {
            try {
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                    $deleted = true;
                }
            } catch (\Throwable $e) {
                Log::warning("StorageService: delete failed on {$disk}: {$e->getMessage()}", ['path' => $path]);
            }
        }
        return $deleted;
    }

    private function urlFor(string $disk, string $path): string
    {
        try {
            if ($disk === self::PRIMARY) {
                // If CloudFront is set, prefer it
                $cf = env('AWS_CLOUDFRONT');
                if ($cf) return rtrim($cf, '/') . '/' . ltrim($path, '/');
                return Storage::disk($disk)->url($path);
            }
            // Local disk: return the /storage/... URL
            return Storage::disk($disk)->url($path);
        } catch (\Throwable $e) {
            // last-ditch: construct app-url based link
            return rtrim((string) config('app.url'), '/') . '/storage/' . ltrim($path, '/');
        }
    }
}
