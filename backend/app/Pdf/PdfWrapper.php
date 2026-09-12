<?php

namespace AlphaDirect\Pdf;

use AlphaDirect\Services\PdfGeneratorService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

/**
 * PdfWrapper — Dompdf/Snappy-compatible API that routes through PdfGeneratorService.
 *
 * This is the target of the global `PDF::` and `SnappyPDF::` facade aliases.
 * Every call chain (`->loadView(...)->setPaper(...)->output()`) now goes through
 * the Puppeteer service first, with Snappy and DomPDF as automatic fallbacks.
 *
 * Existing code does not need changes — the method signatures match both
 * barryvdh/laravel-dompdf and barryvdh/laravel-snappy.
 *
 * Supported API (matches both libraries):
 *   ->loadView($view, $data = [], $mergeData = [])
 *   ->loadHTML($html, $encoding = null)
 *   ->loadFile($path)
 *   ->setPaper($format, $orientation = 'portrait')
 *   ->setOrientation($orientation)
 *   ->setOption($key, $value)      (Snappy-only options silently ignored)
 *   ->setOptions(array $options)
 *   ->output()                      binary string
 *   ->stream($filename)             inline response
 *   ->download($filename)           attachment response
 *   ->save($path, $overwrite = false)   writes to local filesystem
 *   ->inline($filename)             alias for stream
 */
class PdfWrapper
{
    private ?string $html = null;
    private array $options = [
        'format'    => 'A4',
        'landscape' => false,
        'margin'    => ['top' => '10mm', 'right' => '10mm', 'bottom' => '10mm', 'left' => '10mm'],
    ];
    private array $snappyOptions = []; // kept for API compat; ignored

    // ─── Loaders ────────────────────────────────────────────────────────────

    public function loadView(string $view, array $data = [], array $mergeData = []): self
    {
        $this->html = View::make($view, $data, $mergeData)->render();
        return $this;
    }

    /**
     * Accept raw HTML. Both DomPDF's `loadHTML` and Snappy's `loadHtml` route
     * here (PHP method names are case-insensitive, so one declaration covers
     * both callsite spellings).
     */
    public function loadHTML(string $html, ?string $encoding = null): self
    {
        $this->html = $html;
        return $this;
    }

    public function loadFile(string $path): self
    {
        $this->html = file_exists($path) ? file_get_contents($path) : '';
        return $this;
    }

    // ─── Option setters ─────────────────────────────────────────────────────

    /**
     * @param string|array $paper 'A4', 'A3', 'Letter', or [width, height] array
     */
    public function setPaper($paper, string $orientation = 'portrait'): self
    {
        if (is_array($paper)) {
            // [0,0,width,height] or [width,height] from DomPDF style
            // Convert points to mm if very large (rough: 1pt ≈ 0.353mm)
            $w = $paper[2] ?? $paper[0] ?? null;
            $h = $paper[3] ?? $paper[1] ?? null;
            if ($w && $h) {
                $this->options['width']  = round($w * 0.353) . 'mm';
                $this->options['height'] = round($h * 0.353) . 'mm';
                unset($this->options['format']);
            }
        } else {
            $this->options['format'] = strtoupper((string) $paper);
        }
        $this->options['landscape'] = strtolower($orientation) === 'landscape';
        return $this;
    }

    public function setOrientation(string $orientation): self
    {
        $this->options['landscape'] = strtolower($orientation) === 'landscape';
        return $this;
    }

    /** Snappy-specific option. We accept and store it for API compat; most have no Puppeteer equivalent. */
    public function setOption(string $name, $value): self
    {
        $this->snappyOptions[$name] = $value;

        // Translate the few Snappy options that DO have Puppeteer equivalents
        switch ($name) {
            case 'margin-top':    $this->options['margin']['top']    = is_numeric($value) ? "{$value}mm" : $value; break;
            case 'margin-right':  $this->options['margin']['right']  = is_numeric($value) ? "{$value}mm" : $value; break;
            case 'margin-bottom': $this->options['margin']['bottom'] = is_numeric($value) ? "{$value}mm" : $value; break;
            case 'margin-left':   $this->options['margin']['left']   = is_numeric($value) ? "{$value}mm" : $value; break;
            case 'orientation':   $this->options['landscape'] = strtolower((string)$value) === 'landscape'; break;
            case 'page-size':     $this->options['format'] = strtoupper((string)$value); break;
            case 'disable-smart-shrinking':
            case 'enable-local-file-access':
            case 'javascript-delay':
            case 'no-stop-slow-scripts':
            case 'load-error-handling':
            case 'load-media-error-handling':
                // Silent no-op — Puppeteer handles these differently/automatically
                break;
        }
        return $this;
    }

    public function setOptions(array $options): self
    {
        foreach ($options as $k => $v) {
            $this->setOption($k, $v);
        }
        return $this;
    }

    /** DomPDF-specific: no-op (kept for API compat). */
    public function setWarnings(bool $warnings): self { return $this; }

    // ─── Output ─────────────────────────────────────────────────────────────

    /** Render and return raw PDF binary. */
    public function output(): string
    {
        if ($this->html === null) {
            throw new \RuntimeException('PdfWrapper: no content loaded. Call loadView()/loadHTML() first.');
        }
        return app(PdfGeneratorService::class)->fromHtml($this->html, $this->options);
    }

    /** DomPDF alias for output(). */
    public function render(): string
    {
        return $this->output();
    }

    /** Save to a local filesystem path. Returns $this. */
    public function save(string $path, bool $overwrite = false): self
    {
        if (!$overwrite && file_exists($path)) {
            throw new \RuntimeException("PdfWrapper::save — file exists: {$path} (pass overwrite=true to replace)");
        }
        $dir = dirname($path);
        if ($dir && !is_dir($dir)) @mkdir($dir, 0755, true);
        file_put_contents($path, $this->output());
        return $this;
    }

    /** Inline response (display in browser). */
    public function stream(string $filename = 'document.pdf', array $options = []): Response
    {
        return response($this->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $this->sanitizeFilename($filename) . '"',
        ]);
    }

    public function inline(string $filename = 'document.pdf'): Response
    {
        return $this->stream($filename);
    }

    /** Attachment response (force download). */
    public function download(string $filename = 'document.pdf'): Response
    {
        return response($this->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->sanitizeFilename($filename) . '"',
        ]);
    }

    /** DomPDF-specific: returns underlying Dompdf instance. We return null — code paths that need this are rare. */
    public function getDomPDF()
    {
        return null;
    }

    /** Snappy-specific: returns the Knp\Snappy\Pdf instance. Same — return null. */
    public function getSnappy()
    {
        return null;
    }

    /** Magic passthrough for any unsupported method — logs and returns $this (fluent chain preserved). */
    public function __call(string $method, array $args)
    {
        \Log::debug("PdfWrapper: ignoring unsupported method {$method}", ['args_count' => count($args)]);
        return $this;
    }

    // ─── Internals ──────────────────────────────────────────────────────────

    private function sanitizeFilename(string $filename): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return substr($safe, 0, 200) ?: 'document.pdf';
    }
}
