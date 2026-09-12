<?php

namespace AlphaDirect\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use AlphaDirect\Services\OcrExtractor;

/**
 * Hardened chunked-upload service for the customer-facing flow.
 *
 * Used by Customer KYC, Vehicle Pre-inspection, and any other tile
 * that pushes files larger than the typical Apache POST limit. Files
 * arrive as 100KB chunks, get assembled streaming-style, then land
 * on S3 (private, AES-256) with a kyc_documents row pinned to the
 * authorising customer cellphone.
 *
 * Security improvements over the legacy KycUploadController:
 *   - Bearer session token required (binds upload to a verified phone)
 *   - filename sanitised (no path traversal)
 *   - per-purpose MIME + size whitelist
 *   - per-chunk size cap (≤1MB) + total size cap (purpose-driven)
 *   - chunks stored under a per-session UUID dir (no collisions, no
 *     guessing other customers' uploads)
 *   - streaming assembly — no whole-file slurp into RAM
 *   - S3 ACL = private (legacy was 'public')
 *   - sha256 + de-dup so two uploads of the same Omang scan reuse
 *   - 1-hour session TTL; reaper removes orphans
 *   - virus-scan placeholder (clean by default until ClamAV wired)
 */
class PublicUploadService
{
    public const PURPOSES = [
        'omang_kyc', 'passport_kyc', 'license_kyc', 'employment_letter',
        'proof_of_residence', 'vehicle_photo', 'vehicle_inspection',
        'cellphone_photo', 'other',
    ];

    public const SESSION_TTL_SECONDS = 3600;     // 1 hour
    public const MAX_CHUNK_BYTES     = 1_048_576; // 1MB
    public const MAX_TOTAL_DEFAULT   = 10_485_760; // 10MB

    /**
     * Per-purpose constraints. Vehicle photos are smaller and image-only;
     * KYC docs allow PDF too. Tighter than legacy's 'whatever fits'.
     */
    public function constraints(string $purpose): array
    {
        $constraints = [
            'omang_kyc'          => ['mimes' => ['image/jpeg','image/png','image/webp','application/pdf'], 'max' => 10_485_760],
            'passport_kyc'       => ['mimes' => ['image/jpeg','image/png','image/webp','application/pdf'], 'max' => 10_485_760],
            'license_kyc'        => ['mimes' => ['image/jpeg','image/png','image/webp','application/pdf'], 'max' => 10_485_760],
            'employment_letter'  => ['mimes' => ['image/jpeg','image/png','image/webp','application/pdf'], 'max' => 10_485_760],
            'proof_of_residence' => ['mimes' => ['image/jpeg','image/png','image/webp','application/pdf'], 'max' => 10_485_760],
            'vehicle_photo'      => ['mimes' => ['image/jpeg','image/png','image/webp'], 'max' => 8_388_608],
            'vehicle_inspection' => ['mimes' => ['image/jpeg','image/png','image/webp'], 'max' => 8_388_608],
            'cellphone_photo'    => ['mimes' => ['image/jpeg','image/png','image/webp'], 'max' => 5_242_880],
            'other'              => ['mimes' => ['image/jpeg','image/png','image/webp','application/pdf'], 'max' => self::MAX_TOTAL_DEFAULT],
        ];
        return $constraints[$purpose] ?? $constraints['other'];
    }

