<?php

namespace AlphaDirect\Services\SmartUw;

use AlphaDirect\Http\Controllers\Admin\AiConfigController;
use AlphaDirect\Http\Controllers\Admin\VaultController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * In-process (PHP) fallback for Smart UW schedule extraction.
 *
 * WHY THIS EXISTS
 * The primary extractor is the Python smart-uw-engine sidecar, called over HTTP.
 * When that container is not in the ECS task, extraction dies with
 * "unreachable at http://localhost:8099" and the ONLY fix is an AWS task-def
 * change. Staging had no engine container and no working AWS access to add one,
 * so the feature was unusable there (2026-09-01). This path removes the infra
 * dependency: it reads the workbook with PhpSpreadsheet (already in the image
 * via maatwebsite/excel), pulls PDF text with pdftotext -layout / smalot
 * (already used by OcrExtractor) and calls the SAME commercial LLM the engine
 * would, using the SAME prompt and schema.
 *
 * FORMATS
 *   .xlsx / .xls  one segment per meaningful sheet
 *   .pdf          text layer via `pdftotext -layout`, else smalot/pdfparser
 *   .pdf (scan)   the file itself goes to the vision model — OPT-IN, see
 *                 scannedPdfSegments(); the engine cannot do this at all
 *   .csv/.txt/    read straight off disk as text (encoding fixed, length capped)
 *   .tsv/.md
 *   .docx         text pulled out of word/document.xml with ZipArchive
 *   images        png / jpg / webp sent to the vision model as-is; gif / bmp
 *                 converted to PNG first (Gemini takes neither) — OPT-IN on the
 *                 same switch as a scanned PDF, since neither can be pre-screened
 *   .xlsb         NOT supported — PhpSpreadsheet cannot read it (engine uses pyxlsb)
 *   .doc          NOT supported — no reader for the legacy binary format
 *
 * OUTPUT IS DELIBERATELY IDENTICAL to extract.py's extract_file():
 *   { source_file, segment_count, risks: [ {...schema, _segment, _provider, _errors} ] }
 * so SmartUnderwritingExtractJob's row-writing loop, the review screen and the
 * create-wizard prefill all work unchanged. Keep it that way — the two
 * implementations must stay swappable.
 *
 * WHAT IT DOES NOT DO (by design, not oversight)
 *  - PII segments. The engine routes anything ID/contact/financial-shaped to a
 *    LOCAL model (Ollama) because customer PII must never leave Alpha infra
 *    (AD-POL-AI-GOV-001, non-waivable). There is no local model in the PHP
 *    container, so this class applies the same classifier and REFUSES those
 *    segments instead of sending them to a commercial provider. A refused
 *    segment comes back with an _error, exactly as if the local model were
 *    down — never silently uploaded.
 *  - .xlsb. PhpSpreadsheet cannot read the binary macro format (the engine uses
 *    pyxlsb). Callers must check supports() first and keep the sidecar for those.
 *
 * Port of: smart-uw-engine/{normalize,schema,providers,extract}.py — if the
 * prompt or schema changes there, change it here too.
 */
class PhpScheduleExtractor
{
    /**
     * The vendor that read the most recent TEXT segment, as opposed to the one
     * that was configured. complete() falls back to another provider when the
     * chosen one has no key, and _provider has to name the company that
     * actually received the data — see complete().
     */
    private string $providerUsed = '';

    /**
     * Extensions this PHP path can read.
     *
     * Brokers do not agree on a format: the same schedule arrives as a
     * workbook, a PDF, a CSV export, a Word document or a phone photograph of
     * a printout, and "Only .xlsx, .xls, .xlsb, .pdf accepted" simply moved
     * the work back to the underwriter. Everything here reaches the same
     * extractSegment(); only the reader in front of it differs.
     *
     * .xlsb still needs the Python engine — PhpSpreadsheet cannot read the
     * binary format (the engine uses pyxlsb) — and legacy binary .doc has no
     * reader in PHP either.
     */
    private const SUPPORTED = [
        'xlsx', 'xls',
        'pdf',
        'csv', 'txt', 'tsv', 'md',
        'docx',
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'tif', 'tiff',
    ];

    /** Read straight off disk as text. */
    private const TEXT_EXTS = ['csv', 'txt', 'tsv', 'md'];

