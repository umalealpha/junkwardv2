<?php

namespace AlphaDirect\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

/**
 * PdfGeneratorService
 *
 * Primary HTML→PDF generator. Routes through the standalone Puppeteer
 * microservice (PDF_SERVICE_URL). Falls back to the existing Snappy
 * (wkhtmltopdf) binary if the microservice is unreachable.
 *
 * Usage:
 *   $binary = app(PdfGeneratorService::class)->fromView('v2.livewire.pdf.v2-quote-sheet', $data);
 *   $binary = app(PdfGeneratorService::class)->fromHtml('<h1>…</h1>', ['format' => 'A4', 'landscape' => true]);
 *   $result = app(PdfGeneratorService::class)->generateAndStore('quotes/q123.pdf', 'v2.livewire.pdf.v2-quote-sheet', $data);
 *   // $result = ['path' => 'quotes/q123.pdf', 'disk' => 's3', 'url' => '…', 'size' => 12345]
 */
class PdfGeneratorService
{
    private string $serviceUrl;
    private string $apiKey;
    private int $timeoutSec;
    private bool $fallbackEnabled;

    public function __construct()
    {
        $this->serviceUrl      = rtrim((string) config('services.pdf.url', env('PDF_SERVICE_URL', '')), '/');
        $this->apiKey          = (string) config('services.pdf.key', env('PDF_API_KEY', ''));
        $this->timeoutSec      = (int) config('services.pdf.timeout', env('PDF_SERVICE_TIMEOUT', 150));
        $this->fallbackEnabled = (bool) config('services.pdf.fallback_snappy', env('PDF_FALLBACK_SNAPPY', true));
    }

    /**
     * Render a Blade view to PDF binary.
     *
     * @param string $view    Blade view name
     * @param array  $data    View data
     * @param array  $options Puppeteer options (format, landscape, margin, filename, scale, ...)
     */
    public function fromView(string $view, array $data = [], array $options = []): string
    {
        $html = View::make($view, $data)->render();
        return $this->fromHtml($html, $options);
    }

    /**
     * Render raw HTML to PDF binary.
     */
    public function fromHtml(string $html, array $options = []): string
    {
        if ($this->serviceUrl !== '') {
            try {
                return $this->renderViaService($html, $options);
            } catch (\Throwable $e) {
                Log::warning('PDF service unreachable, attempting fallback', [
                    'error' => $e->getMessage(),
                    'service_url' => $this->serviceUrl,
                ]);
                if (!$this->fallbackEnabled) {
                    throw $e;
                }
            }
        }

        return $this->renderViaSnappy($html, $options);
    }

    /**
     * Render and store on disk (S3 with local fallback).
     *
     * @return array{path: string, disk: string, url: string, size: int}
     */
    public function generateAndStore(string $storagePath, string $view, array $data = [], array $options = []): array
    {
        $binary = $this->fromView($view, $data, $options);
        return app(StorageService::class)->putWithFallback($storagePath, $binary, [
            'ContentType' => 'application/pdf',
            'visibility'  => 'public',
        ]);
    }

    // ─── Internals ──────────────────────────────────────────────────────────

    private function renderViaService(string $html, array $options): string
    {
        $client = new Client([
            'base_uri' => $this->serviceUrl,
            'timeout'  => $this->timeoutSec,
            'connect_timeout' => 5,
            'http_errors' => true,
            'headers' => [
                'Content-Type'   => 'application/json',
                'X-PDF-API-KEY'  => $this->apiKey,
                'Accept'         => 'application/pdf',
            ],
        ]);

        $response = $client->post('/render', [
            'json' => [
                'html'    => $html,
                'options' => $this->normalizeOptions($options),
                'waitUntil' => $options['waitUntil'] ?? 'networkidle0',
            ],
        ]);

        $body = (string) $response->getBody();
        if ($body === '') {
            throw new \RuntimeException('PDF service returned empty response');
        }
        return $body;
    }

    /**
     * Fallback to wkhtmltopdf via raw Knp\Snappy.
     * Uses the original Snappy library directly (not via `app('snappy.pdf.wrapper')`
     * which is now bound to our PdfWrapper shim, which would recurse back here).
     */
    private function renderViaSnappy(string $html, array $options): string
    {
        try {
            if (!class_exists(\Knp\Snappy\Pdf::class)) {
                throw new \RuntimeException('Snappy (Knp\\Snappy\\Pdf) not installed');
            }
            $binary = (string) config('snappy.pdf.binary', env('WKHTML_PDF_BINARY', '/usr/local/bin/wkhtmltopdf'));
            $snappy = new \Knp\Snappy\Pdf($binary);

            if (!empty($options['format']))    $snappy->setOption('page-size', $options['format']);
            if (!empty($options['landscape'])) $snappy->setOption('orientation', 'Landscape');
            if (!empty($options['margin'])) {
                foreach (['top','right','bottom','left'] as $side) {
                    if (isset($options['margin'][$side])) {
                        $snappy->setOption("margin-{$side}", (string) $options['margin'][$side]);
                    }
                }
            }

            return $snappy->getOutputFromHtml($html);
        } catch (\Throwable $e) {
            Log::warning('Snappy fallback failed, trying DomPDF', ['error' => $e->getMessage()]);
            return $this->renderViaDomPdf($html, $options);
        }
    }

    /**
     * Final fallback: pure-PHP DomPDF. Slow and low fidelity but has no external deps.
     */
    private function renderViaDomPdf(string $html, array $options): string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('DomPDF not installed — no PDF backend available');
        }
        $dompdf = new \Dompdf\Dompdf(['isHtml5ParserEnabled' => true, 'isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $paper = strtoupper((string) ($options['format'] ?? 'A4'));
        $orientation = !empty($options['landscape']) ? 'landscape' : 'portrait';
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();
        return (string) $dompdf->output();
    }

    /**
     * Normalize options into the shape the Puppeteer service expects.
     */
    private function normalizeOptions(array $options): array
    {
        $out = [];
        if (isset($options['format']))     $out['format'] = $options['format'];
        if (isset($options['landscape']))  $out['landscape'] = (bool) $options['landscape'];
        if (isset($options['filename']))   $out['filename'] = $options['filename'];
        if (isset($options['scale']))      $out['scale'] = (float) $options['scale'];
        if (isset($options['margin']))     $out['margin'] = $options['margin'];
        if (isset($options['timeout']))    $out['timeout'] = (int) $options['timeout'];
        if (isset($options['printBackground'])) $out['printBackground'] = (bool) $options['printBackground'];
        if (isset($options['displayHeaderFooter'])) {
            $out['displayHeaderFooter'] = (bool) $options['displayHeaderFooter'];
            $out['headerTemplate'] = $options['headerTemplate'] ?? '<div></div>';
            $out['footerTemplate'] = $options['footerTemplate'] ?? '<div></div>';
        }

        // Sensible defaults if caller didn't set them
        $out['format']          = $out['format']          ?? 'A4';
        $out['landscape']       = $out['landscape']       ?? false;
        $out['printBackground'] = $out['printBackground'] ?? true;
        $out['margin']          = $out['margin']          ?? ['top'=>'10mm','right'=>'10mm','bottom'=>'10mm','left'=>'10mm'];

        return $out;
    }

    /**
     * Ping the PDF service health endpoint. Returns true if reachable and healthy.
     */
    public function isHealthy(): bool
    {
        if ($this->serviceUrl === '') return false;
        try {
            $client = new Client(['base_uri' => $this->serviceUrl, 'timeout' => 5, 'connect_timeout' => 2]);
            $resp = $client->get('/health');
            return $resp->getStatusCode() === 200;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