    /**
     * Receive a single chunk. First chunk creates the session row;
     * subsequent chunks append; final chunk triggers assemble().
     *
     * @param  array  $session  Validated public_session_tokens row from PublicOtpService::validateToken.
     * @return array  { status, session_uuid, received_chunks, total_chunks, s3_path?, s3_url? }
     */
    public function receiveChunk(
        UploadedFile $chunk,
        string $purpose,
        string $originalFileName,
        int $chunkIndex,
        int $totalChunks,
        array $session,
        ?int $expectedSize = null,
        ?string $sessionUuid = null,
        ?string $mimeType = null,
        ?string $ip = null,
        ?string $ua = null,
        ?int $productId = null,
    ): array {
        if (!in_array($purpose, self::PURPOSES, true)) {
            return ['ok' => false, 'error' => 'invalid_purpose'];
        }
        if ($totalChunks < 1 || $totalChunks > 5000) {
            return ['ok' => false, 'error' => 'invalid_total_chunks'];
        }
        if ($chunkIndex < 0 || $chunkIndex >= $totalChunks) {
            return ['ok' => false, 'error' => 'chunk_index_out_of_range'];
        }
        if ($chunk->getSize() > self::MAX_CHUNK_BYTES) {
            return ['ok' => false, 'error' => 'chunk_too_large'];
        }

        $constraints = $this->constraints($purpose);
        if ($expectedSize && $expectedSize > $constraints['max']) {
            return ['ok' => false, 'error' => 'file_too_large', 'limit' => $constraints['max']];
        }

        // Find or create session row. session_uuid lets the FE drive the
        // grouping — first chunk POSTs without it, server returns the
        // generated uuid for subsequent chunks.
        $row = $sessionUuid
            ? DB::table('public_upload_sessions')->where('session_uuid', $sessionUuid)->first()
            : null;

        $authHash = hash('sha256', ($session['cellphone'] ?? '') . config('app.key'));

        if (!$row) {
            $sessionUuid = (string) Str::uuid();
            $safeName = $this->sanitiseFilename($originalFileName);
            $expiresAt = Carbon::now()->addSeconds(self::SESSION_TTL_SECONDS);
            DB::table('public_upload_sessions')->insert([
                'session_uuid'      => $sessionUuid,
                'auth_token_hash'   => $authHash,
                'cellphone'         => $session['cellphone'] ?? '',
                'purpose'           => $purpose,
                'product_id'        => $productId,
                'original_filename' => substr($originalFileName, 0, 255),
                'safe_filename'     => $safeName,
                'mime_type'         => $mimeType,
                'total_chunks'      => $totalChunks,
                'received_chunks'   => 0,
                'expected_size'     => $expectedSize,
                'status'            => 'receiving',
                'expires_at'        => $expiresAt,
                'client_ip'         => $ip,
                'client_ua'         => $ua ? substr($ua, 0, 255) : null,
                'created_at'        => Carbon::now(),
                'updated_at'        => Carbon::now(),
            ]);
            $row = DB::table('public_upload_sessions')->where('session_uuid', $sessionUuid)->first();
        }

        // Re-authorise on every chunk — token might have been revoked
        // mid-upload.
        if ($row->auth_token_hash !== $authHash) {
            return ['ok' => false, 'error' => 'session_mismatch'];
        }
        if (Carbon::parse($row->expires_at)->isPast() || $row->status !== 'receiving') {
            return ['ok' => false, 'error' => 'session_expired_or_closed'];
        }

        // Persist chunk on local disk under per-session dir. Path is
        // composed from a server-controlled UUID, never from the user-
        // supplied filename — no traversal risk.
        $chunkDir = storage_path("app/chunks/{$row->session_uuid}");
        if (!is_dir($chunkDir) && !mkdir($chunkDir, 0700, true)) {
            return ['ok' => false, 'error' => 'storage_init_failed'];
        }
        $chunkPath = $chunkDir . DIRECTORY_SEPARATOR . sprintf('part_%05d', $chunkIndex);
        if (!@move_uploaded_file($chunk->getPathname(), $chunkPath)) {
            return ['ok' => false, 'error' => 'chunk_save_failed'];
        }

        // Count received based on actual files on disk (idempotent — same
        // chunk re-uploaded doesn't double-count).
        $received = count(glob($chunkDir . DIRECTORY_SEPARATOR . 'part_*') ?: []);
        DB::table('public_upload_sessions')->where('id', $row->id)->update([
            'received_chunks' => $received,
            'updated_at'      => Carbon::now(),
        ]);

        if ($received < $totalChunks) {
            return [
                'ok'              => true,
                'status'          => 'progress',
                'session_uuid'    => $row->session_uuid,
                'received_chunks' => $received,
                'total_chunks'    => $totalChunks,
            ];
        }

        // Final chunk — assemble + upload + persist final row.
        return $this->assemble((array) $row, $constraints);
    }