    /** Image formats both vision providers accept as-is. */
    private const IMAGE_MIMES = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
    ];

    /** Accepted, but converted to PNG first — Gemini takes none of these. */
    private const IMAGE_CONVERT = ['gif', 'bmp', 'tif', 'tiff'];

    /**
     * Hard ceiling on a text-ish upload handed to the LLM, in characters.
     * A 5 MB CSV would blow the context window and the bill; the row cap does
     * the same job for workbooks.
     */
    private const MAX_TEXT_CHARS = 200000;

    /**
     * Rows read per sheet. 400 mirrors normalize.py, which was raised from 120
     * after long fleet registers were truncated mid-fleet — silently dropping
     * insured vehicles from the extraction.
     */
    private const MAX_ROWS_PER_SHEET = 400;

    /**
     * Below this many characters, a PDF's "text layer" is not a schedule — it is
     * a scan with a stray header or a digital stamp. Mirrors OcrExtractor, which
     * uses the same 100-char floor to decide text-layer vs OCR.
     */
    private const MIN_PDF_TEXT_CHARS = 100;

    /**
     * Labels of what the last segment's redaction withheld. Held on the
     * instance because both PII refusals throw out of extractSegment(), and
     * the underwriter still has to be told which columns went — set by
     * redactPii(), read by the segment catch in extract(). Labels only, never
     * values.
     *
     * @var string[]
     */
    private array $lastRedactedLines = [];

    /**
     * Largest scanned PDF we will inline to the provider. Base64 adds ~33%, and
     * the generateContent request cap is 20 MB, so 14 MB of raw bytes is the
     * safe ceiling. (The upload endpoint itself allows 25 MB.)
     */
    private const MAX_INLINE_PDF_BYTES = 14 * 1024 * 1024;

    /**
     * The Groq text model to fall back on when the configured one is refused.
     *
     * Same id OcrExtractor::callTextLlm runs KYC structuring on, so it is the
     * model this account is known to be able to call.
     */
    private const GROQ_FALLBACK_MODEL = 'llama-3.3-70b-versatile';

    /**
     * Replacement ids, best first, when Groq refuses the configured model.
     * Only ones the account actually lists get used - see pickGroqModel().
     */
    private const GROQ_PREFERRED_MODELS = [
        'llama-3.3-70b-versatile',
        'openai/gpt-oss-120b',
        'meta-llama/llama-4-maverick-17b-128e-instruct',
        'meta-llama/llama-4-scout-17b-16e-instruct',
        'llama-3.1-8b-instant',
    ];

    /** Filler sheet names that carry no schedule (normalize.py::_SKIP_EXACT). */
    private const SKIP_EXACT = ['sheet1', 'sheet2', 'sheet3'];

    /**
     * A sheet this big is a real schedule regardless of what it is called, so
     * the default-name rule above must not drop it. Set low on purpose: the
     * cost of keeping a filler sheet is one wasted LLM call, the cost of
     * dropping a real one is the entire extraction.
     */
    private const MIN_SUBSTANTIAL_ROWS = 5;
    private const MIN_SUBSTANTIAL_COLS = 3;

    /** Coverage-section buckets passed to the LLM (schema.py::COVERAGE_HINTS). */
    private const COVERAGE_HINTS = [
        'fire', 'buildings_combined', 'office_contents', 'business_interruption',
        'public_liability', 'products_liability', 'goods_in_transit',
        'motor', 'fidelity_guarantee', 'money', 'glass', 'theft',
        'machinery_breakdown', 'electronic_equipment', 'all_risks',
        'plant_all_risks', 'contractors_all_risks', 'marine', 'group_personal_accident',
        'employers_liability', 'professional_indemnity', 'loss_of_rent',
    ];

    /** True when this file type can be handled without the Python engine. */
    public static function supports(string $ext): bool
    {
        return in_array(strtolower(ltrim($ext, '.')), self::SUPPORTED, true);
    }

    /**
     * Extract one risk object per schedule segment (sheet, or the whole PDF).
     *
     * @param  string  $absolutePath  the schedule on local disk
     * @param  string  $ext           xlsx | xls | pdf
     * @param  string  $provider      'gemini' | 'deepseek' (already vault-resolved)
     * @param  string  $commercialKey API key for $provider; '' falls back to env
     * @param  bool    $forceLocal    caller detected a KYC/ID upload — refuse outright
     * @return array   extract.py-compatible payload
     */
    public function extract(
        string $absolutePath,
        string $ext,
        string $provider = 'gemini',
        string $commercialKey = '',
        bool $forceLocal = false
    ): array {
        $ext = strtolower(ltrim($ext, '.'));
        if (!self::supports($ext)) {
            throw new \RuntimeException(
                'The in-process extractor cannot read .' . $ext . ' files — the '
                . 'smartuw-engine sidecar is required for this format.'
            );
        }

        // Whole-file PII lock. Mirrors the job's PII_HINT filename check: a KYC
        // or ID document must go to the local model, which this path does not
        // have.
        //
        // A PDF gets read first. Its text layer is pulled locally by
        // poppler/pdfparser with no provider involved, so its CONTENT can be
        // classified and redacted per line exactly like a spreadsheet's — and
        // refusing it on the strength of its filename killed ordinary
        // schedules ("... Motor Trade Licence 2026.pdf") one local call short
        // of being readable, which is the one outcome the upload must never
        // have. Only a PDF with no text layer, and an image, genuinely cannot
        // be screened before it is sent, and those still refuse below.
        $pdfSegments = null;
        $pdfReadError = null;
        if ($forceLocal && $ext === 'pdf') {
            try {
                $pdfSegments = $this->pdfSegments($absolutePath);
            } catch (\Throwable $e) {
                // Keep it. A corrupt file, an encrypted one, a missing poppler
                // binary and the scanned-PDF opt-in message all land here, and
                // reporting every one of them as "looks like a KYC document"
                // throws away the only sentence that tells the operator what
                // to do about it.
                $pdfSegments  = null;
                $pdfReadError = $e->getMessage();
            }
            $textChars = 0;
            foreach ($pdfSegments ?? [] as $seg) {
                $textChars += mb_strlen((string) ($seg['text'] ?? ''));
            }
            if ($pdfSegments !== null && $textChars >= self::MIN_PDF_TEXT_CHARS) {
                // Screenable locally — carry on down the ordinary redact-then-send
                // path with the segments already in hand.
                $forceLocal = false;
            } else {
                $pdfSegments = null;
            }
        }

        if ($forceLocal) {
            throw new \RuntimeException(
                'This upload looks like a KYC/ID document, which must be read by the '
                . 'LOCAL model only (AD-POL-AI-GOV-001). The in-process extractor has '
                . 'no local model — run the smartuw-engine sidecar for this file.'
                . ($pdfReadError !== null
                    ? ' Reading its text layer to screen it instead did not work: '
                      . $pdfReadError
                    : '')
            );
        }

        $segments = match (true) {
            $pdfSegments !== null                 => $pdfSegments,
            $ext === 'pdf'                        => $this->pdfSegments($absolutePath),
            $ext === 'docx'                       => $this->docxSegments($absolutePath),
            in_array($ext, self::TEXT_EXTS, true) => $this->textSegments($absolutePath),
            $this->isImageExt($ext)               => $this->imageSegments($absolutePath, $ext),
            default                               => $this->sheetSegments($absolutePath),
        };

        $risks = [];
        foreach ($segments as $seg) {
            // Reset per segment HERE, not inside redactPii — a segment with no
            // PII never calls it, so a later segment that failed for an
            // unrelated reason (a dead model id, say) inherited the previous
            // sheet's withheld-column labels and told the underwriter it had
            // lost columns it never had.
            $this->lastRedactedLines = [];
            try {
                $risks[] = $this->extractSegment($seg, $provider, $commercialKey);
            } catch (\Throwable $e) {
                // One bad segment must not lose the others — same contract as
                // extract_file(), which records the error against the segment.
                $failed = [
                    '_segment' => $seg['name'],
                    '_error'   => class_basename($e) . ': ' . $e->getMessage(),
                ];
                // A segment refused for PII still owes the underwriter the
                // list of what was withheld — otherwise "every line was ID
                // data" arrives with no way to tell which columns went, and a
                // schedule that lost a column reads as one that never had it.
                if ($this->lastRedactedLines !== []) {
                    $failed['_redacted_lines'] = $this->lastRedactedLines;
                }
                $risks[] = $failed;
            }
        }

        return [
            'source_file'   => basename($absolutePath),
            'segment_count' => count($segments),
            'risks'         => $risks,
        ];
    }

    // ── segmentation (port of normalize.py) ────────────────────────────────

    /**
     * One segment per meaningful worksheet, rendered as pipe-joined rows.
     *
     * The layout is deliberately NOT normalised into fixed columns: brokers
     * send wildly different shapes (one sheet per branch, one per subsidiary,
     * one multi-section sheet), so the LLM is handed clean text and left to map
     * it onto the schema — same choice as the Python engine.
     */
    private function sheetSegments(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        // Read filter, not a post-load trim: it stops PhpSpreadsheet
        // materialising a 20k-row fleet register into memory at all. Cell
        // FORMATTING is intentionally still loaded (no setReadDataOnly) so
        // toArray() below yields '01/04/2026' rather than the serial 46113,
        // which the LLM would read as a sum insured.
        $reader->setReadFilter(new class implements IReadFilter {
            public function readCell($column, $row, $worksheetName = ''): bool
            {
                return $row <= PhpScheduleExtractor::maxRowsPerSheet() + 1;
            }
        });

        $book = $reader->load($path);
        $segments = [];
        // Decision trail — logged, so a 0-risk extraction can be explained
        // from the log instead of guessed at.
        $kept         = [];
        $skipped      = [];
        $rejected     = [];   // title => content length, for the last-resort keep
        $rejectedText = [];   // title => text

        foreach ($book->getWorksheetIterator() as $sheet) {
            // $calculateFormulas MUST be true. Broker schedules compute nearly
            // every money column (premium = sum insured × rate, section totals
            // = SUM(...)), and with it false toArray() returns the formula TEXT
            // — the LLM would receive "=B2*C2" where the premium belongs and
            // extract a null or a nonsense figure. Verified against
            // PhpSpreadsheet 1.30.4: false yields "=B2*C2", true yields 3151.2,
            // and an unsupported function (XLOOKUP) yields '' rather than
            // throwing. openpyxl's data_only=True gives the engine the same
            // cached values, so this keeps the two implementations aligned.
            try {
                $rows = $sheet->toArray(null, true, true, false);
            } catch (\Throwable $e) {
                // A formula the calculation engine cannot evaluate at all.
                // Formula text is poor input but far better than dropping an
                // entire schedule sheet, so degrade rather than fail.
                Log::warning('PhpScheduleExtractor: formula evaluation failed, falling back to raw cells', [
                    'sheet' => $sheet->getTitle(), 'error' => $e->getMessage(),
                ]);
                $rows = $sheet->toArray(null, false, true, false);
            }

            $lines = [];
            $cols  = 0;
            foreach ($rows as $row) {
                $cells = array_map(fn($v) => $v === null ? '' : (string) $v, $row);
                $cols  = max($cols, count($cells));
                // Drop wholly blank rows — brokers pad sheets with them and
                // they cost prompt tokens for nothing.
                if (trim(implode('', $cells)) === '') {
                    continue;
                }
                $lines[] = rtrim(implode(' | ', $cells), ' |');
            }

            $title  = $sheet->getTitle();
            $reason = !$lines ? 'no non-blank rows' : $this->fillerReason($title, count($lines), $cols);
            if ($reason !== null) {
                $skipped[$title] = $reason;
                if ($lines) {
                    $body = implode("\n", $lines);
                    $rejected[$title]     = mb_strlen($body);
                    $rejectedText[$title] = $body;
                }
                continue;
            }
            // Truncation must be VISIBLE. A 600-vehicle fleet register was cut
            // to 400 rows with the marker going into the prompt only: the risk
            // came back clean, no error and no discrepancy, and 200 insured
            // vehicles were simply missing with nothing anywhere to say so.
            $truncated = null;
            if (count($rows) > self::maxRowsPerSheet()) {
                $lines[] = '... (truncated)';
                $truncated = count($rows) - self::maxRowsPerSheet();
            }

            $kept[] = $title;
            $segments[] = [
                'name'      => $title,
                'kind'      => 'sheet',
                'text'      => implode("\n", $lines),
                'truncated' => $truncated,
            ];
        }

        // LAST RESORT: every sheet was rejected. Extracting nothing from a
        // workbook the operator can plainly see has content is the worst
        // outcome available — worse than one wasted LLM call on a summary tab —
        // so keep the sheet with the most content and say so.
        if (!$segments && $rejected) {
            arsort($rejected);
            $title = (string) array_key_first($rejected);
            Log::warning('PhpScheduleExtractor: every sheet was filtered out — keeping the largest anyway', [
                'sheet' => $title, 'chars' => $rejected[$title], 'skipped' => $skipped,
            ]);
            $segments[] = ['name' => $title, 'kind' => 'sheet', 'text' => $rejectedText[$title]];
            $kept[] = $title . ' (last resort)';
        }

        Log::info('PhpScheduleExtractor: sheet selection', [
            'kept' => $kept, 'skipped' => $skipped,
        ]);

        $book->disconnectWorksheets();
        unset($book);

        if (!$segments) {
            throw new \RuntimeException(
                'No readable sheet in this workbook — every sheet was empty. '
                . ($skipped ? 'Sheets seen: ' . implode('; ', array_map(
                    fn($n, $r) => "{$n} ({$r})", array_keys($skipped), $skipped)) : 'None found.')
            );
        }

        return $segments;
    }

    /**
     * The PDF as one segment.
     *
     * Two readers, tried in order of table fidelity — which is the whole game
     * for a schedule, where a mangled column turns a rate into a sum insured:
     *
     *   1. `pdftotext -layout` (poppler). Preserves column alignment, so
     *      "Buildings   3 120 000   0.00101   3 151.20" survives as one row.
     *   2. smalot/pdfparser, page by page. Always available (it ships in the
     *      image) but flattens tables, so it is the fallback, not the default.
     *
     * A PDF with no usable text layer is a scan; that goes to
     * scannedPdfSegments() rather than being declared unreadable.
     */
    private function pdfSegments(string $path): array
    {
        $name = basename($path);

        $text = $this->pdfTextViaPoppler($path);
        $via  = 'pdftotext -layout';
        if ($text === null || mb_strlen(trim($text)) <= self::MIN_PDF_TEXT_CHARS) {
            $text = $this->pdfTextViaParser($path);
            $via  = 'smalot/pdfparser';
        }

        // Threshold, not emptiness: a scanned schedule often carries a few
        // characters of header text from a digital stamp or a cover letter,
        // which would pass an === '' test and then hand the LLM one useless
        // line instead of the schedule.
        if ($text === null || mb_strlen(trim($text)) <= self::MIN_PDF_TEXT_CHARS) {
            return $this->scannedPdfSegments($path, $name);
        }

        Log::info('PhpScheduleExtractor: PDF text layer read', [
            'file' => $name, 'via' => $via, 'chars' => mb_strlen($text),
        ]);

        // Capped like every other text reader. A generated PDF reached 326k
        // characters and was posted whole — the cap exists so one upload
        // cannot blow the context window and the bill.
        return [['name' => $name, 'kind' => 'pdf', 'text' => $this->capText($text, $name)]];
    }

    /** True for any image extension this reader accepts. */
    private function isImageExt(string $ext): bool
    {
        return isset(self::IMAGE_MIMES[$ext]) || in_array($ext, self::IMAGE_CONVERT, true);
    }

    /**
     * A plain-text schedule (CSV, TSV, Markdown, a pasted table) as one segment.
     *
     * No parsing: the LLM reads a delimited table perfectly well and a CSV
     * dialect guess would only mangle a quoted "1,250,000". Encoding is the one
     * thing worth fixing — Excel writes CSV as Windows-1252, and a mojibake
     * "P 1 250 000" costs a sum insured.
     */
    private function textSegments(string $path): array
    {
        $raw = (string) file_get_contents($path);
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252, ISO-8859-1');
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = $this->capText($raw, basename($path));

        if (trim($raw) === '') {
            throw new \RuntimeException('That file is empty — nothing to read.');
        }

        return [['name' => basename($path), 'kind' => 'text', 'text' => $raw]];
    }

    /**
     * A .docx as one text segment.
     *
     * A .docx is a zip holding word/document.xml, so the text comes out with
     * ZipArchive alone — no new dependency, and PhpSpreadsheet already needs
     * ext-zip. Paragraph and table markers become newlines and tabs first, or
     * strip_tags would run a whole schedule table into one line and every
     * column would be lost.
     */
    private function docxSegments(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException(
                'Reading .docx needs the PHP zip extension, which is not installed on this '
                . 'container. Save the schedule as PDF or CSV and upload that.'
            );
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('That .docx could not be opened — the file may be corrupt.');
        }

        $xml = '';
        foreach (['word/document.xml', 'word/document2.xml'] as $part) {
            $found = $zip->getFromName($part);
            if ($found !== false) {
                $xml = (string) $found;
                break;
            }
        }
        $zip->close();

        if ($xml === '') {
            throw new \RuntimeException(
                'No document body found inside that .docx. If it was saved by a non-Word '
                . 'editor, export it to PDF and upload that instead.'
            );
        }

        // Cell -> tab, row -> newline, paragraph -> newline. ORDER MATTERS: a
        // table cell wraps its text in its own <w:p>, so turning every </w:p>
        // into a newline first splits "Estimated carry per annum" from
        // "4500000" and the row stops being a row. A cell's LAST paragraph
        // break is therefore consumed by the cell boundary instead.
        $xml  = preg_replace('~</w:p>\s*</w:tc>~', '</w:tc>', $xml) ?? $xml;
        $xml  = str_replace(['</w:tc>', '</w:tr>', '</w:p>', '<w:br/>', '<w:tab/>'],
                            ["\t", "\n", "\n", "\n", "\t"], $xml);
        $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = $this->capText(trim($text), basename($path));

        if ($text === '') {
            throw new \RuntimeException(
                'That .docx holds no readable text — if the schedule is an embedded image, '
                . 'upload the image itself.'
            );
        }

        return [['name' => basename($path), 'kind' => 'text', 'text' => $text]];
    }

    /**
     * A photograph or scan of a schedule, sent to the vision provider whole.
     *
     * Governed exactly like a scanned PDF, and for the same reason: nothing can
     * pre-screen a picture for Omang / bank / contact data before it leaves,
     * so it rides the same opt-in (AD-POL-AI-GOV-001). Never a silent bypass of
     * the PII gate just because the format changed.
     */
    private function imageSegments(string $path, string $ext): array
    {
        if (!$this->scannedPdfAllowed()) {
            throw new \RuntimeException(
                'This is an image of a schedule, which can only be read by sending the '
                . 'picture to the AI provider. Nothing can pre-screen an image for '
                . 'Omang / bank / contact data first, so that is disabled by default '
                . '(AD-POL-AI-GOV-001). Upload the workbook instead, or set '
                . 'SMARTUW_ALLOW_SCANNED_PDF=1 to allow images of COMMERCIAL schedules.'
            );
        }

        [$sendPath, $mime] = $this->normaliseImage($path, $ext);

        $bytes = (int) @filesize($sendPath);
        if ($bytes > self::MAX_INLINE_PDF_BYTES) {
            throw new \RuntimeException(sprintf(
                'That image is %.1f MB. Base64 inlining inflates it by ~33%%, which exceeds '
                . 'the provider request limit — photograph it at a lower resolution, or '
                . 'upload the workbook.',
                $bytes / 1048576
            ));
        }

        return [[
            'name' => basename($path),
            'kind' => 'image',
            'text' => '',
            'file' => $sendPath,
            'mime' => $mime,
        ]];
    }

    /**
     * Hand back a path both vision providers can read.
     *
     * Gemini accepts PNG / JPEG / WEBP only, so a GIF, BMP or TIFF is converted
     * to PNG in the system temp directory first. GD ships with the image, but
     * it cannot read TIFF — that case says so plainly instead of failing later
     * with an opaque provider error.
     *
     * @return array{0: string, 1: string}  path to send, mime type
     */
    private function normaliseImage(string $path, string $ext): array
    {
        if (isset(self::IMAGE_MIMES[$ext])) {
            return [$path, self::IMAGE_MIMES[$ext]];
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            throw new \RuntimeException(
                'A .' . $ext . ' image needs converting to PNG before it can be sent, and the '
                . 'PHP GD extension is not installed here. Save it as PNG or JPEG and re-upload.'
            );
        }

        $img = @imagecreatefromstring((string) file_get_contents($path));
        if ($img === false) {
            throw new \RuntimeException(
                'That .' . $ext . ' image could not be decoded'
                . ($ext === 'tif' || $ext === 'tiff'
                    ? ' — PHP cannot read TIFF. Save it as PNG, JPEG or PDF and re-upload.'
                    : '. Save it as PNG or JPEG and re-upload.')
            );
        }

        // tempnam() creates the stub file itself, so appending '.png' would
        // leave TWO files behind per upload on a long-lived container. Write
        // the PNG over the stub and keep the one path.
        $out = tempnam(sys_get_temp_dir(), 'smartuw_');
        imagepng($img, $out);
        imagedestroy($img);

        Log::info('PhpScheduleExtractor: image converted for the vision provider', [
            'file' => basename($path), 'from' => $ext, 'to' => 'png',
        ]);

        return [$out, 'image/png'];
    }

    /** Trim an over-long text upload to what the model can actually be sent. */
    private function capText(string $text, string $name): string
    {
        if (mb_strlen($text) <= self::MAX_TEXT_CHARS) {
            return $text;
        }

        Log::warning('PhpScheduleExtractor: text upload truncated', [
            'file' => $name, 'chars' => mb_strlen($text), 'cap' => self::MAX_TEXT_CHARS,
        ]);

        return mb_substr($text, 0, self::MAX_TEXT_CHARS);
    }

    /**
     * `pdftotext -layout` when poppler-utils is on the box. Returns null when
     * the binary is missing or fails, so the caller can fall back.
     *
     * OcrExtractor already relies on this binary being present on the deployed
     * images (its own PDF path shells out to plain `pdftotext`), so this is a
     * reuse of a known-available tool rather than a new dependency — and the
     * null return means a container without it simply uses the parser instead.
     */
    private function pdfTextViaPoppler(string $path): ?string
    {
        if (!function_exists('exec')) {
            return null;
        }

        $out  = [];
        $code = 1;
        // -layout keeps the column geometry; '-' writes to stdout. The stderr
        // sink is platform-specific: on Windows dev boxes (no poppler) '/dev/null'
        // is not a path and cmd prints "The system cannot find the path
        // specified" straight into the CLI output of `smartuw:run`.
        $devNull = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
        @exec('pdftotext -layout ' . escapeshellarg($path) . ' - ' . $devNull, $out, $code);
        if ($code !== 0 || !$out) {
            return null;
        }

        return implode("\n", $out);
    }

    /** smalot/pdfparser, page by page so page breaks survive as blank lines. */
    private function pdfTextViaParser(string $path): ?string
    {
        if (!class_exists(\Smalot\PdfParser\Parser::class)) {
            return null;
        }

        try {
            $pdf   = (new \Smalot\PdfParser\Parser())->parseFile($path);
            $pages = $pdf->getPages();
            if ($pages) {
                $chunks = [];
                foreach ($pages as $i => $page) {
                    try {
                        $chunks[] = '--- page ' . ($i + 1) . " ---\n" . $page->getText();
                    } catch (\Throwable $e) {
                        // One unreadable page must not lose the rest.
                        $chunks[] = '--- page ' . ($i + 1) . " (unreadable) ---";
                    }
                }

                return implode("\n", $chunks);
            }

            return (string) $pdf->getText();
        } catch (\Throwable $e) {
            Log::warning('PhpScheduleExtractor: pdfparser failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Scanned (image-only) PDF — hand the FILE ITSELF to the vision model.
     *
     * Note what makes this different from every other path here: there is no
     * text, so the PII classifier has nothing to screen. Sending the document
     * blind would defeat the gate that keeps ID/contact data off commercial
     * providers, so it is OPT-IN (SMARTUW_ALLOW_SCANNED_PDF=1) and the message
     * below tells the operator exactly what enabling it means. The filename
     * KYC guard still applies to a scan: extract() now reads a PDF's text
     * layer before deciding, but a scan has none, so a KYC-named scan falls
     * back to the refusal exactly as before — only a text-layer PDF, which the
     * classifier can screen line by line, is allowed past on its content.
     *
     * The Python engine cannot do this at all — pdfplumber returns empty text
     * for a scan and the LLM receives nothing — so this path is a capability
     * the sidecar does not have, not a workaround for its absence.
     */
    private function scannedPdfSegments(string $path, string $name): array
    {
        if (!$this->scannedPdfAllowed()) {
            throw new \RuntimeException(
                'This PDF is a scan (no text layer), so it can only be read by sending the '
                . 'document image to the AI provider. Nothing can pre-screen a scan for '
                . 'Omang / bank / contact data first, so that is disabled by default '
                . '(AD-POL-AI-GOV-001). Upload the workbook instead, or set '
                . 'SMARTUW_ALLOW_SCANNED_PDF=1 to allow scanned COMMERCIAL schedules.'
            );
        }

        $bytes = (int) @filesize($path);
        if ($bytes > self::MAX_INLINE_PDF_BYTES) {
            throw new \RuntimeException(sprintf(
                'This scanned PDF is %.1f MB. Base64 inlining inflates it by ~33%%, which '
                . 'exceeds the provider request limit — split it, or upload the workbook.',
                $bytes / 1048576
            ));
        }

        // Marked kind 'pdf_scan' so extractSegment() knows to send the file
        // rather than text. The text field carries the operator-facing note only.
        return [[
            'name' => $name,
            'kind' => 'pdf_scan',
            'text' => '',
            'file' => $path,
        ]];
    }

    /** Scanned-PDF opt-in. Vault first so the CFO can switch it without a redeploy. */
    private function scannedPdfAllowed(): bool
    {
        $vault = (string) VaultController::get('smartuw_allow_scanned_pdf', '');
        if ($vault !== '') {
            return in_array(strtolower($vault), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) env('SMARTUW_ALLOW_SCANNED_PDF', false);
    }

    /**
     * Filler-sheet filter. Returns the reason a sheet is filler, or null to keep
     * it — the reason is logged, because "we extracted nothing" is otherwise
     * impossible to explain after the fact.
     *
     * Adapted from normalize.py::_is_filler_sheet with one deliberate
     * difference: the default-name rule (Sheet1/2/3) YIELDS TO SUBSTANCE.
     * normalize.py drops those names outright, which silently discards the
     * whole schedule when a broker exports a single sheet and never renames it
     * — the "Extraction complete — 0 risks" case seen on staging 2026-09-01.
     * Excel's default name is weak evidence of filler; 40 rows × 12 columns of
     * content is strong evidence against it, so content wins.
     *
     * The "<Location> 2026" rule is unchanged: those stubs are ~54 rows but
     * narrow (<=6 cols), and they duplicate a real schedule sheet elsewhere in
     * the same workbook, so width is what distinguishes them.
     */
    private function fillerReason(string $name, int $rows, int $cols): ?string
    {
        if ($rows <= 2) {
            return "only {$rows} non-blank row(s)";
        }
        if ($cols <= 1) {
            return 'single column';
        }

        $low = strtolower(trim($name));
        if (in_array($low, self::SKIP_EXACT, true)) {
            // Substantial enough to be a real schedule despite the lazy name?
            if ($rows >= self::MIN_SUBSTANTIAL_ROWS && $cols >= self::MIN_SUBSTANTIAL_COLS) {
                return null;
            }

            return "default sheet name '{$name}' with little content ({$rows}x{$cols})";
        }

        // A rate stub is narrow AND short. Width alone threw away a 30-vehicle
        // fleet register called "Fleet 2026" — every insured vehicle on the
        // policy, gone, with no error and no discrepancy. The year is also read
        // from the clock rather than hard-coded, or the rule dies in 2027.
        $years = [date('Y'), (string) ((int) date('Y') + 1), (string) ((int) date('Y') - 1)];
        $endsInYear = (bool) preg_match('/\b(' . implode('|', $years) . ')$/', trim($name));
        if ($endsInYear && $cols <= 6 && $rows < self::MIN_SUBSTANTIAL_ROWS * 2) {
            return "narrow '<name> {$years[0]}' rate stub ({$rows}x{$cols})";
        }

        return null;
    }

    /** Row cap, env-overridable exactly like SMARTUW_MAX_ROWS_PER_SHEET. */
    public static function maxRowsPerSheet(): int
    {
        $v = (int) env('SMARTUW_MAX_ROWS_PER_SHEET', self::MAX_ROWS_PER_SHEET);

        return $v > 0 ? $v : self::MAX_ROWS_PER_SHEET;
    }

    // ── one segment -> one risk object (port of extract.py) ────────────────

    private function extractSegment(array $segment, string $provider, string $key): array
    {
        $name = $segment['name'];
        $text = (string) ($segment['text'] ?? '');
        // An image of a schedule travels the same way a scanned PDF does: no
        // text to screen, so the same opt-in gates it and the same vision call
        // reads it.
        $isScan = in_array($segment['kind'] ?? '', ['pdf_scan', 'image'], true);

        // PII gate FIRST and non-overridable, mirroring providers.py::route_for.
        // A commercial provider may only ever see already-non-PII data. A scan
        // has no text to screen — that case is gated separately, at the opt-in
        // in scannedPdfSegments(), and is never silently waved through here.
        $redactedLines = [];
        if (!$isScan && $this->looksLikePii($text)) {
            // ANONYMISE, don't abandon. Refusing outright made the feature
            // useless in practice: every broker schedule carries a "CONTACT
            // PERSON / CONTACT NUMBER" block on its cover, so every real file
            // tripped the gate (the Diesel Heads schedule, 2026-09-01). The
            // engine's answer is to route the whole segment to a local model,
            // but there is no local model in this container — and none in the
            // ECS task either, so that route fails there too.
            //
            // The identifiers are confined to a handful of labelled lines; the
            // schedule itself (cover sections, sums insured, rates, premiums)
            // carries none. So drop those lines and send the rest. This is the
            // anonymisation workaround the DPA standard asks for, and it sends
            // strictly LESS than an unfiltered upload would.
            if (!$this->redactionAllowed()) {
                throw new \RuntimeException(
                    'Segment contains ID / contact / financial identifiers. Anonymise-then-send '
                    . 'is disabled (SMARTUW_REDACT_PII=0), so this segment was not read. Enable '
                    . 'it, remove those columns, or run the sidecar with a local model.'
                );
            }

            [$text, $redactedLines] = $this->redactPii($text);

            // Fail-closed: if anything identifier-shaped survived the pass, the
            // redactor did not understand this layout — refuse rather than
            // guess. Never "send anyway because we tried".
            // Fail-closed, but line by line — the same unit redaction works in.
            // A marker can span a line break in a wrapped cell ("proof of

            // residence"), which the whole-text check sees and no single line
            // does. Redaction then removes nothing, the whole-text re-check
            // fires, and the operator is told to remove columns that do not
            // exist. If no LINE is an identifier, there is nothing left to
            // remove and nothing identifying to send.
            $piiLine = null;
            // \R = any line ending. Written as an escape, never as raw CR/LF
            // characters in the pattern: git's autocrlf rewrites those on every
            // Windows round-trip, which had already eaten the carriage-return
            // alternative.
            foreach (preg_split('/\R/', $text) ?: [] as $line) {
                if ($this->looksLikePii($line)) {
                    $piiLine = $line;
                    break;
                }
            }
            if ($piiLine === null && $this->looksLikePii($text)) {
                Log::info('PhpScheduleExtractor: cross-line PII marker treated as a wrapped '
                    . 'cell — no single line is an identifier', ['segment' => $name]);
            }

            if ($piiLine !== null) {
                throw new \RuntimeException(
                    'Segment still contains ID / contact / financial identifiers after '
                    . 'anonymisation, so it cannot be sent to a commercial AI provider '
                    . '(AD-POL-AI-GOV-001). Remove those columns and re-upload, or run '
                    . 'the smartuw-engine sidecar with a local model.'
                );
            }
            Log::info('PhpScheduleExtractor: PII lines removed before sending', [
                'segment' => $name, 'lines_removed' => count($redactedLines),
            ]);
        }

        $scanProvider = 'gemini';
        if ($isScan) {
            $mime = (string) ($segment['mime'] ?? 'application/pdf');
            $what = $mime === 'application/pdf' ? 'a PDF' : 'an image';
            // The passed key belongs to the MAPPING provider, which is now
            // DeepSeek by default — and DeepSeek takes no file part, so a scan
            // is read by Gemini or Anthropic. Handing DeepSeek's key to
            // callVisionWithFile would have it sent to Gemini as $passed and
            // rejected with a 400, so only pass it when it is Gemini's own.
            [$raw, $scanProvider] = $this->callVisionWithFile(
                $this->systemPrompt(),
                $this->userPrompt($name, '(the schedule is attached as ' . $what . ' — read it directly)'),
                (string) $segment['file'],
                $mime,
                $provider === 'gemini' ? $key : ''
            );
        } else {
            $raw = $this->complete($this->systemPrompt(), $this->userPrompt($name, $text), $provider, $key);
        }

        $obj = $this->parseJson($raw);
        $obj = $this->normaliseMotorRows($obj);
        $obj = self::normaliseClassification($obj);

        $obj['_segment']  = $name;
        // Tagged so the review screen and the extractions table show which
        // engine produced the row — the three paths can disagree. The TEXT path
        // reports the vendor complete() actually used, not the one configured:
        // with no DeepSeek key the read falls back to Gemini or Anthropic, and
        // filing that as "deepseek" names the wrong company in the audit trail.
        $obj['_provider'] = $isScan
            ? $scanProvider . '(php-scan)'
            : ($this->providerUsed !== '' ? $this->providerUsed : $provider) . '(php)';
        $obj['_errors']   = self::validate($obj);
        // Lines to look at, NOT reasons to fail: "please check" placements and
        // lines left for a person to classify. Kept apart from _errors so the
        // job can tell a shaky read from a broken one when it scores
        // confidence — an exception is normal, an error is not.
        $obj['_exceptions'] = self::classificationExceptions($obj);

        // Data the model never saw is a discrepancy, not a footnote. _errors is
        // what the review screen shows, so the underwriter is told here rather
        // than finding out when a vehicle is missing from the policy.
        if (!empty($segment['truncated'])) {
            $obj['_truncated_rows'] = (int) $segment['truncated'];
            $obj['_errors'][] = 'Only the first ' . self::maxRowsPerSheet() . ' rows of "'
                . $name . '" were read — ' . (int) $segment['truncated'] . ' more row(s) were not '
                . 'sent. Split the sheet, or raise SMARTUW_MAX_ROWS_PER_SHEET, then re-upload.';
        }
        // Surfaced to the review screen: the underwriter must know these fields
        // were withheld from the AI, so a blank customer phone reads as "not
        // sent" rather than "not in the schedule".
        if ($redactedLines) {
            $obj['_redacted_lines'] = $redactedLines;
        }

        return $obj;
    }

    /**
     * Remove the identifier-bearing lines from a segment, returning the cleaned
     * text and the LABELS of what was dropped (labels only — never the values,
     * which would put the PII straight into the log and the DB row).
     *
     * Whole lines go, not just the values: the label itself ("Contact Number")
     * is what the classifier matches on, so masking only the value would leave
     * the segment permanently flagged and the fail-closed re-check would refuse
     * it. Dropping the line costs nothing — Graphite takes contact details from
     * the customer record, never from a broker's cover sheet.
     *
     * @return array{0: string, 1: string[]}  cleaned text, dropped labels
     */
    private function redactPii(string $text): array
    {
        $kept    = [];
        $dropped = [];

        // Column indexes a header row declared to be identifier columns. The
        // classifier matches a LABEL, never a value: an Omang register's
        // header ("Name | Omang | Address") is caught, but its data rows are
        // bare digits that are neither an email nor a 267 number, so every row
        // classified clean and went to the provider with the identifiers
        // intact. Remembering the column and blanking it on the rows below is
        // the piece that was missing — the branch already computes exactly
        // this and then threw it away.
        $piiColumns      = [];
        $columnsRedacted = 0;

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            if (trim($line) === '') {
                $kept[] = $line;
                continue;
            }

            // A new header row starts a new table, and its columns mean
            // something else. Without this reset an Omang register sitting
            // above a cover table blanked that table's SUM INSURED column,
            // which loses the schedule instead of protecting anybody.
            if ($piiColumns !== [] && $this->looksLikeHeaderRow($line)) {
                $piiColumns = [];
            }

            // Apply what the header taught us BEFORE the per-line test, which
            // is the test the data rows were escaping through.
            if ($piiColumns !== []) {
                $rowCells = $this->rowCells($line);
                if (count($rowCells) >= 3) {
                    $blanked = false;
                    foreach ($piiColumns as $i) {
                        if (isset($rowCells[$i]) && trim((string) $rowCells[$i]) !== '') {
                            $rowCells[$i] = '';
                            $blanked      = true;
                        }
                    }
                    if ($blanked) {
                        $line = str_contains($line, '|')
                            ? implode(' | ', $rowCells)
                            : implode(',', $rowCells);
                        $columnsRedacted++;
                    }
                }
            }

            if (!$this->looksLikePii($line)) {
                $kept[] = $line;
                continue;
            }

            // Cell-level first. Dropping the whole line cost a header row its
            // column names the moment one CONTACT column sat at the end of it
            // ("SECTION,SUM INSURED,RATE,PREMIUM,CONTACT NUMBER" left the model
            // guessing which number was the rate). Keep the cells that are
            // clean and blank only the ones that are not.
            // Only for a TABLE row — three or more cells, and the offending
            // cell is not the first. A two-cell "CONTACT PERSON | Kabelo
            // Modise" is a label/value block: blanking just the label would
            // leave the person's name behind, because the classifier is scoped
            // to identifiers and a bare name is not one. Those still go whole.
            $cells = $this->rowCells($line);
            $hits  = array_keys(array_filter(
                $cells,
                fn ($cell) => $this->looksLikePii((string) $cell)
            ));
            if (count($cells) >= 3 && $hits !== [] && !in_array(0, $hits, true)) {
                $clean = array_map(
                    fn ($cell) => $this->looksLikePii((string) $cell) ? '' : $cell,
                    $cells
                );
                $rebuilt = str_contains($line, '|')
                    ? implode(' | ', $clean)
                    : implode(',', $clean);
                if (!$this->looksLikePii($rebuilt) && trim(str_replace([',', '|'], '', $rebuilt)) !== '') {
                    $kept[] = $rebuilt;
                    foreach ($cells as $i => $cell) {
                        if ($clean[$i] === '' && trim((string) $cell) !== '') {
                            $dropped[] = $this->safeLabel((string) $cell);
                        }
                    }
                    // This row declared these columns to be identifier
                    // columns. Every row below it is scrubbed at the same
                    // indexes — see $piiColumns at the top of this method.
                    $piiColumns = array_values(array_unique(array_merge($piiColumns, $hits)));
                    continue;
                }
            }

            // Label only — NEVER the value. Splitting on '|' alone was true
            // for sheet rows and false for every other reader: a CSV, TXT, MD
            // or PDF-text line has no pipe, so the "label" was the whole line
            // and the identifier it had just removed was written into
            // smart_uw_extractions.extracted_json and shown on the review
            // screen. Split on any cell separator, then scrub anything that
            // still looks like a value out of what remains.
            $dropped[] = $this->safeLabel($line);
        }

        // Say that the column was scrubbed on the rows below, without saying
        // what was in it. Counted, not listed: one entry per row would bury
        // the labels that actually tell the underwriter what is missing.
        if ($columnsRedacted > 0) {
            $dropped[] = $columnsRedacted . ' further row(s) — identifier column values withheld';
        }

        // Hold the labels where the segment-level catch in extract() can still
        // reach them. Both PII refusals below unwind out of extractSegment,
        // and that catch rebuilds the risk as {_segment, _error} — so the
        // labels were being computed and then thrown away, leaving the
        // underwriter with "every line was ID data" and no idea which columns
        // went. The sidecar returns them instead of raising (extract.py), and
        // this is the same contract by another route.
        $this->lastRedactedLines = $dropped;

        // Everything was PII. Sending an empty SCHEDULE CONTENT block would be
        // billed by the provider and come back as a risk with no error at all,
        // which reads to the underwriter as "the schedule had nothing in it".
        if (trim(implode('', $kept)) === '' && $dropped !== []) {
            throw new \RuntimeException(
                'Every line of this segment was ID / contact / financial data, so nothing '
                . 'was left to read. It looks like a KYC or contact document rather than a '
                . 'schedule — upload the schedule itself.'
            );
        }

        return [implode("\n", $kept), $dropped];
    }

    /**
     * Is this row a column-heading row rather than a data row?
     *
     * Used only to decide when a learned identifier column stops applying:
     * headings carry no figures, so a row where no cell reads as a number
     * starts a new table. Deliberately conservative — mistaking a data row for
     * a heading merely stops the scrubbing early, so the caller re-learns from
     * the next label it sees.
     */
    /**
     * Split one line into its cells on the delimiter the line ACTUALLY uses:
     * '|' when it has one, ',' otherwise.
     *
     * Never on both. Splitting on '/\s*\|\s*|,/' broke every money column in a
     * pipe-delimited row, because toArray() is called with $formatData = true so
     * a broker workbook's figures arrive already carrying thousands separators:
     * a sum insured of "1,250,000" split into three cells and was re-joined as
     * "1 | 250 | 000", and a premium of "18,750" as "18 | 750". The model then
     * read a nonsense figure with nothing to flag it. It also shifted every cell
     * index to the right of the first comma, so the $piiColumns indexes learned
     * from a header no longer addressed the same columns on the rows below —
     * blanking a SUM INSURED or PREMIUM column while leaving the identifier.
     *
     * The re-join sites already picked their delimiter this way; splitting the
     * same way is what makes split and re-join agree.
     */
    private function rowCells(string $line): array
    {
        return str_contains($line, '|')
            ? (preg_split('/\s*\|\s*/', $line) ?: [])
            : (preg_split('/\s*,\s*/', $line) ?: []);
    }

    private function looksLikeHeaderRow(string $line): bool
    {
        $cells = array_values(array_filter(
            array_map('trim', $this->rowCells($line)),
            fn ($cell) => $cell !== ''
        ));
        if (count($cells) < 2) {
            return false;
        }

        foreach ($cells as $cell) {
            // A figure in any form a schedule prints one: 1000000, 1,000,000.00,
            // P 12 500, 10%.
            if (preg_match('/\d/', (string) $cell)
                && is_numeric(str_replace([',', ' ', 'P', 'p', '%'], '', (string) $cell))) {
                return false;
            }

            // A DATE is a value too, and is_numeric() never says so — there is
            // no '/' or '-' in the strip list, so "01/01/2026" fell through as
            // though the cell were a column name. That made a row like
            // "Period | 01/01/2026 | Kabelo Molefe" read as a HEADER, which reset
            // $piiColumns and sent the contact person's name straight to the
            // commercial provider on the very first row under the header that had
            // just identified that column as an identifier column. Exactly the
            // layout the carry-forward exists to protect.
            if (preg_match('~^\d{1,4}[/-]\d{1,2}[/-]\d{1,4}$~', (string) $cell)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A label safe to store: never the value it labelled.
     *
     * _redacted_lines is written into smart_uw_extractions.extracted_json and
     * shown on the review screen, so anything that still looks like an
     * identifier is masked out of it first.
     */
    private function safeLabel(string $line): string
    {
        $first = trim((string) (preg_split('/[|,;:\t]/', $line)[0] ?? ''));
        $first = trim((string) preg_replace('/\S*\d{3,}\S*|\S+@\S+/', '…', $first));

        return $first !== '' ? mb_substr($first, 0, 40) : '(unlabelled line)';
    }

    /**
     * Anonymise-then-send switch. Default ON: with it off, a schedule carrying
     * an ordinary contact block cannot be read at all, which is how this
     * feature came to be unusable. Vault first so the CFO can revoke it without
     * a redeploy.
     */
    private function redactionAllowed(): bool
    {
        $vault = (string) VaultController::get('smartuw_redact_pii', '');
        if ($vault !== '') {
            return in_array(strtolower($vault), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) env('SMARTUW_REDACT_PII', true);
    }

    /**
     * Resolve the API key for a provider, sharing the credentials the KYC
     * document reader already runs on.
     *
     * WHY: Smart UW shipped with its own key chain (vault `gemini_api_key`,
     * then the GEMINI_API_KEY env var). On the deployed task that env var is
     * attached to the smartuw-engine SIDECAR only — the web/worker container
     * that runs THIS class never received it (deploy.yml) — so extraction
     * dead-ended on "No Gemini API key" while KYC PDF reading worked fine on
     * the same container. It works because OcrExtractor reads its keys from
     * AiConfigController::getSettings() (vault, then .env). Same account, same
     * endpoint, same credential: read it from there instead of asking the
     * operator to paste the key a second time.
     *
     * Order: key passed in by the job (vault, explicit) → Smart UW's own vault
     * entry → the shared AI config KYC uses → the provider's env var.
     */
    private function providerKey(string $provider, string $passed = ''): string
    {
        $passed = trim($passed);
        if ($passed !== '') {
            return $passed;
        }

        // Smart UW's own vault entry first: an app-saved key must beat a stale
        // env value (VaultController::get is env-first — see getVaultOnly).
        $vault = trim((string) VaultController::getVaultOnly($provider . '_api_key', ''));
        if ($vault !== '') {
            return $vault;
        }

        // The KYC reader's credentials. getSettings() carries groq/anthropic/
        // gemini only; DeepSeek is Smart-UW-specific and has no KYC equivalent.
        $shared = AiConfigController::getSettings();
        $key = trim((string) ($shared[$provider . '_api_key'] ?? ''));
        if ($key !== '') {
            return $key;
        }

        return trim((string) env(strtoupper($provider) . '_API_KEY', ''));
    }

    /**
     * Anthropic model to use, sanitised.
     *
     * getSettings() defaults `anthropic_model` to env('AI_MODEL'), which on
     * this deployment holds a GROQ llama id (.env.example line 67) — sending
     * that to api.anthropic.com 404s. Only take the configured value when it
     * actually names a Claude model.
     */
    private function anthropicModel(): string
    {
        $model = trim((string) (AiConfigController::getSettings()['anthropic_model'] ?? ''));

        return str_starts_with(strtolower($model), 'claude') ? $model : 'claude-sonnet-4-6';
    }

    /**
     * Groq model to use — the vault first, then the shared AI config.
     *
     * `groq_model` resolves through VaultController::get(), which is env-FIRST,
     * so the deployment's AI_MODEL beats anything saved in the app. That is
     * wrong for a value the operator can edit in the AI card: AI_MODEL held a
     * retired llama-4-scout id and no vault entry could displace it, because
     * only AWS can edit the task definition. Read the vault entry first (the
     * rule getVaultOnly() documents for exactly this case), then fall back.
     */
    private function groqModel(): string
    {
        $vault = trim((string) VaultController::getVaultOnly('groq_model', ''));
        if ($vault !== '') {
            return $vault;
        }

        $model = trim((string) (AiConfigController::getSettings()['groq_model'] ?? ''));

        return $model !== '' ? $model : self::GROQ_FALLBACK_MODEL;
    }

    /**
     * Vision call for a scanned schedule: the PDF travels inline.
     *
     * Gemini first (native PDF input, and the provider the engine uses), with
     * Anthropic as the fallback — that is precisely the capability KYC PDF
     * reading runs on (OcrExtractor::callVisionAi sends a PDF as a `document`
     * block), so a container holding only an Anthropic key can still read a
     * scan instead of dead-ending. DeepSeek's chat API takes no PDF part at
     * all, so it is never a candidate here.
     *
     * @return array{0: string, 1: string}  raw response text, provider used
     */
    private function callVisionWithFile(
        string $system,
        string $user,
        string $filePath,
        string $mime,
        string $key
    ): array {
        $geminiKey = $this->providerKey('gemini', $key);
        if ($geminiKey !== '') {
            return [$this->callGeminiWithFile($system, $user, $filePath, $mime, $geminiKey), 'gemini'];
        }

        $anthropicKey = $this->providerKey('anthropic');
        if ($anthropicKey !== '') {
            Log::info('PhpScheduleExtractor: no Gemini key — reading the scan with '
                . 'Anthropic, the same provider KYC PDF reading uses');

            return [$this->callAnthropicWithFile($system, $user, $filePath, $mime, $anthropicKey), 'anthropic'];
        }

        throw new \RuntimeException(
            'Reading a scan or an image needs Gemini or Anthropic — DeepSeek takes no file part '
            . 'and Groq vision cannot read PDFs either — and neither key is available: not in '
            . 'the Credentials Vault (gemini_api_key / anthropic_api_key), not in the AI config '
            . 'KYC document reading uses, and not in this container\'s environment.'
        );
    }

    /**
     * Send the file itself to Anthropic — as KYC does.
     *
     * A PDF rides in a `document` block; an image must ride in an `image`
     * block. Sending an image as a document is a 400 from the API, which is
     * why the block type is chosen from the mime rather than hard-coded.
     */
    private function callAnthropicWithFile(
        string $system,
        string $user,
        string $filePath,
        string $mime,
        string $key
    ): string {
        $resp = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(300)   // a 30-page scan is slower than a text prompt
            ->post('https://api.anthropic.com/v1/messages', [
                'model'       => $this->anthropicModel(),
                'max_tokens'  => 8192,
                'temperature' => 0.0,
                'system'      => $system,
                'messages'    => [[
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => $mime === 'application/pdf' ? 'document' : 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $mime,
                                'data'       => base64_encode((string) file_get_contents($filePath)),
                            ],
                        ],
                        ['type' => 'text', 'text' => $user],
                    ],
                ]],
            ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Anthropic (file) ' . $resp->status() . ': ' . substr($resp->body(), 0, 300));
        }
        $raw = (string) $resp->json('content.0.text', '');
        if (trim($raw) === '') {
            throw new \RuntimeException('Anthropic (file) returned no text (stop_reason='
                . $resp->json('stop_reason', 'unknown') . ')');
        }

        return $raw;
    }

    /** Text completion via Anthropic — the last-resort share of the KYC key. */
    private function callAnthropic(string $system, string $user, string $key): string
    {
        $resp = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(180)->post('https://api.anthropic.com/v1/messages', [
            'model'       => $this->anthropicModel(),
            'max_tokens'  => 8192,
            'temperature' => 0.0,
            'system'      => $system,
            'messages'    => [['role' => 'user', 'content' => $user]],
        ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Anthropic ' . $resp->status() . ': ' . substr($resp->body(), 0, 300));
        }
        $raw = (string) $resp->json('content.0.text', '');
        if (trim($raw) === '') {
            throw new \RuntimeException('Anthropic returned no text (stop_reason='
                . $resp->json('stop_reason', 'unknown') . ')');
        }

        return $raw;
    }

    /** Inline the file for Gemini. mime_type decides how it is decoded. */
    private function callGeminiWithFile(
        string $system,
        string $user,
        string $filePath,
        string $mime,
        string $key
    ): string {
        $model = (string) env('SMARTUW_GEMINI_MODEL', 'gemini-2.5-flash');

        $resp = Http::withHeaders(['x-goog-api-key' => $key, 'Content-Type' => 'application/json'])
            ->timeout(300)   // a 30-page scan is slower than a text prompt
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents'           => [[
                    'role'  => 'user',
                    'parts' => [
                        ['inline_data' => [
                            'mime_type' => $mime,
                            'data'      => base64_encode((string) file_get_contents($filePath)),
                        ]],
                        ['text' => $user],
                    ],
                ]],
                'generationConfig' => [
                    'temperature'      => 0.0,
                    'maxOutputTokens'  => 8192,
                    'thinkingConfig'   => ['thinkingBudget' => 0],
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Gemini (file) ' . $resp->status() . ': ' . substr($resp->body(), 0, 300));
        }
        $raw = (string) $resp->json('candidates.0.content.parts.0.text', '');
        if (trim($raw) === '') {
            throw new \RuntimeException('Gemini (file) returned no text (finishReason='
                . $resp->json('candidates.0.finishReason', 'unknown') . ')');
        }

        return $raw;
    }

    /**
     * PII classifier — port of providers.py::classify_pii, including the
     * label-normalisation added by the Smart UW PII audit (2026-06-18) so
     * "OMANG_123", "ID.NO", "IDNumber", "Phone#" are all still caught.
     * Biases to safety: an ambiguous label counts as PII.
     *
     * Scoped to ID / contact / financial identifiers — NOT names or addresses,
     * which every commercial schedule carries by design.
     */
    private function looksLikePii(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $norm = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $text);        // camelCase -> spaced
        $norm = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1 $2', (string) $norm); // IDNumber -> ID Number
        $norm = str_replace(['.', '#'], ['', ' number '], (string) $norm);   // I.D. -> ID, ID# -> ID number
        $norm = preg_replace('/[_\-:;=\/]+/', ' ', (string) $norm);          // other delimiters -> space

        $markers = '/\b('
            . 'omang|passport|national\s*id|'
            . '(?:id|ident(?:ification|ity)?)\s*(?:no|number|#)|'
            . 'date\s*of\s*birth|dob|next\s*of\s*kin|'
            . 'proof\s*of\s*(?:residence|income)|driver\'?s?\s*licen[cs]e|'
            . 'social\s*security|bank\s*account|account\s*(?:no|number)|'
            // A contact word needs EVIDENCE that it is a contact field: either a
            // suffix ("CONTACT PERSON", "MOBILE NUMBER") or a value that starts
            // with digits ("Phone: 76517110"). Bare words matched before, and
            // the redactor then deleted whole cover lines — MOBILE PLANT ALL
            // RISKS, TELEPHONE INSTALLATION and FAX AND OFFICE EQUIPMENT were
            // all dropped out of a schedule, silently, and plant_all_risks is
            // one of this class's own COVERAGE_HINTS.
            . '(?:tel|phone|mobile|cell|fax|telephone|whatsapp|contact)'
            . '\s*(?:no|number|person|name|details|#)'
            . ')\b/i';

        if (preg_match($markers, (string) $norm)) {
            return true;
        }

        // The same words immediately followed by a number: "Phone: 76517110",
        // "Mobile,26771234567". A cover line ("TELEPHONE INSTALLATION 120000")
        // has words in between, so it is left alone.
        $markers = '/\b(?:tel|phone|mobile|cell|fax|telephone|whatsapp)\b'
            . '\s*[:,]?\s*\+?\d/i';

        if (preg_match($markers, (string) $norm)) {
            return true;
        }
        // Email / Botswana phone are matched on the ORIGINAL text — '.' and '+'
        // are part of their format and the normaliser strips them. The 267
        // prefix keeps this off plain sums insured and policy numbers.
        //
        // The phone pattern is anchored at a word boundary and must not be
        // followed by another digit. Unanchored, '267' matched INSIDE longer
        // numbers — a chassis "WDB26712345678" and a sum insured
        // "126712345678" both read as phone numbers, and because a
        // chassis-first row fails the cell-level test in redactPii() the WHOLE
        // line was dropped: one vehicle silently missing from the fleet.
        return (bool) (preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/', $text)
            || preg_match('/(?<![0-9A-Za-z])\+?267[\s\-]?\d{2}[\s\-]?\d{3}[\s\-]?\d{3}(?![0-9])/', $text));
    }

    // ── prompt (port of schema.py) ─────────────────────────────────────────

    private function systemPrompt(): string
    {
        return "You are a precise commercial-insurance underwriting data extractor for "
            . "Alpha Direct Insurance (Botswana). You read a broker policy schedule and "
            . "return STRICT JSON matching the given schema. Rules:\n"
            . "- Output JSON ONLY. No prose, no markdown fences.\n"
            . "- A schedule is organised in SECTIONS (Fire & Allied Perils, Buildings "
            . "Combined, Public Liability, Goods in Transit, Motor, etc.). Each section "
            . "becomes one entry in coverages[]; its line items become details[].\n"
            . "- Per-vehicle rows (registration + make/model + value) go in motor[], "
            . "NOT in coverages[].\n"
            . "- Map every section title to the closest coverage_hint; use 'other' if none fit.\n"
            . "- Within a section, split the lines by KIND into our four buckets: a "
            . "cover limit / sum insured line goes in details[] (Coverage); a named "
            . "extension, clause, warranty or memorandum in extensions[] (Extension); "
            . "a specified or miscellaneous item — named property carrying its own "
            . "value — in specified_items[] (Miscellaneous Item); an excess, "
            . "deductible or first amount payable in excesses[] (Excess), parsing "
            . "'10% ... min P10,000' into min_percent 10 and min_amount 10000 "
            . "while keeping the clause verbatim in text. Never put an excess "
            . "in details[]." . PHP_EOL
            // The classification contract, and the reason it is written this
            // hard: a line guessed into Excess or Coverage changes the
            // premium, and nothing downstream would ever flag it. An unplaced
            // line costs the underwriter one dropdown; a wrong one costs a
            // mispriced policy. So: never guess, never drop, flag what is
            // shaky, and leave what is genuinely unreadable for a person.
            . "- NEVER GUESS a bucket. If you cannot tell which of the four a line "
            . "belongs to, put it in that section's unclassified[] — the line text "
            . "verbatim, whatever figures it carries, and a short reason. A wrong "
            . "Excess or Coverage changes the premium, so an unplaced line is always "
            . "better than a guessed one.\n"
            . "- If you DO place a line but are not confident, place it and set "
            . "needs_check=true with a short check_reason. Every flagged line is read "
            . "by an underwriter, who can move it.\n"
            . "- A line that belongs to no section at all goes in the TOP-LEVEL "
            . "unclassified[], with whatever heading it sat under in `section`.\n"
            . "- Never drop a line. Every printed line ends up in exactly one of "
            . "details[], extensions[], specified_items[], excesses[] or unclassified[].\n"
            . "- The client's layout is never wrong — it is just their layout. Read "
            . "whatever wording, column order or spelling they used and map it to our "
            . "format; never expect a house format.\n"
            . "- Numbers: strip currency symbols/spaces ('P 3,120,000' -> 3120000; "
            . "'0.4%' -> 0.004; '0.00101' stays 0.00101). Keep null when truly absent.\n"
            . "- Dates -> YYYY-MM-DD. Frequency words -> annual/monthly/quarterly/semi_annual.\n"
            . "- entity_type is 'Organisation' for company schedules (Pty Ltd, Group, etc.).\n"
            . "- If a POLICY NUMBER appears, set existing_policy_number and is_renewal=true.\n"
            . "- Never invent values. Put uncertainties in notes.";
    }

    /** schema.py::TARGET_SCHEMA — the shape the LLM must return. */
    private function targetSchema(): array
    {
        return [
            'policy' => [
                'product_group'           => 'commercial|domestic',
                'existing_policy_number'  => 'string|null   # if this is a renewal of a live policy',
                'is_renewal'              => 'bool',
                'insurer'                 => 'string|null',
                'broker'                  => 'string|null',
                'account_executive'       => 'string|null',
                'premium_freq'            => 'annual|monthly|quarterly|semi_annual|null',
                'term_start_date'         => 'YYYY-MM-DD|null',
                'expiry_date'             => 'YYYY-MM-DD|null',
                'currency'                => 'string|null',
            ],
            'customer' => [
                'entity_type'      => 'Organisation|Individual',
                // The INSURED. This is the policy holder — the company on a
                // commercial schedule — and the wizard refuses to write a
                // schedule whose insured is not the policy's own holder, so
                // copy it verbatim from the schedule and never merge in a
                // contact person, broker or branch name.
                'name'             => 'string   # the INSURED exactly as printed',
                'company_reg_no'   => 'string|null',
                'vat_reg_no'       => 'string|null',
                // Any address on the schedule describes the RISK, whichever
                // label the broker used. Keep the label it came under; the
                // wizard reads all three into the risk address.
                'physical_address'    => 'string|null',
                'residential_address' => 'string|null   # if labelled "Residential Address"',
                'postal_address'   => 'string|null',
                'occupation'       => 'string|null   # business description',
                'email'            => 'string|null',
                'phone'            => 'string|null',
            ],
            'risk_location' => [
                'name'             => 'string|null   # branch/camp/site name for this segment',
                'physical_address' => 'string|null   # the site address, any label',
            ],
            'coverages' => [[
                'section'          => 'string   # raw section title from the schedule',
                'coverage_hint'    => "one of COVERAGE_HINTS, or 'other'",
                'section_premium'  => 'number|null',
                'details'          => [[
                    'description'  => 'string',
                    'sum_insured'  => 'number|null',
                    'rate'         => 'number|null   # decimal e.g. 0.00101 or % as given',
                    'premium'      => 'number|null',
                    // Flagged, not withheld. The line is still placed where
                    // the model thinks it belongs — the underwriter sees
                    // "please check" against it and can move it.
                    'needs_check'  => 'bool   # true when you are not sure this is a Coverage line',
                    'check_reason' => 'string|null   # one short line: what you were unsure about',
                ]],
                // A Graphite coverage carries three child buckets besides its
                // detail rows, and a schedule states all three inline. Split
                // here so the wizard can write them to
                // policy_coverage_extension / specified_coverage_items /
                // policy_coverage_excess rather than stranding them in prose.
                'extensions'       => [[
                    'name'         => 'string   # extension / clause / warranty title',
                    'sum_insured'  => 'number|null',
                    'premium'      => 'number|null',
                    'text'         => 'string|null   # wording, when the extension is words not a limit',
                    'needs_check'  => 'bool   # true when you are not sure this is an Extension',
                    'check_reason' => 'string|null',
                ]],
                'specified_items'  => [[
                    'name'         => 'string   # the item as named on the schedule',
                    'sum_insured'  => 'number|null',
                    'rate'         => 'number|null',
                    'premium'      => 'number|null',
                    'needs_check'  => 'bool   # true when you are not sure this is a Miscellaneous Item',
                    'check_reason' => 'string|null',
                ]],
                'excesses'         => [[
                    'text'         => 'string   # the clause verbatim',
                    'min_percent'  => "number|null   # 10 for '10% of the claim'",
                    'min_amount'   => "number|null   # 10000 for 'min P10,000.00'",
                    'needs_check'  => 'bool   # true when you are not sure this is an Excess',
                    'check_reason' => 'string|null',
                ]],
                // The fifth bucket, and the only honest answer for a line the
                // reader cannot place: NOT a guess. Nothing here is written to
                // the policy until an underwriter picks its bucket on the
                // review screen, so a line landing here costs one dropdown —
                // where a line guessed into Excess or Coverage would quietly
                // change the premium.
                'unclassified'     => [[
                    'text'         => 'string   # the line exactly as printed',
                    'sum_insured'  => 'number|null',
                    'rate'         => 'number|null',
                    'premium'      => 'number|null',
                    'reason'       => 'string|null   # why you could not place it',
                ]],
                'notes'            => 'string|null   # anything about THIS section the underwriter should read',
            ]],
            'motor' => [[
                'registration' => 'string|null',
                'make_model'   => 'string|null',
                'year'         => 'number|null',
                'sum_insured'  => 'number|null   # estimated/insured value',
                'rate'         => 'number|null',
                'premium'      => 'number|null',
            ]],
            // Lines that belong to no section at all. Same rule as the
            // per-section bucket: unplaced, never guessed.
            'unclassified' => [[
                'text'         => 'string   # the line exactly as printed',
                'section'      => 'string|null   # the heading it sat under, if any',
                'sum_insured'  => 'number|null',
                'rate'         => 'number|null',
                'premium'      => 'number|null',
                'reason'       => 'string|null',
            ]],
            'notes' => 'string|null   # anything ambiguous the underwriter should check',
        ];
    }

    private function userPrompt(string $segmentName, string $segmentText): string
    {
        return 'COVERAGE_HINTS = ' . json_encode(self::COVERAGE_HINTS) . "\n\n"
            . "SCHEMA (return exactly this shape):\n"
            . json_encode($this->targetSchema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n"
            . "SCHEDULE SEGMENT NAME: {$segmentName}\n"
            . "SCHEDULE CONTENT:\n-----\n{$segmentText}\n-----\n\n"
            . 'Return the JSON object now.';
    }

    // ── LLM call ───────────────────────────────────────────────────────────

    /**
     * Call the chosen commercial provider. Same two providers the engine
     * supports, so the CFO's Credentials Vault switch keeps working on this
     * path too. Never called for PII — extractSegment() gates that first.
     */
    private function complete(string $system, string $user, string $provider, string $key): string
    {
        $providerKey = $this->providerKey($provider, $key);
        // The vendor that ACTUALLY read the segment, which is not always the
        // one that was chosen: the fallback below silently swaps vendors when
        // the chosen provider has no key. _provider used to record the choice,
        // so a schedule read by Gemini or Anthropic was filed as "deepseek" —
        // an audit trail naming the wrong company as having seen the data.
        $this->providerUsed = $provider;

        // The chosen provider has no key, so fall back to whatever KYC document
        // reading runs on. Anthropic first (strongest at this schema), then
        // Groq — which is the DEFAULT provider for KYC (AI_PROVIDER=groq,
        // .env.example line 65), so on a container where only KYC works it is
        // the key most likely to be present. Same prompt, same schema, and the
        // segment is already past the PII gate above; only the vendor differs.
        if ($providerKey === '') {
            // Gemini is in this list because the MAPPING provider is now
            // DeepSeek by default: an install that has only ever held a Gemini
            // key (the previous default) must not stop reading schedules
            // because the default provider name changed under it. Anthropic
            // first (strongest at this schema), then Gemini, then Groq —
            // text-only, so last.
            foreach (['anthropic', 'gemini', 'groq'] as $fallback) {
                if ($fallback === $provider) {
                    continue;
                }
                $fallbackKey = $this->providerKey($fallback);
                if ($fallbackKey === '') {
                    continue;
                }
                Log::info('PhpScheduleExtractor: no ' . $provider . ' key — using '
                    . $fallback . ', a provider KYC document reading uses');

                $this->providerUsed = $fallback;

                return match ($fallback) {
                    'groq'   => $this->callGroq($system, $user, $fallbackKey),
                    'gemini' => $this->callGemini($system, $user, $fallbackKey),
                    default  => $this->callAnthropic($system, $user, $fallbackKey),
                };
            }

            // Nothing anywhere. One message naming every place looked, because
            // "No Gemini API key" sent operators hunting for a Gemini key when
            // any of four would have done (test env, 2026-09-03).
            throw new \RuntimeException(
                'No AI provider key available. Looked for ' . $provider . ', anthropic, '
                . 'gemini and groq keys in: the Credentials Vault (' . $provider . '_api_key), '
                . 'the AI config KYC document reading uses, and this container\'s environment '
                . '(' . strtoupper($provider) . '_API_KEY / ANTHROPIC_API_KEY / GEMINI_API_KEY '
                . '/ GROQ_API_KEY). Set one on the Smart Upload page (AI extraction engine → Set up).'
            );
        }

        // Route by name, never "everything that is not deepseek is Gemini".
        // With provider='groq' the old else-branch posted the GROQ key to
        // generativelanguage.googleapis.com as x-goog-api-key — a secret handed
        // to the wrong vendor. Only the job's own allow-list kept that off the
        // live path, and this method's fallback chain names groq and anthropic
        // as first-class providers.
        return match ($provider) {
            'deepseek'  => $this->callDeepseek($system, $user, $providerKey),
            'groq'      => $this->callGroq($system, $user, $providerKey),
            'anthropic' => $this->callAnthropic($system, $user, $providerKey),
            'gemini'    => $this->callGemini($system, $user, $providerKey),
            default     => throw new \RuntimeException(
                'Unknown AI provider "' . $provider . '". Supported: gemini, deepseek, '
                . 'anthropic, groq. Set it on the Smart Upload page (AI extraction engine).'
            ),
        };
    }

    /**
     * Text completion via Groq (OpenAI-style chat API).
     *
     * The default provider for KYC document reading, so on a container where
     * only KYC has ever been configured this is the key that exists. Mirrors
     * OcrExtractor::callTextLlm. Groq cannot read a PDF, so it is a text-path
     * fallback only — never a candidate in callVisionWithFile().
     */
    private function callGroq(string $system, string $user, string $key): string
    {
        $model = $this->groqModel();

        $resp = $this->postGroq($model, $system, $user, $key);

        // The configured id is one this Groq account cannot call. `groq_model`
        // defaults to env('AI_MODEL'), which on this deployment named a retired
        // llama-4-scout id (.aws/backend-task-def.json) — Groq answers 404
        // model_not_found and every schedule segment fails with it. Guessing a
        // replacement is no better: llama-3.3-70b-versatile 404'd on the same
        // key (test env, 2026-09-04), because the line-up is per-account. Ask
        // Groq which models this key may call, then retry on one of those.
        if ($this->groqRejectedModel($resp)) {
            $alternative = $this->pickGroqModel($key, $model);
            if ($alternative !== null) {
                Log::warning('PhpScheduleExtractor: Groq rejected the configured model — '
                    . 'retrying on ' . $alternative, ['configured_model' => $model]);
                $model = $alternative;
                $resp  = $this->postGroq($model, $system, $user, $key);
            }
        }

        if ($resp->failed()) {
            // A model_not_found that survived the retry is a credential or
            // configuration problem, not a bad schedule. Name what the key CAN
            // call so the operator fixes it in one look instead of guessing
            // model ids against a red banner.
            if ($this->groqRejectedModel($resp)) {
                throw new \RuntimeException(
                    'Groq refuses model "' . $model . '" for this API key. '
                    . $this->groqModelHint($key)
                );
            }

            throw new \RuntimeException('Groq ' . $resp->status() . ': ' . substr($resp->body(), 0, 300));
        }
        $raw = (string) $resp->json('choices.0.message.content', '');
        if (trim($raw) === '') {
            throw new \RuntimeException('Groq returned no text (finishReason='
                . $resp->json('choices.0.finish_reason', 'unknown') . ')');
        }

        return $raw;
    }

    /** True when Groq turned the request down over the model id itself. */
    private function groqRejectedModel(\Illuminate\Http\Client\Response $resp): bool
    {
        return $resp->status() === 404 && str_contains((string) $resp->body(), 'model_not_found');
    }

    /**
     * The chat models this Groq key may actually call.
     *
     * Cached per key for ten minutes: the list only moves when Groq retires
     * something, and a failing extraction must not add a round trip per
     * segment. An empty array means the listing itself failed — groqModelHint()
     * reports that rather than pretending the account has nothing.
     *
     * @return list<string>
     */
    private function groqModels(string $key): array
    {
        return Cache::remember(
            'smartuw.groq_models.' . substr(md5($key), 0, 12),
            600,
            function () use ($key) {
                $resp = Http::withHeaders(['Authorization' => 'Bearer ' . $key])
                    ->timeout(20)
                    ->get('https://api.groq.com/openai/v1/models');

                if ($resp->failed()) {
                    Log::warning('PhpScheduleExtractor: could not list Groq models', [
                        'status' => $resp->status(),
                        'body'   => substr((string) $resp->body(), 0, 200),
                    ]);

                    return [];
                }

                $ids = [];
                foreach ((array) $resp->json('data', []) as $entry) {
                    $id = trim((string) ($entry['id'] ?? ''));
                    if ($id !== '' && $this->isGroqChatModel($id)) {
                        $ids[] = $id;
                    }
                }

                return $ids;
            }
        );
    }

    /**
     * Drop ids that cannot answer a JSON extraction prompt — speech
     * (whisper/tts), safety classifiers (llama-guard, prompt-guard) and
     * embeddings all appear in the same listing.
     */
    private function isGroqChatModel(string $id): bool
    {
        $id = strtolower($id);
        foreach (['whisper', 'tts', 'guard', 'embed', 'moderation'] as $notChat) {
            if (str_contains($id, $notChat)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Pick a replacement model: the ids known to return clean JSON for this
     * schema first, then whatever else the account carries.
     */
    private function pickGroqModel(string $key, string $rejected): ?string
    {
        $available = array_values(array_filter(
            $this->groqModels($key),
            static fn (string $id): bool => $id !== $rejected
        ));

        foreach (self::GROQ_PREFERRED_MODELS as $preferred) {
            if (in_array($preferred, $available, true)) {
                return $preferred;
            }
        }

        if ($available !== []) {
            return $available[0];
        }

        // Listing failed or returned nothing usable. One blind attempt on the
        // model KYC document reading runs on still beats no attempt.
        return $rejected === self::GROQ_FALLBACK_MODEL ? null : self::GROQ_FALLBACK_MODEL;
    }

    /** Operator-facing tail for a model_not_found error. */
    private function groqModelHint(string $key): string
    {
        $available = $this->groqModels($key);
        if ($available === []) {
            return 'Groq listed no usable chat model for it either, so the key itself is the '
                . 'suspect — invalid, revoked, or on an account with no model access. The '
                . '/v1/models status is in the app log.';
        }

        return 'Models it can call: ' . implode(', ', array_slice($available, 0, 8))
            . (count($available) > 8 ? ', ...' : '')
            . '. Set one of those as groq_model in the Credentials Vault (or AI_MODEL).';
    }

    /** One Groq chat-completion request. Split out so the model can be retried. */
    private function postGroq(string $model, string $system, string $user, string $key): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders(['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'])
            ->timeout(180)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'           => $model,
                'temperature'     => 0.0,
                // A multi-section schedule's JSON runs long, and DeepSeek's
                // default output cap truncates it mid-object — which arrives
                // here as "Model did not return JSON" rather than as a length
                // error. Ask for the full budget instead. Gemini's own call
                // does the same (maxOutputTokens 8192).
                'max_tokens'      => 8192,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ]);
    }

    private function callGemini(string $system, string $user, string $key): string
    {
        $key = $this->providerKey('gemini', $key);
        if ($key === '') {
            throw new \RuntimeException('No Gemini API key — set it in the Credentials Vault '
                . '(gemini_api_key), or in the AI config KYC document reading uses.');
        }
        $model = (string) env('SMARTUW_GEMINI_MODEL', 'gemini-2.5-flash');

        // Key in the x-goog-api-key HEADER, never the URL query string, so it
        // cannot leak into access logs. Mirrors OcrExtractor::callTextLlm.
        $resp = Http::withHeaders(['x-goog-api-key' => $key, 'Content-Type' => 'application/json'])
            ->timeout(180)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents'           => [['role' => 'user', 'parts' => [['text' => $user]]]],
                // thinkingBudget 0: a schedule extraction is transcription, not
                // reasoning, and thinking tokens can consume the whole output
                // budget and return an empty candidate.
                'generationConfig'   => [
                    'temperature'      => 0.0,
                    'maxOutputTokens'  => 8192,
                    'thinkingConfig'   => ['thinkingBudget' => 0],
                    'responseMimeType' => 'application/json',
                ],
            ]);

        if ($resp->failed()) {
            throw new \RuntimeException('Gemini ' . $resp->status() . ': ' . substr($resp->body(), 0, 300));
        }
        $raw = (string) $resp->json('candidates.0.content.parts.0.text', '');
        if (trim($raw) === '') {
            throw new \RuntimeException('Gemini returned no text (finishReason='
                . $resp->json('candidates.0.finishReason', 'unknown') . ')');
        }

        return $raw;
    }

    private function callDeepseek(string $system, string $user, string $key): string
    {
        $key = $this->providerKey('deepseek', $key);
        if ($key === '') {
            throw new \RuntimeException('No DeepSeek API key — set it in the Credentials Vault (deepseek_api_key).');
        }
        $model = (string) env('SMARTUW_DEEPSEEK_MODEL', 'deepseek-chat');

        $resp = Http::withHeaders(['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'])
            ->timeout(180)
            ->post('https://api.deepseek.com/chat/completions', [
                'model'           => $model,
                'temperature'     => 0.0,
                // A multi-section schedule's JSON runs long, and DeepSeek's
                // default output cap (4096) truncates it mid-object — which
                // arrives here as "Model did not return JSON" rather than as a
                // length error. Ask for the full budget. Gemini's own call does
                // the same (maxOutputTokens 8192).
                'max_tokens'      => 8192,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ]);

        if ($resp->failed()) {
            throw new \RuntimeException('DeepSeek ' . $resp->status() . ': ' . substr($resp->body(), 0, 300));
        }

        // Say WHICH thing went wrong. A cut-off answer and an empty answer both
        // reach parseJson() as "Model did not return JSON", which sends the
        // operator looking for a bad schedule when the cause is the output cap
        // or a refusal. finish_reason names it.
        $finish = (string) $resp->json('choices.0.finish_reason', '');
        $raw    = (string) $resp->json('choices.0.message.content', '');
        if ($finish === 'length') {
            throw new \RuntimeException('DeepSeek stopped at its output limit — the JSON for this '
                . 'schedule was cut off mid-object. Split the sheet, or set SMARTUW_DEEPSEEK_MODEL '
                . 'to a model with a larger output budget, then re-upload.');
        }
        if (trim($raw) === '') {
            throw new \RuntimeException('DeepSeek returned no text (finish_reason='
                . ($finish !== '' ? $finish : 'unknown') . ')');
        }

        return $raw;
    }

    /** extract.py::_parse_json — tolerate code fences and surrounding prose. */
    private function parseJson(string $raw): array
    {
        $raw = trim($raw);
        if (str_starts_with($raw, '```')) {
            $raw = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $raw) ?: $raw;
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            // A model that answers [ {...} ] instead of { ... } used to leave
            // the whole risk under integer key 0: every consumer reading
            // $risk['customer'] saw nothing, and _errors reported
            // "customer.name is empty" for a response that plainly had one.
            if (array_is_list($decoded) && count($decoded) === 1 && is_array($decoded[0])) {
                return $decoded[0];
            }

            return $decoded;
        }

        // Last resort: the outermost {...} block.
        $a = strpos($raw, '{');
        $b = strrpos($raw, '}');
        if ($a !== false && $b !== false && $b > $a) {
            $decoded = json_decode(substr($raw, $a, $b - $a + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException('Model did not return JSON: ' . mb_substr($raw, 0, 200));
    }

    /**
     * Pull the registration (and year) out of make_model when the model left
     * `registration` null.
     *
     * Broker vehicle schedules very often carry one free-text cell per vehicle
     * — "2018 Venter Trailer B 424 BVO" — instead of separate registration and
     * description columns. The extractor faithfully returns that as make_model
     * with registration null, and a null plate is fatal downstream, not
     * cosmetic: `addVehicle` requires vehiclePlate, and `addMotorVehicle`
     * rejects a blank plate outright ("Registration number (vehicle plate) is
     * required") because (policy_coverage_id, registration_no) is the key it
     * uses to reuse a motor row instead of duplicating it. So a schedule of
     * five vehicles rendered five rows of "—" that could not be added to the
     * policy at all.
     *
     * Deliberately conservative — a wrong plate is worse than a blank one:
     *   - only fills registration when it is EMPTY (a model-supplied plate wins);
     *   - only accepts the Botswana civilian shape, B + 3 digits + 3 letters,
     *     with optional spacing/hyphens ("B 424 BVO", "B424BVO", "B-424-BVO");
     *   - requires the plate to sit at a word boundary, so a make like
     *     "B123ABCDEF" is not sliced up;
     *   - takes the LAST match, because these cells read "<description> <plate>";
     *   - leaves make_model alone entirely if stripping the plate and a leading
     *     year would empty it.
     * A cell it cannot read keeps its null and still shows as "—", exactly as
     * before, so nothing that used to work changes shape.
     */
    private function normaliseMotorRows(array $obj): array
    {
        if (!isset($obj['motor']) || !is_array($obj['motor'])) {
            return $obj;
        }

        foreach ($obj['motor'] as $i => $row) {
            if (!is_array($row)) {
                continue;
            }

            $plate = trim((string) ($row['registration'] ?? ''));
            $desc  = trim((string) ($row['make_model'] ?? ''));
            if ($desc === '') {
                continue;
            }

            if ($plate === '') {
                // \b on both ends so only a standalone token is taken.
                if (preg_match_all('/\bB[\s\-]?\d{3}[\s\-]?[A-Z]{3}\b/i', $desc, $m) && !empty($m[0])) {
                    $found = (string) end($m[0]);
                    // Store it the way the plate is keyed everywhere else:
                    // upper case, no separators (addVehicle upper-cases too).
                    $obj['motor'][$i]['registration'] = strtoupper(
                        preg_replace('/[\s\-]+/', '', $found)
                    );

                    $stripped = trim(str_replace($found, ' ', $desc));
                    $stripped = trim(preg_replace('/\s{2,}/', ' ', $stripped));
                    if ($stripped !== '') {
                        $obj['motor'][$i]['make_model'] = $stripped;
                        $desc = $stripped;
                    }
                }
            }

            // A leading 4-digit year in the same cell ("2018 Venter Trailer")
            // is the row's year, not part of the make. Only lifted when the
            // model gave no year, and only from the FRONT of the string.
            $year = $row['year'] ?? null;
            if (($year === null || $year === '' || (int) $year === 0)
                && preg_match('/^(19|20)\d{2}\b/', $desc, $ym)
            ) {
                $obj['motor'][$i]['year'] = (int) $ym[0];
                $rest = trim(substr($desc, strlen($ym[0])));
                if ($rest !== '') {
                    $obj['motor'][$i]['make_model'] = $rest;
                }
            }
        }

        return $obj;
    }

    /** The four buckets a Graphite coverage line can land in. Keys are the
     *  extraction's own array names; the values are what the screen calls them. */
    public const CLASSIFY_BUCKETS = [
        'details'         => 'Coverage',
        'extensions'      => 'Extension',
        'specified_items' => 'Miscellaneous Item',
        'excesses'        => 'Excess',
    ];

    /**
     * Make the classification keys safe to read, whatever the model returned.
     *
     * The model is asked for five arrays per section and two flags per line;
     * it will sometimes answer with a string, a single object, or nothing at
     * all. Every consumer downstream (the review screen's classification
     * editor, the apply path, the exception count) reads these keys directly,
     * so the shape is settled once, here, rather than defended for in four
     * places. Nothing is re-classified and nothing is dropped: a bucket the
     * model omitted becomes an empty list, and a line with no text at all is
     * the only thing discarded — there is nothing for an underwriter to place.
     */
    /** Public + static: the JOB calls it for sidecar-produced risks, which
     *  never pass through this class at all. */
    public static function normaliseClassification(array $obj): array
    {
        $listOf = static function ($v): array {
            if (is_array($v)) {
                // A single object answered where a list was asked for.
                return array_is_list($v) ? $v : [$v];
            }
            // One bare line answered where a list was asked for. Wrapped, not
            // discarded: the contract is that no printed line is ever dropped,
            // and this is the one an unsure model is most likely to write as
            // plain text ("unclassified": "Sprinkler warranty applies").
            if (is_string($v) && trim($v) !== '') {
                return [$v];
            }

            return [];
        };

        // One unclassified entry, whichever key the model used for its text.
        $pending = static function ($row, bool $withSection): ?array {
            if (is_string($row)) {
                $row = ['text' => $row];
            }
            if (!is_array($row)) {
                return null;
            }
            $text = trim((string) ($row['text'] ?? $row['description'] ?? $row['name'] ?? ''));
            if ($text === '') {
                return null;
            }
            $out = [
                'text'        => $text,
                'sum_insured' => $row['sum_insured'] ?? null,
                'rate'        => $row['rate'] ?? null,
                'premium'     => $row['premium'] ?? null,
                'reason'      => isset($row['reason']) ? (string) $row['reason'] : null,
                // Which bucket an underwriter un-placed this line FROM. Kept
                // because the figures are: dropping the marker while
                // preserving "10%" and "P10,000" is what let an excess be
                // placed as a coverage after a save, with the excess's numbers
                // in the coverage's rate and premium. Only the four bucket
                // names are accepted, so nothing else can ride in on this key.
                '_from'       => isset(self::CLASSIFY_BUCKETS[$row['_from'] ?? ''])
                    ? (string) $row['_from']
                    : null,
            ];
            if ($withSection) {
                $out['section'] = isset($row['section']) ? (string) $row['section'] : null;
            }

            return $out;
        };

        $coverages = $listOf($obj['coverages'] ?? null);
        foreach ($coverages as $i => $cov) {
            if (!is_array($cov)) {
                continue;
            }
            // A bucket row that came back as a bare string instead of an
            // object. It is a real printed line, so it is moved to
            // unclassified rather than dropped — the model named a bucket but
            // gave nothing to place, which is exactly the case a person
            // should settle.
            $strays = [];
            foreach (array_keys(self::CLASSIFY_BUCKETS) as $bucket) {
                $rows = $listOf($cov[$bucket] ?? null);
                foreach ($rows as $ri => $row) {
                    if (!is_array($row)) {
                        if (is_string($row) && trim($row) !== '') {
                            $strays[] = [
                                'text'   => trim($row),
                                'reason' => 'Named under ' . self::CLASSIFY_BUCKETS[$bucket]
                                    . ' with no figures — confirm where it belongs.',
                            ];
                        }
                        unset($rows[$ri]);
                        continue;
                    }
                    // 'true' / '1' / 'yes' from a model that wrote the flag as text.
                    $flag = $row['needs_check'] ?? false;
                    $rows[$ri]['needs_check'] = is_string($flag)
                        ? in_array(strtolower(trim($flag)), ['1', 'true', 'yes', 'y'], true)
                        : (bool) $flag;
                    $reason = trim((string) ($row['check_reason'] ?? ''));
                    $rows[$ri]['check_reason'] = $reason !== '' ? $reason : null;
                }
                $cov[$bucket] = array_values($rows);
            }

            $unplaced = [];
            foreach (array_merge($listOf($cov['unclassified'] ?? null), $strays) as $row) {
                $one = $pending($row, false);
                if ($one !== null) {
                    $unplaced[] = $one;
                }
            }
            $cov['unclassified'] = $unplaced;
            $coverages[$i] = $cov;
        }
        if (array_key_exists('coverages', $obj) || $coverages) {
            $obj['coverages'] = array_values($coverages);
        }

        $top = [];
        foreach ($listOf($obj['unclassified'] ?? null) as $row) {
            $one = $pending($row, true);
            if ($one !== null) {
                $top[] = $one;
            }
        }
        $obj['unclassified'] = $top;

        return $obj;
    }

    /**
     * The exceptions an underwriter has to look at, in words.
     *
     * Two kinds, and neither is a failure — they are the point of the design:
     * a line the reader placed but is unsure of ("please check"), and a line it
     * refused to place at all ("needs classification"). Both are carried to
     * the review screen, both stay editable, and neither blocks the upload.
     * Stored alongside the validation errors in
     * smart_uw_extractions.discrepancies, so the existing amber panel lists
     * them with no schema change.
     *
     * Public + static so the job and its tests can call it without an instance.
     *
     * @return string[] human-readable exception lines; empty = nothing to check
     */
    public static function classificationExceptions(array $obj): array
    {
        $out = [];

        foreach ((is_array($obj['coverages'] ?? null) ? $obj['coverages'] : []) as $cov) {
            if (!is_array($cov)) {
                continue;
            }
            $title = trim((string) ($cov['section'] ?? '')) ?: '(untitled section)';

            foreach (self::CLASSIFY_BUCKETS as $bucket => $label) {
                $rows = is_array($cov[$bucket] ?? null) ? $cov[$bucket] : [];
                foreach ($rows as $row) {
                    if (!is_array($row) || empty($row['needs_check'])) {
                        continue;
                    }
                    $name = trim((string) ($row['description'] ?? $row['name'] ?? $row['text'] ?? ''));
                    $out[] = 'Please check — ' . $title . ' / ' . $label . ': "'
                        . ($name !== '' ? mb_substr($name, 0, 120) : '(unnamed line)') . '"'
                        . (!empty($row['check_reason']) ? ' — ' . (string) $row['check_reason'] : '');
                }
            }

            foreach ((is_array($cov['unclassified'] ?? null) ? $cov['unclassified'] : []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $out[] = 'Needs classification — ' . $title . ': "'
                    . mb_substr(trim((string) ($row['text'] ?? '')), 0, 120) . '"'
                    . (!empty($row['reason']) ? ' — ' . (string) $row['reason'] : '');
            }
        }

        foreach ((is_array($obj['unclassified'] ?? null) ? $obj['unclassified'] : []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $where = trim((string) ($row['section'] ?? ''));
            $out[] = 'Needs classification — no section'
                . ($where !== '' ? ' (printed under "' . mb_substr($where, 0, 60) . '")' : '') . ': "'
                . mb_substr(trim((string) ($row['text'] ?? '')), 0, 120) . '"'
                . (!empty($row['reason']) ? ' — ' . (string) $row['reason'] : '');
        }

        return $out;
    }

    /**
     * Structural validation — port of schema.py::validate. The result is stored
     * in smart_uw_extractions.discrepancies and drives the review screen's
     * confidence flag, so it must flag the same things the engine flags.
     *
     * @return array list of human-readable problems; empty = clean
     */
    /** Public + static: saveExtraction re-runs it on a hand-edited risk, so a
     *  problem the underwriter fixed stops being reported. Pure — no state. */
    public static function validate(array $obj): array
    {
        $errs = [];
        foreach (['policy', 'customer', 'coverages'] as $key) {
            if (!array_key_exists($key, $obj)) {
                $errs[] = "missing top-level key: {$key}";
            }
        }

        $cust = is_array($obj['customer'] ?? null) ? $obj['customer'] : [];
        if (empty($cust['name'])) {
            $errs[] = 'customer.name is empty';
        }
        if (isset($cust['entity_type']) && !in_array($cust['entity_type'], ['Organisation', 'Individual'], true)) {
            $errs[] = 'bad entity_type: ' . (string) $cust['entity_type'];
        }

        $covs = $obj['coverages'] ?? null;
        if ($covs !== null && !is_array($covs)) {
            $errs[] = 'coverages is not a list';
        }
        if (is_array($covs)) {
            foreach ($covs as $i => $c) {
                if (!is_array($c)) {
                    $errs[] = "coverages[{$i}] not an object";
                    continue;
                }
                if (!array_key_exists('section', $c)) {
                    $errs[] = "coverages[{$i}] missing section";
                }
                if (isset($c['details']) && !is_array($c['details'])) {
                    $errs[] = "coverages[{$i}].details not a list";
                }
            }
        }

        if (isset($obj['motor']) && !is_array($obj['motor'])) {
            $errs[] = 'motor is not a list';
        }

        return $errs;
    }
}
