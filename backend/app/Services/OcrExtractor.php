<?php

namespace AlphaDirect\Services;

use AlphaDirect\Http\Controllers\Admin\AiConfigController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vision-LLM based OCR extractor for the customer-facing onboarding flow.
 *
 * Why this exists separately from the older OcrController:
 * The legacy pipeline runs Tesseract on the image, then asks the LLM to
 * parse the resulting text. On glossy laminated cards (Omang, driver
 * license) photographed at an angle Tesseract delivers ~30-50% character
 * accuracy and the parse is garbage. Modern vision-capable LLMs (Claude
 * Sonnet/Opus, Groq Llama 4 Scout) read the image directly and hold steady
 * around 95-99% on the field-level extractions we care about.
 *
 * Schemas are typed per document so the agent UI can render the right
 * form (Omang fields look different from a vehicle valuation). Auto-detect
 * is supported for the upload-and-go flow.
 */
class OcrExtractor
{
    /**
     * Document types we extract. Add to this list when a new flow lands;
     * the schema + prompt for each is in $this->schemas().
     */
    public const TYPES = [
        'omang_id',           // Botswana National Identity Card
        'driver_license',     // Botswana Driving Licence
        'vehicle_bluebook',   // RV.10 — Department of Road Transport registration book
        'vehicle_valuation',  // Insurer / assessor valuation report (Velocity Dynamics, etc.)
        'employment_letter',  // Employer confirmation (KYC requirement)
        'proof_of_residence', // Utility bill, bank statement, etc.
        'passport',           // International passport (foreign nationals)
    ];

    /**
     * Extract structured fields from an image.
     *
     * @param  string       $absolutePath  Path to the image on disk
     * @param  string|null  $expectedType  One of self::TYPES; null = auto-detect
     * @return array  { type, fields: [...], confidence: 0-1, raw_text?, ai_provider, ai_model }
     */
    public function extract(string $absolutePath, ?string $expectedType = null): array
    {
        if (!is_file($absolutePath)) {
            throw new \InvalidArgumentException('File not found: ' . $absolutePath);
        }

        $mime = $this->detectMime($absolutePath);

        // Typed PDFs (employer-generated employment letters, banks'
        // statements, vehicle valuations from valuer software) have an
        // embedded text layer — there's no OCR step needed at all. Pull
        // the text directly with smalot/pdfparser and feed it to a cheap
        // text LLM for structuring. Falls through to image OCR only when
        // the text layer is empty (scanned PDF) or the parser is missing.
        if ($mime === 'application/pdf') {
            $pdfText = $this->extractPdfTextLayer($absolutePath);
            if ($pdfText !== null && mb_strlen(trim($pdfText)) > 100) {
                return $this->structureFromText($pdfText, $expectedType, 'pdf_text_layer');
            }
            // Image-only PDF (scanned). The vision LLMs need a special
            // path for PDFs (Anthropic-only — Groq doesn't accept them),
            // and we may not have an Anthropic key configured. Try
            // OCR.space first since it accepts PDFs out of the box and
            // sidesteps the Groq-vs-Anthropic provider question.
            try {
                return $this->extractViaOcrSpace($absolutePath, $expectedType);
            } catch (\Throwable $e) {
                Log::info('OcrExtractor: ocrspace fallback failed for PDF, trying vision LLM', ['msg' => $e->getMessage()]);
                // Fall through to vision LLM as last resort.
            }
        }

        // Pipeline switch — OCR_PROVIDER env picks how we read the doc:
        //   vision_llm        Default. Image goes straight to Claude / Llama-Scout
        //                     (single call, ~$0.01-0.03, fast). Best when the
        //                     doc is a clean photo and the model handles it
        //                     well — but expensive at volume.
        //   ocrspace_then_llm OCR.space (free 25k/mo) extracts raw text →
        //                     cheap text-only Groq Llama-3 70B structures it
        //                     into our schema. ~10x cheaper at volume,
        //                     usually higher accuracy on Botswana ID cards
        //                     because text LLMs don't fight pixel noise.
        $pipeline = strtolower((string) env('OCR_PROVIDER', 'vision_llm'));

        if ($pipeline === 'ocrspace_then_llm') {
            return $this->extractViaOcrSpace($absolutePath, $expectedType);
        }

        // Vision LLM with OCR.space fallback. If the vision call throws
        // (Groq + PDF without Anthropic key, network blip, etc.) drop
        // to the cheap-but-slow OCR.space path so the customer doesn't
        // hit a 502 dead-end.
        try {
            return $this->extractViaVisionLlm($absolutePath, $expectedType);
        } catch (\Throwable $e) {
            Log::warning('OcrExtractor: vision LLM failed, falling back to OCR.space', ['msg' => $e->getMessage()]);
            return $this->extractViaOcrSpace($absolutePath, $expectedType);
        }
    }