    private function assemble(array $row, array $constraints): array
    {
        DB::table('public_upload_sessions')->where('id', $row['id'])->update([
            'status'     => 'assembling',
            'updated_at' => Carbon::now(),
        ]);

        $chunkDir = storage_path("app/chunks/{$row['session_uuid']}");
        $tmpFinal = $chunkDir . DIRECTORY_SEPARATOR . 'assembled.bin';

        $out = @fopen($tmpFinal, 'wb');
        if (!$out) {
            $this->markFailed($row['id'], 'assembly_open_failed');
            return ['ok' => false, 'error' => 'assembly_open_failed'];
        }

        $hash = hash_init('sha256');
        $totalBytes = 0;
        for ($i = 0; $i < $row['total_chunks']; $i++) {
            $partPath = $chunkDir . DIRECTORY_SEPARATOR . sprintf('part_%05d', $i);
            $in = @fopen($partPath, 'rb');
            if (!$in) { fclose($out); $this->markFailed($row['id'], 'assembly_chunk_missing'); return ['ok' => false, 'error' => 'assembly_chunk_missing']; }
            while (!feof($in)) {
                $buf = fread($in, 65536);
                fwrite($out, $buf);
                hash_update($hash, $buf);
                $totalBytes += strlen($buf);
            }
            fclose($in);
        }
        fclose($out);

        if ($totalBytes > $constraints['max']) {
            @unlink($tmpFinal);
            $this->markFailed($row['id'], 'final_too_large');
            return ['ok' => false, 'error' => 'file_too_large', 'limit' => $constraints['max']];
        }

        // Sniff the assembled MIME; the chunked stream may have lied.
        $sniffedMime = @mime_content_type($tmpFinal) ?: $row['mime_type'] ?? 'application/octet-stream';
        if (!in_array($sniffedMime, $constraints['mimes'], true)) {
            @unlink($tmpFinal);
            $this->markFailed($row['id'], 'mime_not_allowed');
            return ['ok' => false, 'error' => 'mime_not_allowed', 'mime' => $sniffedMime];
        }

        $sha = hash_final($hash);

        // De-dup: if the same hash + cellphone + purpose already exists,
        // reuse it. Saves S3 spend on legitimate retries.
        $existing = DB::table('public_uploaded_files')
            ->where('cellphone', $row['cellphone'])
            ->where('purpose',   $row['purpose'])
            ->where('sha256',    $sha)
            ->first();

        if ($existing) {
            @unlink($tmpFinal);
            $this->cleanupChunks($chunkDir);
            DB::table('public_upload_sessions')->where('id', $row['id'])->update([
                'status'     => 'completed',
                'updated_at' => Carbon::now(),
            ]);
            return [
                'ok'           => true,
                'status'       => 'completed',
                'session_uuid' => $row['session_uuid'],
                's3_path'      => $existing->s3_path,
                'sha256'       => $sha,
                'size_bytes'   => $existing->size_bytes,
                'deduplicated' => true,
            ];
        }

        // Push to S3. Path scoped by purpose + cellphone hash so leaked
        // bucket listings don't reveal customer identifiers.
        $s3Key = sprintf(
            'kyc/%s/%s/%s_%s',
            $row['purpose'],
            substr(hash('sha256', $row['cellphone'] . config('app.key')), 0, 12),
            substr($sha, 0, 16),
            $row['safe_filename'],
        );

        try {
            Storage::disk('s3')->putFileAs(
                dirname($s3Key),
                new \Illuminate\Http\File($tmpFinal),
                basename($s3Key),
                ['visibility' => 'private', 'ServerSideEncryption' => 'AES256'],
            );
        } catch (\Throwable $e) {
            Log::error('public_upload.s3_failed', ['msg' => $e->getMessage(), 'session' => $row['session_uuid']]);
            @unlink($tmpFinal);
            $this->markFailed($row['id'], 's3_upload_failed');
            return ['ok' => false, 'error' => 's3_upload_failed'];
        }

        @unlink($tmpFinal);
        $this->cleanupChunks($chunkDir);

        $fileId = DB::table('public_uploaded_files')->insertGetId([
            'session_id'  => $row['id'],
            'cellphone'   => $row['cellphone'],
            'purpose'     => $row['purpose'],
            // Carry forward whichever product this upload belongs to so
            // the materialisation worker can file it under the right
            // product folder when it creates the policy.
            'product_id'  => $row['product_id'] ?? null,
            // public_uploaded_files.s3_bucket is NOT NULL. In local dev the
            // S3 disk is mapped to the local driver and AWS_BUCKET is empty,
            // so default to a marker string so the row still inserts.
            's3_bucket'   => config('filesystems.disks.s3.bucket') ?: 'local-dev',
            's3_path'     => $s3Key,
            'size_bytes'  => $totalBytes,
            'sha256'      => $sha,
            'mime_type'   => $sniffedMime,
            'scan_status' => 'pending',  // ClamAV worker fills this in
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);

        DB::table('public_upload_sessions')->where('id', $row['id'])->update([
            'status'     => 'completed',
            'updated_at' => Carbon::now(),
        ]);

        Log::info('public_upload.completed', [
            'session_uuid' => $row['session_uuid'],
            'purpose'      => $row['purpose'],
            'size'         => $totalBytes,
            'mime'         => $sniffedMime,
        ]);

        return [
            'ok'           => true,
            'status'       => 'completed',
            'session_uuid' => $row['session_uuid'],
            'file_id'      => $fileId,
            's3_path'      => $s3Key,
            'sha256'       => $sha,
            'size_bytes'   => $totalBytes,
            'mime'         => $sniffedMime,
        ];
    }

    /**
     * Auth-less variant: receive chunks, assemble in /tmp, run OCR
     * against the assembled file, delete it, return only the
     * extracted fields. Used for the customer-facing document
     * scanner where the file is just a means to read its contents
     * — never persisted to S3, no DB row, no Bearer required.
     *
     * Same chunking guarantees as receiveChunk (1MB cap per chunk,
     * MIME + size whitelist on assembly). Heavier per-IP throttle
     * because the OCR call costs ~$0.01 each.
     */
    public function receiveScanChunk(
        \Illuminate\Http\UploadedFile $chunk,
        string $purpose, // doubles as OCR document_type, see OcrExtractor::TYPES
        string $originalFileName,
        int $chunkIndex,
        int $totalChunks,
        ?int $expectedSize = null,
        ?string $sessionUuid = null,
        ?string $mimeType = null,
        ?string $ip = null,
    ): array {
        if ($totalChunks < 1 || $totalChunks > 5000) {
            return ['ok' => false, 'error' => 'invalid_total_chunks'];
        }
        if ($chunkIndex < 0 || $chunkIndex >= $totalChunks) {
            return ['ok' => false, 'error' => 'chunk_index_out_of_range'];
        }
        if ($chunk->getSize() > self::MAX_CHUNK_BYTES) {
            return ['ok' => false, 'error' => 'chunk_too_large'];
        }

        // Constraints come from the same map keyed by purpose, so a
        // vehicle_valuation OCR scan can't push a 50MB blob through
        // this no-auth surface.
        $constraints = $this->scanConstraints($purpose);
        if ($expectedSize && $expectedSize > $constraints['max']) {
            return ['ok' => false, 'error' => 'file_too_large', 'limit' => $constraints['max']];
        }

        // Per-IP scoped temp dir keyed by session UUID. UUID is server-
        // generated on the first chunk so the IP can't collide with
        // someone else's parallel scan. We don't persist a DB row at
        // all — temp dir is the only state.
        if (!$sessionUuid) {
            $sessionUuid = (string) \Illuminate\Support\Str::uuid();
        }
        // Validate UUID shape so the dir name can't be poisoned.
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sessionUuid)) {
            return ['ok' => false, 'error' => 'invalid_session_uuid'];
        }

