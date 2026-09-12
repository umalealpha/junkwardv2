<?php

namespace AlphaDirect\Http\Controllers\Api\V1;

use AlphaDirect\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

/**
 * Avatar upload + serve for the authenticated user.
 *
 *   POST   /api/v1/me/avatar    multipart (image) — accepts jpg/png/webp
 *   DELETE /api/v1/me/avatar                      — clears the avatar
 *
 * The image is resized server-side to a 256×256 square (defence-in-depth
 * — even if the client crop UI misbehaves, the stored asset is always
 * the right shape). Client-side crop in the React modal does the
 * visual square selection; we just enforce it here.
 *
 * Storage uses the `documents` disk (S3, private). The presigned URL is
 * returned in the /me/profile payload (see MeController::profile) so
 * the frontend can render it directly without a proxy hop.
 */
class MeAvatarController extends Controller
{
    private const DISK = 'documents';
    private const MAX_BYTES = 5 * 1024 * 1024; // 5 MB upload cap

    public function upload(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }
        if (!Schema::hasColumn('users', 'avatar_path')) {
            return response()->json([
                'message' => 'Avatar storage not yet enabled on this environment.',
            ], 503);
        }

        $request->validate([
            // The mimes/dimensions/max rules cover obvious misuse — and we
            // also re-decode through Intervention/Image below, which will
            // throw on anything that isn't a real image.
            'image' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'image.required' => 'Please choose an image to upload.',
            'image.mimes'    => 'Only JPG, PNG, or WEBP images are supported.',
            'image.max'      => 'Image must be 5 MB or smaller.',
        ]);

        $file = $request->file('image');

        try {
            // Resize to a fixed 256×256 square. fit() crops to the centre if
            // the source isn't already square, which is rare since the
            // client modal pre-crops — but harmless as a safety net.
            $jpeg = Image::make($file->getRealPath())
                ->orient()                       // honour EXIF rotation
                ->fit(256, 256, fn ($c) => $c->upsize())
                ->encode('jpg', 85)
                ->getEncoded();
        } catch (\Throwable $e) {
            Log::warning('MeAvatarController: image decode failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Could not read that file as an image.'], 422);
        }

        // Stable filename — overwrites on every upload, so we never leak
        // old assets and the URL stays cacheable until updated_at changes.
        $path = "user-avatars/{$user->id}.jpg";

        try {
            Storage::disk(self::DISK)->put($path, $jpeg, 'private');
        } catch (\Throwable $e) {
            Log::error('MeAvatarController: S3 put failed', [
                'user_id' => $user->id,
                'path'    => $path,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to store avatar. Try again.'], 500);
        }

        $user->forceFill(['avatar_path' => $path])->saveQuietly();

        return response()->json([
            'message' => 'Avatar updated.',
            'data'    => [
                'avatar_path' => $path,
                'avatar_url'  => self::presign($path),
            ],
        ]);
    }

    public function destroy(): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Authentication required.'], 401);
        }
        if (!Schema::hasColumn('users', 'avatar_path')) {
            return response()->json(['message' => 'Avatar storage not enabled.'], 503);
        }

        $path = $user->avatar_path;
        if ($path) {
            try { Storage::disk(self::DISK)->delete($path); } catch (\Throwable $e) {
                // Object may already be gone — that's fine; we still clear the row.
                Log::info('MeAvatarController: delete swallowed', ['path' => $path, 'error' => $e->getMessage()]);
            }
            $user->forceFill(['avatar_path' => null])->saveQuietly();
        }

        return response()->json(['message' => 'Avatar removed.']);
    }

    /**
     * Build a short-lived presigned URL the browser can render directly.
     * Public method so MeController::profile() can call it to include the
     * URL in the consolidated payload without duplicating logic.
     */
    public static function presign(?string $path): ?string
    {
        if (!$path) return null;
        try {
            $disk = Storage::disk(self::DISK);
            // Only the s3 driver supports temporaryUrl — the local fallback
            // (dev) returns the regular URL.
            if (method_exists($disk, 'temporaryUrl')) {
                return $disk->temporaryUrl($path, now()->addMinutes(60));
            }
            return $disk->url($path);
        } catch (\Throwable $e) {
            Log::info('MeAvatarController: presign failed', ['path' => $path, 'error' => $e->getMessage()]);
            return null;
        }
    }
}