    /**
     * Pull the embedded text layer from a typed PDF. Returns null if
     * the parser isn't available or if the PDF has no text (scanned).
     */
    private function extractPdfTextLayer(string $absolutePath): ?string
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($absolutePath);
                $text = $pdf->getText();
                return is_string($text) ? $text : null;
            } catch (\Throwable $e) {
                Log::warning('OcrExtractor: smalot pdfparser failed', ['msg' => $e->getMessage()]);
                return null;
            }
        }
        // CLI fallback for Linux deploys with poppler-utils installed.
        $tmp = [];
        @exec('pdftotext ' . escapeshellarg($absolutePath) . ' - 2>/dev/null', $tmp, $code);
        if ($code === 0 && !empty($tmp)) return implode("\n", $tmp);
        return null;
    }

    /**
     * Common path for any "we already have raw text" pipeline (PDF text
     * layer, OCR.space). Detects type if not supplied, sends text to
     * the text LLM for structuring, runs the same date-sanity check
     * as the vision path.
     */
    private function structureFromText(string $rawText, ?string $expectedType, string $sourceLabel): array
    {
        $detectedType = $expectedType;
        if ($detectedType === null || !in_array($detectedType, self::TYPES, true)) {
            $detectedType = $this->detectTypeFromText($rawText);
        }
        $schema = $this->schemas()[$detectedType] ?? $this->schemas()['omang_id'];
        $prompt = $this->buildTextStructuringPrompt($detectedType, $schema, $rawText);
        $aiResp = $this->callTextLlm($prompt);

        $fields = $aiResp['parsed'] ?? [];
        $fields = $this->sanityCheckDates($detectedType, $fields);

        return [
            'type'        => $detectedType,
            'fields'      => $fields,
            'confidence'  => $this->scoreConfidence($schema, $fields),
            'raw_text'    => mb_substr($rawText, 0, 4000),
            'source'      => $sourceLabel,
            'ai_provider' => $aiResp['provider'] ?? 'groq',
            'ai_model'    => $aiResp['model']    ?? null,
        ];
    }

    /** Existing vision-LLM path (Groq / Anthropic) — direct image → JSON. */
    private function extractViaVisionLlm(string $absolutePath, ?string $expectedType): array
    {
        $bytes  = file_get_contents($absolutePath);
        $base64 = base64_encode($bytes);
        $mime   = $this->detectMime($absolutePath);

        $detectedType = $expectedType;
        if ($detectedType === null || !in_array($detectedType, self::TYPES, true)) {
            $detectedType = $this->detectType($base64, $mime);
        }

        $schema = $this->schemas()[$detectedType] ?? $this->schemas()['omang_id'];
        $prompt = $this->buildExtractionPrompt($detectedType, $schema);

        $aiResp = $this->callVisionAi($base64, $mime, $prompt);

        $fields = $aiResp['parsed'] ?? [];
        $fields = $this->sanityCheckDates($detectedType, $fields);
        $confidence = $this->scoreConfidence($schema, $fields);

        return [
            'type'        => $detectedType,
            'fields'      => $fields,
            'confidence'  => $confidence,
            'ai_provider' => $aiResp['provider'] ?? null,
            'ai_model'    => $aiResp['model']    ?? null,
        ];
    }

    /**
     * Two-stage cheap path: OCR.space (free 25k pages/month) extracts
     * raw text from any image / PDF, then we hand the text to a cheap
     * text-only LLM (Groq Llama-3 70B) to structure into our schema.
     *
     * This is typically more accurate than direct vision for ID cards
     * because:
     *   1. OCR.space uses Tesseract under the hood with engine 2 doing
     *      handwriting + struct doc layout — better than vanilla
     *      Tesseract on glossy cards.
     *   2. Once we have the text, structuring is a "given this text,
     *      where's the DOB?" task that text LLMs nail at low cost
     *      ($0.07 / MTok on Groq) vs. vision LLMs ($3-15 / MTok).
     *
     * Cost at volume (1000 scans/day): vision_llm ~$30/day, this
     * pipeline ~$3/day even on the paid OCR.space tier.
     */
    private function extractViaOcrSpace(string $absolutePath, ?string $expectedType): array
    {
        // OCR.space free tier rejects files >1024 KB outright. If the file
        // is larger and it's a PDF, render page 1 to a downsampled JPEG
        // via Imagick or Ghostscript and OCR that instead. For oversized
        // images, downscale to a JPEG quality-step that gets us under 1MB.
        $size = @filesize($absolutePath) ?: 0;
        $limitBytes = 1024 * 1024;
        $pathToSend = $absolutePath;
        $tmpRendered = null;
        if ($size > $limitBytes) {
            $tmpRendered = $this->renderForOcrSpace($absolutePath);
            if ($tmpRendered) {
                $pathToSend = $tmpRendered;
            }
        }

        try {
            $rawText = $this->callOcrSpace($pathToSend);
        } finally {
            if ($tmpRendered && is_file($tmpRendered)) @unlink($tmpRendered);
        }

        // Document type — use a tiny text classifier call when caller
        // didn't supply one. Same prompt-shape as vision detectType but
        // much cheaper.
        $detectedType = $expectedType;
        if ($detectedType === null || !in_array($detectedType, self::TYPES, true)) {
            $detectedType = $this->detectTypeFromText($rawText);
        }

        $schema = $this->schemas()[$detectedType] ?? $this->schemas()['omang_id'];
        $prompt = $this->buildTextStructuringPrompt($detectedType, $schema, $rawText);

        $aiResp = $this->callTextLlm($prompt);

        $fields = $aiResp['parsed'] ?? [];
        $fields = $this->sanityCheckDates($detectedType, $fields);
        $confidence = $this->scoreConfidence($schema, $fields);

        return [
            'type'        => $detectedType,
            'fields'      => $fields,
            'confidence'  => $confidence,
            'raw_text'    => mb_substr($rawText, 0, 4000), // for debugging
            'ai_provider' => $aiResp['provider'] ?? 'ocrspace+groq',
            'ai_model'    => $aiResp['model']    ?? null,
        ];
    }

    private function callOcrSpace(string $absolutePath): string
    {
        $apiKey = (string) env('OCRSPACE_API_KEY', 'helloworld'); // 'helloworld' = unlimited free dev key
        $endpoint = 'https://api.ocr.space/parse/image';

        // OCR.space rejects files without a recognised extension. Our chunked
        // uploads land in storage with random/hash filenames (no extension),
        // so we sniff the mime and force a postname that OCR.space accepts.
        $mime = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: '') : '';
        $extMap = [
            'application/pdf' => 'pdf',
            'image/jpeg'      => 'jpg',
            'image/jpg'       => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'image/bmp'       => 'bmp',
            'image/gif'       => 'gif',
            'image/tiff'      => 'tif',
        ];
        $ext = $extMap[$mime] ?? (pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'jpg');
        $postname = 'upload.' . $ext;
        $postMime = $mime ?: ($ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => env('CURL_VERIFY_SSL', config('app.env') === 'production'),
            CURLOPT_SSL_VERIFYHOST => env('CURL_VERIFY_SSL', config('app.env') === 'production') ? 2 : 0,
            CURLOPT_HTTPHEADER     => ['apikey: ' . $apiKey],
            CURLOPT_POSTFIELDS     => [
                'file'     => new \CURLFile($absolutePath, $postMime, $postname),
                'language' => 'eng',
                'OCREngine'=> '2',          // engine 2: better on glossy/handwritten
                'isTable'  => 'false',
                'detectOrientation' => 'true',
                'scale'    => 'true',
                'isOverlayRequired' => 'false',
                'filetype' => strtoupper($ext === 'jpg' ? 'JPG' : $ext),
            ],
        ]);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \Exception("OCR.space cURL error: {$err}");
        }
        $resp = json_decode($body ?: '{}', true) ?: [];
        if (!empty($resp['IsErroredOnProcessing'])) {
            $msg = $resp['ErrorMessage'][0] ?? 'OCR.space returned an error';
            throw new \Exception("OCR.space error: " . (is_array($msg) ? json_encode($msg) : $msg));
        }
        $text = '';
        foreach (($resp['ParsedResults'] ?? []) as $page) {
            $text .= ($page['ParsedText'] ?? '') . "\n";
        }
        return trim($text);
    }

    /**
     * Render an oversized PDF / image down to a sub-1MB JPEG so OCR.space
     * (which rejects >1024KB on the free tier) accepts it. Returns the
     * temp path or null when no renderer is available — caller falls
     * back to vision LLM in that case.
     */
    private function renderForOcrSpace(string $absolutePath): ?string
    {
        $mime = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: '') : '';
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocrspace_' . bin2hex(random_bytes(6)) . '.jpg';

        // PDFs — page 1 only. Imagick first (bundled on most LAMP),
        // then ghostscript CLI (Linux deploy boxes), then null.
        if ($mime === 'application/pdf') {
            if (extension_loaded('imagick')) {
                try {
                    $im = new \Imagick();
                    $im->setResolution(150, 150);
                    $im->readImage($absolutePath . '[0]');
                    $im->setImageFormat('jpeg');
                    $im->setImageCompression(\Imagick::COMPRESSION_JPEG);
                    $im->setImageCompressionQuality(75);
                    $im->setImageBackgroundColor('white');
                    $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                    $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                    $im->writeImage($tmp);
                    $im->clear();
                    if (is_file($tmp) && filesize($tmp) > 0) {
                        return $this->shrinkJpegUnderLimit($tmp, 1024 * 1024) ?: $tmp;
                    }
                } catch (\Throwable $e) {
                    Log::info('OcrExtractor: Imagick PDF render failed, trying gs', ['msg' => $e->getMessage()]);
                }
            }
            // Ghostscript fallback (Linux). Renders page 1 only at 150 DPI.
            $gs = trim((string) @shell_exec('command -v gs 2>/dev/null'));
            if ($gs !== '') {
                @exec(sprintf(
                    '%s -sDEVICE=jpeg -r150 -dFirstPage=1 -dLastPage=1 -dJPEGQ=75 -o %s %s 2>/dev/null',
                    escapeshellarg($gs),
                    escapeshellarg($tmp),
                    escapeshellarg($absolutePath),
                ), $_, $code);
                if ($code === 0 && is_file($tmp) && filesize($tmp) > 0) {
                    return $this->shrinkJpegUnderLimit($tmp, 1024 * 1024) ?: $tmp;
                }
            }
            return null;
        }

        // Oversized image — re-encode at lower JPEG quality until under 1MB.
        if (str_starts_with($mime, 'image/') && function_exists('imagecreatefromstring')) {
            $bytes = @file_get_contents($absolutePath);
            if (!$bytes) return null;
            $img = @imagecreatefromstring($bytes);
            if (!$img) return null;
            for ($q = 75; $q >= 30; $q -= 10) {
                @imagejpeg($img, $tmp, $q);
                if (is_file($tmp) && filesize($tmp) <= 1024 * 1024) {
                    imagedestroy($img);
                    return $tmp;
                }
            }
            imagedestroy($img);
            return is_file($tmp) ? $tmp : null;
        }

        return null;
    }

    /** Iteratively re-encode a JPEG until it fits under $maxBytes. */
    private function shrinkJpegUnderLimit(string $path, int $maxBytes): ?string
    {
        if (filesize($path) <= $maxBytes) return $path;
        if (!function_exists('imagecreatefromjpeg')) return null;
        $img = @imagecreatefromjpeg($path);
        if (!$img) return null;
        for ($q = 70; $q >= 30; $q -= 10) {
            @imagejpeg($img, $path, $q);
            if (filesize($path) <= $maxBytes) {
                imagedestroy($img);
                return $path;
            }
        }
        imagedestroy($img);
        return $path; // best effort
    }

    private function detectTypeFromText(string $text): string
    {
        $upper = strtoupper($text);

        // Driver licence — checked BEFORE Omang because the SADC licence
        // also says "REPUBLIC OF BOTSWANA" and lists an "ID Omang", which
        // would otherwise misroute it to the Omang schema.
        if (str_contains($upper, 'DRIVING LICENCE') || str_contains($upper, 'DRIVING LICENSE') ||
            str_contains($upper, 'TESELETSO YA GO KGWEETSA') ||
            str_contains($upper, 'CARTA DE CONDU'))                                                        return 'driver_license';

        // Bluebook (RV.10) — checked BEFORE valuation. The bluebook has
        // distinctive phrases ("MOTOR VEHICLE REGISTRATION BOOK",
        // "ROAD TRANSPORT AND ROAD SAFETY", "RV.10", "REGISTRATION BOOK")
        // that the assessor's report doesn't. The bluebook is the
        // authoritative ownership doc; the valuation is a second-party
        // assessment letter.
        if (str_contains($upper, 'MOTOR VEHICLE REGISTRATION') ||
            str_contains($upper, 'REGISTRATION BOOK') ||
            str_contains($upper, 'BLUEBOOK') ||
            str_contains($upper, 'BLUE BOOK') ||
            str_contains($upper, 'ROAD TRANSPORT AND ROAD SAFETY') ||
            str_contains($upper, 'R.V.10') ||
            str_contains($upper, 'RV.10') ||
            str_contains($upper, 'YEAR OF MANF') ||
            str_contains($upper, 'UNLADEN WEIGHT') ||
            str_contains($upper, 'GROSS WEIGHT') ||
            str_contains($upper, 'PREV REGN') ||
            str_contains($upper, 'CITIZEN OF BOTSWANA'))                                                   return 'vehicle_bluebook';

        // Vehicle valuation / assessor report. Distinguishing markers:
        // "VEHICLE VALUATION", "ASSESSOR", "VALUER", "ROADWORTHY",
        // "VEHICLE VALUE", "ODOMETER", "MAKE & MODEL".
        if (str_contains($upper, 'VEHICLE VALUATION') ||
            str_contains($upper, 'VEHICLE VALUE') ||
            str_contains($upper, 'VEHICLE DETAILS') ||
            str_contains($upper, 'ROADWORTHY') ||
            str_contains($upper, 'ASSESSOR') ||
            str_contains($upper, 'VALUER') ||
            str_contains($upper, 'MAKE & MODEL') ||
            str_contains($upper, 'ODOMETER') ||
            str_contains($upper, 'ENGINE CAPACITY') ||
            str_contains($upper, 'YEAR OF MANUFACTURE') ||
            str_contains($upper, 'CHASSIS NUMBER'))                                                        return 'vehicle_valuation';

        if (str_contains($upper, 'PASSPORT'))                                                              return 'passport';

        if (str_contains($upper, 'NATIONAL IDENTITY CARD') ||
            str_contains($upper, 'NATIONAL ID CARD') ||
            (str_contains($upper, 'OMANG') && !str_contains($upper, 'DRIVING')))                           return 'omang_id';

        if (str_contains($upper, 'EMPLOYMENT CONFIRMATION') ||
            str_contains($upper, 'CONFIRMATION OF EMPLOYMENT') ||
            str_contains($upper, 'EMPLOYMENT LETTER') ||
            str_contains($upper, 'TO WHOM') ||
            str_contains($upper, 'EMPLOYED') ||
            str_contains($upper, 'EMPLOYER'))                                                              return 'employment_letter';

        if (str_contains($upper, 'PROOF OF RESIDENCE') ||
            str_contains($upper, 'UTILITY') ||
            str_contains($upper, 'BANK STATEMENT') ||
            str_contains($upper, 'STATEMENT OF ACCOUNT'))                                                  return 'proof_of_residence';

        // Fallback heuristic — if we see plate-like patterns (B 679 BTD)
        // call it vehicle_valuation; otherwise default to omang_id since
        // that's the most common upload in our flow.
        if (preg_match('/\b[A-Z]{1,3}\s?\d{2,4}\s?[A-Z]{1,3}\b/u', $upper)) return 'vehicle_valuation';

        return 'omang_id';
    }

    private function buildTextStructuringPrompt(string $type, array $schema, string $rawText): string
    {
        $shape = $this->shapeFromSchema($schema);
        $rawText = mb_substr($rawText, 0, 6000); // cap to keep token count down

        return <<<PROMPT
You are extracting structured fields from raw OCR text of a Botswana {$type} document.

The OCR text below is what an OCR engine read off the card / page. It may have
typos, missing punctuation, swapped characters (O↔0, I↔1, S↔5) and lines in
the wrong order — that's normal. Use context to interpret it.

OCR TEXT:
\"\"\"
{$rawText}
\"\"\"

Return ONLY a single JSON object matching this shape (no markdown, no commentary):
{$shape}

CRITICAL DATE RULES:
- date_of_birth is ALWAYS IN THE PAST (typically 18-90 years ago).
- date_of_expiry / validity_to / passport expiry is ALWAYS IN THE FUTURE for a current document.
- Future dates are NEVER date_of_birth.
- Dates older than 90 years are NEVER date_of_birth either.

Other rules:
- If a field isn't in the OCR text, set it to null. Do NOT guess.
- Dates in YYYY-MM-DD. DD/MM/YYYY → convert (30/07/2002 → 2002-07-30).
- Names exactly as printed (preserve UPPERCASE).
- Numeric fields (odometer, value, amount): digits only, no commas, no currency.
- For Botswana Omang IDs the MRZ has format YYMMDD<chk><sex>YYMMDD<chk>BWA — first
  YYMMDD is BIRTH, second YYMMDD is EXPIRY. Use the MRZ to disambiguate.
PROMPT;
    }

    private function shapeFromSchema(array $schema): string
    {
        $lines = [];
        foreach ($schema as $key => $desc) {
            $lines[] = "  \"{$key}\": <{$desc}>,";
        }
        return "{\n" . implode("\n", $lines) . "\n}";
    }

    /**
     * Cheap text-only LLM call. Uses Groq Llama-3 70B (~$0.07 / MTok)
     * which is roughly $0.0001 per ID-document structuring — basically
     * free at any volume.
     */
    private function callTextLlm(string $prompt): array
    {
        $cfg = AiConfigController::getSettings();

        if (strtolower($cfg['ai_provider'] ?? 'groq') === 'gemini') {
            try {
                $gKey   = $cfg['gemini_api_key'] ?? '';
                $gModel = $cfg['gemini_model'] ?? env('GEMINI_MODEL', 'gemini-2.5-flash');
                if (empty($gKey)) throw new \Exception('Gemini API key not configured for OCR text-structuring');
                $resp = \Illuminate\Support\Facades\Http::withOptions(['verify' => $this->verifySsl()])
                    ->withHeaders(['x-goog-api-key' => $gKey, 'Content-Type' => 'application/json'])
                    ->timeout(30)->post("https://generativelanguage.googleapis.com/v1beta/models/{$gModel}:generateContent", [
                        'system_instruction' => ['parts' => [['text' => 'You extract structured fields from OCR text. Return only valid JSON.']]],
                        'contents'           => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                        'generationConfig'   => ['temperature' => 0.0, 'maxOutputTokens' => 2048, 'thinkingConfig' => ['thinkingBudget' => 0], 'responseMimeType' => 'application/json'],
                    ]);
                if ($resp->failed()) {
                    throw new \Exception("Gemini text API {$resp->status()}: " . substr($resp->body(), 0, 400));
                }
                $raw = $resp->json('candidates.0.content.parts.0.text', '');
                if ($raw === '') {
                    $finish = $resp->json('candidates.0.finishReason', 'unknown');
                    throw new \Exception("Gemini text returned no text (finishReason={$finish})");
                }
                return ['raw' => $raw, 'provider' => 'gemini', 'model' => $gModel, 'parsed' => $this->parseJsonFromText($raw)];
            } catch (\Throwable $e) {
                // Gemini failed (commonly a transient 503). Fall through to the Groq
                // retry path below so the scan still completes — provided a Groq key
                // exists. (Groq has a 100k-tokens/day cap, so this is best-effort.)
                if (empty($cfg['groq_api_key'] ?? '')) throw $e;
                Log::warning('OcrExtractor: Gemini text failed, falling back to Groq', ['error' => $e->getMessage()]);
            }
        }

        $apiKey = $cfg['groq_api_key'] ?? '';
        // llama-3.3-70b-versatile was the old default and is NOT available on
        // our Groq key (404 model_not_found). groq/compound is, and returns
        // clean JSON without a reasoning preamble.
        $model  = (string) env('GROQ_TEXT_MODEL', 'groq/compound');
        if (empty($apiKey)) throw new \Exception('Groq API key not configured for OCR text-structuring');

        // Retry up to 2 times on 429 (Groq free tier is 1 req / 3s).
        // Honour Retry-After header when present, otherwise back off
        // exponentially: 4s, 8s. Timeouts and 5xx are also retried.
        // Bounded so the whole synchronous OCR request stays under the proxy
        // window: 2 attempts × 30s + a short back-off ≈ 62s worst case. The
        // old 3 × 60s + 4s/8s sleeps could reach ~190s and get killed by the
        // gateway as an empty-body 502.
        $attempts = 0;
        $maxAttempts = 2;
        while (true) {
            $attempts++;
            $resp = \Illuminate\Support\Facades\Http::withOptions(['verify' => $this->verifySsl()])
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])->timeout(30)->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model'       => $model,
                    'max_tokens'  => 1500,
                    'temperature' => 0.0,
                    'messages'    => [
                        ['role' => 'system', 'content' => 'You extract structured fields from OCR text. Return only valid JSON.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                ]);

            if (!$resp->failed()) {
                $raw = $resp->json('choices.0.message.content', '');
                return ['raw' => $raw, 'provider' => 'groq', 'model' => $model, 'parsed' => $this->parseJsonFromText($raw)];
            }

            $status = $resp->status();
            $retryable = ($status === 429 || ($status >= 500 && $status < 600));
            if (!$retryable || $attempts >= $maxAttempts) {
                throw new \Exception("Groq text API {$status}: " . substr($resp->body(), 0, 400));
            }
            $retryAfter = (int) ($resp->header('Retry-After') ?? 0);
            $sleepSecs = $retryAfter > 0 ? min($retryAfter, 5) : (2 * $attempts);
            Log::info('OcrExtractor: Groq text retry', [
                'attempt' => $attempts, 'status' => $status, 'sleep_s' => $sleepSecs,
            ]);
            sleep($sleepSecs);
        }
    }

    /**
     * The schemas we extract per document type. Each schema is a flat
     * association of `field_key => description` — the description is fed
     * to the LLM so it knows what to look for.
     */
    public function schemas(): array
    {
        return [
            'omang_id' => [
                'id_number'           => 'Omang / National ID number — exactly 9 digits printed near the top of the FRONT of the card under "ID NUMBER"',
                'surname'             => 'Family name / surname under "SURNAME" on the FRONT (UPPERCASE)',
                'forenames'           => 'Given names under "FORENAMES" on the FRONT (UPPERCASE)',
                'date_of_birth'       => 'Date of BIRTH printed under "DATE OF BIRTH" on the FRONT — MUST be in the past, typically 18-90 years ago. Format YYYY-MM-DD. NEVER use the expiry date or place-of-application date here.',
                'gender'              => 'M or F (printed as "SEX" on the BACK of the card)',
                'nationality'         => 'Nationality from the BACK (e.g. MOTSWANA)',
                'place_of_birth'      => 'Place of birth on the FRONT (city — Francistown / Gaborone / Maun / etc.)',
                'place_of_application'=> 'Place of application from the BACK (city)',
                'date_of_expiry'      => 'Date of EXPIRY from the BACK under "DATE OF EXPIRY" — MUST be in the future. Format YYYY-MM-DD.',
                'document_number'     => 'Document number from the MRZ (top line, after country code "ACBWAD"). The MRZ has 3 lines on the BACK: line 2 starts with YYMMDD of BIRTH then check-digit then sex letter then YYMMDD of EXPIRY. Use the MRZ as the authoritative source when the printed dates are unclear.',
            ],

            'driver_license' => [
                'licence_number'      => 'Licence number under "Licence Number"',
                'surname'             => 'Surname (UPPERCASE)',
                'forenames'           => 'Forenames (UPPERCASE)',
                'omang_id'            => 'Omang ID printed on the licence under "ID: Omang" — exactly 9 digits',
                'date_of_birth'       => 'Date of BIRTH under "Date of Birth" — MUST be in the past, typically 18-90 years ago. Format YYYY-MM-DD. Do NOT use first_issue_date or validity dates here.',
                'gender'              => 'M or F under "Gender"',
                'class'               => 'Vehicle class (A, B, C, etc.) under "Class"',
                'driver_restriction'  => 'Driver restriction code (e.g. 0 = none)',
                'vehicle_restriction' => 'Vehicle restriction code',
                'first_issue_date'    => 'First issue date in YYYY-MM-DD (under "First Issue")',
                'validity_from'       => 'Start of validity period in YYYY-MM-DD (e.g. "Jan 2022 - Jan 2027" → 2022-01-01)',
                'validity_to'         => 'End of validity period in YYYY-MM-DD — MUST be in the future for a current licence',
                'endorsement'         => 'Endorsement (Yes / No)',
            ],

            'vehicle_bluebook' => [
                // RV.10 = Department of Road Transport and Road Safety
                // Motor Vehicle Registration Book. The official ownership
                // proof — different from a third-party assessor's valuation.
                // Key distinguishing fields: registration_book_no, owner ID,
                // first_registered, body_type, fuel_used, weights.
                'registration_book_no'  => 'Registration Book number printed near the top (e.g. 49385888)',
                'registration_number'   => 'Vehicle plate number (e.g. B679BTD or B 679 BTD)',
                'make'                  => 'Make / manufacturer (e.g. MAZDA)',
                'model'                 => 'Model (e.g. DEMIO)',
                'body_type'             => 'Body type (HATCH BACK / SEDAN / SUV / etc.)',
                'colour'                => 'Body colour',
                'year_of_manufacture'   => 'Year of manufacture (4-digit)',
                'chassis_number'        => 'Chassis / VIN number',
                'engine_number'         => 'Engine number',
                'engine_capacity_cc'    => 'Engine capacity in cc (digits only — 1300 not 1.3 LITRES)',
                'fuel_used'             => 'Fuel type (PETROL / DIESEL / etc.)',
                'number_of_axles'       => 'Number of axles (digits only)',
                'unladen_weight_kg'     => 'Unladen / kerb weight in kg (digits only)',
                'gross_weight_kg'       => 'Gross weight in kg (digits only)',
                'first_registered'      => 'First registered date in YYYY-MM-DD',
                'owner_omang'           => 'Current owner ID number (Omang) — typically 9 digits',
                'owner_name'            => 'Current owner full name (as printed)',
                'owner_postal_address'  => 'Owner postal address',
                'owner_physical_address'=> 'Owner physical address (Plot No / area)',
                'registration_office'   => 'Registration office (e.g. DRTS - MARUAPULA)',
                'previous_owners_count' => 'Number of previous owners (digits)',
            ],

            'vehicle_valuation' => [
                'owner_name'           => 'Vehicle owner full name (as printed)',
                'make_model'           => 'Make and model (e.g. MAZDA DEMIO)',
                'year_of_manufacture'  => 'Year of manufacture (4-digit)',
                'registration_number'  => 'Plate number with separator (e.g. B 679 BTD)',
                'engine_number'        => 'Engine number',
                'chassis_number'       => 'Chassis / VIN number',
                'colour'               => 'Body colour',
                'transmission'         => 'AUTOMATIC or MANUAL',
                'engine_capacity'      => 'Engine capacity (e.g. 1.3 LITRES)',
                'odometer_km'          => 'Odometer reading in km (digits only)',
                'vehicle_value_pula'   => 'Estimated value in Pula (digits only, no thousands separator)',
                'condition_body'       => 'Bodywork condition (GOOD/FAIR/POOR)',
                'condition_interior'   => 'Interior condition',
                'condition_mechanical' => 'Mechanical condition',
                'condition_tyres'      => 'Tyre condition',
                'roadworthy'           => 'true if explicitly stated ROADWORTHY, false otherwise',
                'assessor_name'        => 'Assessor / valuer name',
                'assessment_date'      => 'Date of assessment in YYYY-MM-DD',
            ],

            'employment_letter' => [
                'employee_name'        => 'Employee full name',
                'employee_omang'       => 'Employee Omang ID (9 digits)',
                'residential_address'  => 'Employee residential address as printed',
                'employer_name'        => 'Employer / company name (incl. trading-as)',
                'employer_address'     => 'Employer address',
                'employer_phone'       => 'Employer contact phone',
                'employer_email'       => 'Employer contact email',
                'position'             => 'Job title / position',
                'employment_start_date'=> 'Start of employment in YYYY-MM-DD',
                'letter_date'          => 'Date the letter was issued in YYYY-MM-DD',
                'signatory_name'       => 'Person who signed the letter',
                'signatory_title'      => 'Signatory title (e.g. CEO & Director)',
            ],

            'proof_of_residence' => [
                'occupant_name'      => 'Name of the occupant on the bill / statement',
                'residential_address'=> 'Full residential address',
                'utility_provider'   => 'Utility / bank name issuing the document',
                'account_number'     => 'Account or meter number',
                'statement_date'     => 'Statement / bill date in YYYY-MM-DD',
                'amount_due'         => 'Amount due (digits only) — optional',
            ],

            'passport' => [
                'passport_number'    => 'Passport number',
                'surname'            => 'Surname (UPPERCASE)',
                'given_names'        => 'Given names (UPPERCASE)',
                'date_of_birth'      => 'Date of birth in YYYY-MM-DD',
                'gender'             => 'M or F',
                'nationality'        => 'Country of nationality',
                'place_of_birth'     => 'Place of birth',
                'date_of_issue'      => 'Date of issue in YYYY-MM-DD',
                'date_of_expiry'     => 'Date of expiry in YYYY-MM-DD',
                'issuing_authority'  => 'Issuing authority',
            ],
        ];
    }

    // ─── Internals ──────────────────────────────────────────────────────────

    /**
     * SSL verify policy. Off on Windows dev (PHP curl ships without a CA
     * bundle so api.groq.com / api.anthropic.com fail with cURL error 60).
     * On in any other env unless CURL_VERIFY_SSL=false is set explicitly.
     *
     * Override the default with CURL_VERIFY_SSL=true|false in .env when
     * the platform default is wrong for your environment.
     */
    private function verifySsl(): bool
    {
        $explicit = env('CURL_VERIFY_SSL');
        if ($explicit !== null) {
            return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
        }
        // PHP_OS_FAMILY = Windows on Win, Darwin on Mac, Linux on Linux
        $isWindowsDev = PHP_OS_FAMILY === 'Windows' && config('app.env') !== 'production';
        return !$isWindowsDev;
    }

    private function detectMime(string $path): string
    {
        $mime = mime_content_type($path) ?: 'image/jpeg';
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'], true)) {
            return $mime;
        }
        return 'image/jpeg';
    }

    private function detectType(string $base64, string $mime): string
    {
        $list = "- " . implode("\n- ", self::TYPES);
        $prompt = "Look at this document and reply with exactly one of these types (no extra words):\n{$list}";
        try {
            $resp = $this->callVisionAi($base64, $mime, $prompt, /*expectJson*/ false);
            $raw  = strtolower(trim($resp['raw'] ?? ''));
            foreach (self::TYPES as $t) {
                if (str_contains($raw, $t)) return $t;
            }
        } catch (\Throwable $e) {
            Log::warning('OcrExtractor.detectType failed: ' . $e->getMessage());
        }
        return 'omang_id'; // safe default — most common in our flow
    }

    private function buildExtractionPrompt(string $type, array $schema): string
    {
        $lines = [];
        foreach ($schema as $key => $desc) {
            $lines[] = "  \"{$key}\": <{$desc}>,";
        }
        $shape = "{\n" . implode("\n", $lines) . "\n}";

        return <<<PROMPT
You are extracting structured fields from a Botswana {$type} document.

Return ONLY a single JSON object matching this shape (no markdown, no commentary):
{$shape}

CRITICAL DATE RULES — these are the most common extraction mistakes:
- date_of_birth is ALWAYS IN THE PAST (the customer is alive — typically born 18-90 years ago).
- date_of_expiry / validity_to / passport expiry is ALWAYS IN THE FUTURE for a current document.
- If you read a date that is in the future, it is NOT the date_of_birth — it is an expiry / issue date. Re-read the document and find the correct DOB.
- If you read a date that is more than 90 years in the past, it is also NOT the DOB — it's likely a printing artifact. Set date_of_birth to null rather than guess.

Other rules:
- If a field is not visible or unreadable, set it to null (do NOT guess).
- Dates must be YYYY-MM-DD. If the document shows DD/MM/YYYY, convert it (30/07/2002 → 2002-07-30).
- Names should be exactly as printed (preserve UPPERCASE).
- Numeric fields (odometer, vehicle value, amount) must be digits only — no commas, no currency symbols.
- For booleans, return true/false (lowercase, no quotes).
- For Botswana Omang IDs, the MRZ on the BACK is the highest-fidelity source. The 2nd MRZ line has format:
    YYMMDD<check><sex>YYMMDD<check>BWA<...><check>
  where the FIRST YYMMDD is BIRTH and the SECOND YYMMDD is EXPIRY. Use the MRZ to disambiguate when the printed front fields are blurry.
PROMPT;
    }

    /**
     * Vision-capable AI call. Supports Anthropic Claude and Groq Llama 4 Scout.
     *
     * PDF fallback: Groq's vision API doesn't accept PDFs. When the
     * caller hands us a PDF AND the configured provider is Groq, we
     * transparently fall back to Anthropic if its key is also set.
     * Otherwise we surface a clear, actionable error.
     */
    private function callVisionAi(string $base64, string $mime, string $prompt, bool $expectJson = true): array
    {
        $cfg = AiConfigController::getSettings();
        $provider = strtolower($cfg['ai_provider'] ?? 'anthropic');

        if ($mime === 'application/pdf' && $provider === 'groq') {
            $anthropicKey = $cfg['anthropic_api_key'] ?? '';
            if (!empty($anthropicKey)) {
                Log::info('OcrExtractor: PDF detected, falling back from Groq → Anthropic for this scan');
                $provider = 'anthropic';
            } else {
                throw new \Exception(
                    'PDF uploads need Anthropic. Either set ANTHROPIC_API_KEY in V2 admin (AI config) ' .
                    'so we can fall back automatically for PDFs, or upload an image (JPG / PNG / WebP) instead.'
                );
            }
        }

        if ($provider === 'gemini') {
            try {
                // Gemini accepts BOTH images and PDFs natively as inline_data.
                $apiKey = $cfg['gemini_api_key'] ?? '';
                $model  = $cfg['gemini_model'] ?? env('GEMINI_MODEL', 'gemini-2.5-flash');
                if (empty($apiKey)) throw new \Exception('Gemini API key not configured');

                $resp = Http::withOptions(['verify' => $this->verifySsl()])
                    ->withHeaders([
                        // Key in the header, never the URL query string, so it can't
                        // leak into proxy/APM/access logs.
                        'x-goog-api-key' => $apiKey,
                        'Content-Type'   => 'application/json',
                    ])->timeout(75)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                        'contents' => [[
                            'role'  => 'user',
                            'parts' => [
                                ['inline_data' => ['mime_type' => $mime, 'data' => $base64]],
                                ['text' => $prompt],
                            ],
                        ]],
                        'generationConfig' => array_merge(
                            [
                                'temperature'     => 0.0,
                                // gemini-2.5-flash is a reasoning model: without a cap it spends
                                // the output budget "thinking" and emits no JSON on the larger
                                // schemas → empty/MAX_TOKENS. Cap output AND disable thinking.
                                'maxOutputTokens' => 2048,
                                'thinkingConfig'  => ['thinkingBudget' => 0],
                            ],
                            $expectJson ? ['responseMimeType' => 'application/json'] : []
                        ),
                    ]);

                if ($resp->failed()) {
                    throw new \Exception("Gemini vision API {$resp->status()}: " . substr($resp->body(), 0, 400));
                }
                $raw = $resp->json('candidates.0.content.parts.0.text', '');
                if ($raw === '') {
                    $finish = $resp->json('candidates.0.finishReason', 'unknown');
                    throw new \Exception("Gemini vision returned no text (finishReason={$finish}) — raise maxOutputTokens / lower thinkingBudget.");
                }
                $result = ['raw' => $raw, 'provider' => 'gemini', 'model' => $model];
                if ($expectJson) $result['parsed'] = $this->parseJsonFromText($raw);
                return $result;
            } catch (\Throwable $e) {
                // Gemini failed (commonly a transient 503). Fall back to Groq vision
                // for IMAGES so the scan still succeeds. Groq vision can't read PDFs,
                // so for a PDF (or with no Groq key) surface the original error.
                if ($mime === 'application/pdf' || empty($cfg['groq_api_key'] ?? '')) {
                    throw $e;
                }
                Log::warning('OcrExtractor: Gemini vision failed, falling back to Groq', ['error' => $e->getMessage()]);
                $provider = 'groq';
            }
        }

        if ($provider === 'anthropic') {
            $apiKey = $cfg['anthropic_api_key'] ?? '';
            // claude-sonnet-4-20250514 (Sonnet 4) retired 2026-06-15 → 404s.
            // Fall back to the current Sonnet vision model when none configured.
            $model  = $cfg['anthropic_model']   ?? 'claude-sonnet-4-6';
            if (empty($apiKey)) throw new \Exception('Anthropic API key not configured');

            // Anthropic accepts image OR document (PDF) as a content block.
            // PDFs go via the `document` block which preserves text layers
            // when present and runs OCR on rendered pages otherwise.
            $isPdf = $mime === 'application/pdf';
            $mediaBlock = $isPdf
                ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]]
                : ['type' => 'image',    'source' => ['type' => 'base64', 'media_type' => $mime,           'data' => $base64]];

            $resp = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            // Capped at 75s: behind Cloudflare (~100s edge) + a stock origin
            // (fastcgi_read_timeout ~60s), a longer call gets killed by the
            // proxy and surfaces as an empty-body 502. Failing here returns a
            // clean JSON ocr_failed the FE can show instead.
            ])->timeout(75)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $model,
                'max_tokens' => 2000,
                'temperature'=> 0.0,
                'messages'   => [[
                    'role' => 'user',
                    'content' => [
                        $mediaBlock,
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
            ]);

            if ($resp->failed()) {
                throw new \Exception("Anthropic vision API {$resp->status()}: " . substr($resp->body(), 0, 400));
            }
            $raw = $resp->json('content.0.text', '');
        } else {
            // Groq vision: must use a vision-capable model regardless of the
            // global groq_model setting (which is typically a text-only model
            // like llama-3.3-70b-versatile). GROQ_VISION_MODEL env var or
            // groq_vision_model vault key wins; default to Llama 4 Scout.
            $apiKey = $cfg['groq_api_key'] ?? '';
            // Llama 4 Scout is NOT available on our Groq key (404
            // model_not_found). qwen/qwen3.6-27b is the only vision-capable
            // model the key can reach — verified 2026-08-21 by posting an
            // image block to every available chat model.
            $model  = $cfg['groq_vision_model']
                   ?? env('GROQ_VISION_MODEL', 'qwen/qwen3.6-27b');
            if (empty($apiKey)) throw new \Exception('Groq API key not configured');

            // PDFs are filtered out earlier (Anthropic fallback or hard error)
            // — by this point $mime is guaranteed image/*.

            $resp = Http::withOptions(['verify' => $this->verifySsl()])
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])->timeout(60)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'      => $model,
                'max_tokens' => 2000,
                'temperature'=> 0.0,
                'messages'   => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mime};base64,{$base64}"]],
                        ['type' => 'text', 'text' => $prompt],
                    ],
                ]],
            ]);

            if ($resp->failed()) {
                throw new \Exception("Groq vision API {$resp->status()}: " . substr($resp->body(), 0, 400));
            }
            $raw = $resp->json('choices.0.message.content', '');
        }

        $result = ['raw' => $raw, 'provider' => $provider, 'model' => $model ?? null];
        if ($expectJson) {
            $result['parsed'] = $this->parseJsonFromText($raw);
        }
        return $result;
    }

    private function parseJsonFromText(string $text): array
    {
        $text = trim($text);
        // Reasoning models (qwen3.6, gpt-oss) emit <think>…</think> before the
        // answer, and that reasoning often CONTAINS a draft JSON object. The
        // recursive brace match further down would grab that draft instead of
        // the final answer, so drop the block first. Handles an unclosed tag
        // too (truncated output).
        $text = preg_replace('/<think>.*?<\/think>/is', '', $text);
        $text = preg_replace('/<think>.*$/is', '', $text);
        $text = trim((string) $text);
        // Strip ```json fences the model sometimes adds despite our instruction
        $text = preg_replace('/^```(?:json)?\s*/m', '', $text);
        $text = preg_replace('/\s*```\s*$/m', '', $text);

        $parsed = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
            return $parsed;
        }
        // Try to recover the first {...} block
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) return $parsed;
        }
        return [];
    }

    /**
     * Belt-and-braces date sanity check — even with a tightened prompt
     * the vision model occasionally swaps DOB and expiry on cards
     * where the layout is dense (Botswana driver's licence puts both
     * fields on the same line). We validate post-extraction:
     *
     *   date_of_birth must be 1900..today (any future date is wrong)
     *   date_of_expiry / validity_to must be today..2100
     *
     * Wrong-shape values are nulled rather than kept — better to ask
     * the agent to type than to pre-fill garbage they won't notice.
     */
    private function sanityCheckDates(string $type, array $fields): array
    {
        $today = strtotime('today');
        $past90 = strtotime('-100 years');
        $future100 = strtotime('+100 years');

        $isPast = function ($v) use ($today, $past90) {
            $t = strtotime((string) $v);
            return $t !== false && $t <= $today && $t >= $past90;
        };
        $isFuture = function ($v) use ($today, $future100) {
            $t = strtotime((string) $v);
            return $t !== false && $t >= $today && $t <= $future100;
        };

        // Past-only fields
        foreach (['date_of_birth', 'first_issue_date'] as $key) {
            if (isset($fields[$key]) && $fields[$key] && !$isPast($fields[$key])) {
                Log::warning("OcrExtractor: rejected non-past {$key}", [
                    'type'   => $type,
                    'value'  => $fields[$key],
                ]);
                $fields[$key] = null;
            }
        }
        // Future-only fields (for a CURRENT document)
        foreach (['date_of_expiry', 'validity_to'] as $key) {
            if (isset($fields[$key]) && $fields[$key] && !$isFuture($fields[$key])) {
                Log::warning("OcrExtractor: rejected non-future {$key}", [
                    'type'   => $type,
                    'value'  => $fields[$key],
                ]);
                // Don't null an expired document's expiry — that's still
                // useful information. Only reject if it's nonsensical
                // (year 1900, etc.).
                $t = strtotime((string) $fields[$key]);
                if ($t === false || $t < strtotime('-50 years')) {
                    $fields[$key] = null;
                }
            }
        }

        return $fields;
    }

    private function scoreConfidence(array $schema, array $fields): float
    {
        $expected = count($schema);
        if ($expected === 0) return 0.0;
        $present = 0;
        foreach (array_keys($schema) as $key) {
            $v = $fields[$key] ?? null;
            if ($v !== null && $v !== '' && $v !== false) $present++;
        }
        return round($present / $expected, 2);
    }
}