        // OCR-result cache: if this session has already completed an
        // OCR pass, return the cached result immediately. This handles
        // the FE-timeout-then-retry case: the first final-chunk request
        // ran OCR successfully but the FE cancelled before the response
        // arrived; the FE retries the final chunk; chunks have already
        // been cleaned up, so without the cache we'd report
        // assembly_chunk_missing even though OCR actually succeeded.
        $cacheKey = "scan_ocr_result:{$sessionUuid}";
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if (is_array($cached) && ($cached['ok'] ?? false)) {
            \Log::info('public_upload.scan_ocr_cache_hit', [
                'session' => $sessionUuid, 'ip' => $ip,
            ]);
            return $cached;
        }

        $chunkDir = storage_path("app/scan_chunks/{$sessionUuid}");
        if (!is_dir($chunkDir) && !mkdir($chunkDir, 0700, true)) {
            return ['ok' => false, 'error' => 'storage_init_failed'];
        }

        // Touch a marker file on first chunk so the reaper knows when
        // the session started (used to clean up dead scan sessions).
        if ($chunkIndex === 0 && !file_exists($chunkDir . DIRECTORY_SEPARATOR . '.started')) {
            file_put_contents($chunkDir . DIRECTORY_SEPARATOR . '.started', (string) time());
        }

        $chunkPath = $chunkDir . DIRECTORY_SEPARATOR . sprintf('part_%05d', $chunkIndex);
        if (!@move_uploaded_file($chunk->getPathname(), $chunkPath)) {
            return ['ok' => false, 'error' => 'chunk_save_failed'];
        }

        $received = count(glob($chunkDir . DIRECTORY_SEPARATOR . 'part_*') ?: []);

        if ($received < $totalChunks) {
            return [
                'ok'              => true,
                'status'          => 'progress',
                'session_uuid'    => $sessionUuid,
                'received_chunks' => $received,
                'total_chunks'    => $totalChunks,
            ];
        }

        // Assemble streaming-style.
        $tmpFinal = $chunkDir . DIRECTORY_SEPARATOR . 'assembled.bin';
        $out = @fopen($tmpFinal, 'wb');
        if (!$out) {
            $this->cleanupChunks($chunkDir);
            return ['ok' => false, 'error' => 'assembly_open_failed'];
        }
        $totalBytes = 0;
        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = $chunkDir . DIRECTORY_SEPARATOR . sprintf('part_%05d', $i);
            $in = @fopen($partPath, 'rb');
            if (!$in) {
                fclose($out);
                $this->cleanupChunks($chunkDir);
                return ['ok' => false, 'error' => 'assembly_chunk_missing'];
            }
            while (!feof($in)) {
                $buf = fread($in, 65536);
                fwrite($out, $buf);
                $totalBytes += strlen($buf);
            }
            fclose($in);
        }
        fclose($out);

        if ($totalBytes > $constraints['max']) {
            $this->cleanupChunks($chunkDir);
            return ['ok' => false, 'error' => 'file_too_large', 'limit' => $constraints['max']];
        }

        $sniffedMime = @mime_content_type($tmpFinal) ?: ($mimeType ?? 'application/octet-stream');
        if (!in_array($sniffedMime, $constraints['mimes'], true)) {
            $this->cleanupChunks($chunkDir);
            return ['ok' => false, 'error' => 'mime_not_allowed', 'mime' => $sniffedMime];
        }

        // Give the OCR step room regardless of the pool's global php.ini.
        // Base64-encoding a phone photo + buffering the vision API request can
        // briefly need >256MB; on a staging box with a stingier memory_limit
        // that overflow kills the php-fpm worker mid-request, which surfaces as
        // an empty-body 502 at the gateway (works on a 512MB dev box, 502s on
        // staging). Bump per-request so we don't depend on server config.
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        // Run OCR right here — caller wants extracted fields, not a file.
        try {
            $extractor = app(OcrExtractor::class);
            // Map upload purpose → OCR doc-type. They share most names
            // already; null = let OcrExtractor auto-detect.
            // 'auto' (or any unknown purpose) → null = let OcrExtractor
            // sniff the doc type from the OCR/text content. The customer
            // dropzone uses this so a single uploader handles all 6 doc
            // types without forcing the user to pre-classify.
            $ocrType = in_array($purpose, OcrExtractor::TYPES, true) ? $purpose : null;
            $result  = $extractor->extract($tmpFinal, $ocrType);
        } catch (\Throwable $e) {
            \Log::error('public_upload.scan_ocr_failed', [
                'session' => $sessionUuid,
                'msg'     => $e->getMessage(),
            ]);
            $this->cleanupChunks($chunkDir);
            // Map known failure shapes to friendlier error codes the FE
            // can switch on without parsing free-text.
            $msg = $e->getMessage();
            $code = 'ocr_failed';
            if (str_contains($msg, 'PDF uploads need Anthropic')) $code = 'pdf_needs_anthropic';
            return ['ok' => false, 'error' => $code, 'message' => $msg];
        }

        \Log::info('public_upload.scan_ocr_completed', [
            'session'    => $sessionUuid,
            'purpose'    => $purpose,
            'size'       => $totalBytes,
            'mime'       => $sniffedMime,
            'confidence' => $result['confidence'] ?? null,
            'ip'         => $ip,
        ]);

        $response = [
            'ok'           => true,
            'status'       => 'completed',
            'session_uuid' => $sessionUuid,
            'size_bytes'   => $totalBytes,
            'mime'         => $sniffedMime,
            // Same shape as POST /public/ocr/extract returns
            'type'         => $result['type']        ?? null,
            'fields'       => $result['fields']      ?? [],
            'confidence'   => $result['confidence']  ?? 0.0,
            'ai_provider'  => $result['ai_provider'] ?? null,
            'ai_model'     => $result['ai_model']    ?? null,
        ];

        // Cache the OCR response BEFORE deleting chunks, so a FE retry
        // arriving after the FE timed out gets the same result instead
        // of replaying OCR (which would either hit Groq rate-limit or
        // diverge subtly). 10-minute TTL is plenty — well past any
        // reasonable retry window.
        \Illuminate\Support\Facades\Cache::put($cacheKey, $response, 600);

        $this->cleanupChunks($chunkDir);

        return $response;
    }

    /**
     * Constraints for the no-persistence scan flow. Tighter than the
     * authed flow: only image + PDF, smaller ceilings (these are scans
     * of cards / single-page docs, never multi-MB files).
     */
    private function scanConstraints(string $purpose): array
    {
        $img = ['image/jpeg','image/png','image/webp'];
        $pdf = array_merge($img, ['application/pdf']);
        $map = [
            'auto'               => ['mimes' => $pdf, 'max' => 12_582_912], // 12MB — auto handles any doc
            'omang_id'           => ['mimes' => $pdf, 'max' => 8_388_608], // 8MB
            'passport'           => ['mimes' => $pdf, 'max' => 8_388_608],
            'driver_license'     => ['mimes' => $pdf, 'max' => 8_388_608],
            'vehicle_bluebook'   => ['mimes' => $pdf, 'max' => 12_582_912], // 12MB — RV.10 scans run large
            'vehicle_valuation'  => ['mimes' => $pdf, 'max' => 12_582_912], // 12MB — multi-page
            'employment_letter'  => ['mimes' => $pdf, 'max' => 10_485_760], // 10MB
            'proof_of_residence' => ['mimes' => $pdf, 'max' => 10_485_760],
        ];
        return $map[$purpose] ?? ['mimes' => $pdf, 'max' => 8_388_608];
    }

    /**
     * Sweep abandoned upload sessions older than expires_at. Runs from
     * the cron — keeps storage/app/chunks/ clean.
     */
    public function reapExpired(): int
    {
        $stale = DB::table('public_upload_sessions')
            ->where('status', 'receiving')
            ->where('expires_at', '<', Carbon::now())
            ->select('id', 'session_uuid')
            ->get();

        foreach ($stale as $row) {
            $this->cleanupChunks(storage_path("app/chunks/{$row->session_uuid}"));
            DB::table('public_upload_sessions')->where('id', $row->id)->update([
                'status' => 'expired',
                'updated_at' => Carbon::now(),
            ]);
        }
        return $stale->count();
    }

    // ─── Internals ──────────────────────────────────────────────────────

    private function sanitiseFilename(string $name): string
    {
        $name = basename($name); // strip any directory components
        $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
        $name = preg_replace('/_{2,}/', '_', $name);
        if (strlen($name) > 100) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $base = substr(pathinfo($name, PATHINFO_FILENAME), 0, 80);
            $name = $base . ($ext ? '.' . substr($ext, 0, 10) : '');
        }
        return $name ?: 'upload.bin';
    }

    private function markFailed(int $id, string $error): void
    {
        DB::table('public_upload_sessions')->where('id', $id)->update([
            'status'        => 'failed',
            'error_message' => $error,
            'updated_at'    => Carbon::now(),
        ]);
    }

    private function cleanupChunks(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) @unlink($f);
        @rmdir($dir);
    }
}
