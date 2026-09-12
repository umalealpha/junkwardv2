<?php

namespace AlphaDirect\Providers;

use AlphaDirect\Pdf\PdfWrapper;
use AlphaDirect\Services\PdfGeneratorService;
use AlphaDirect\Services\StorageService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the global PDF facade alias to our Puppeteer-backed wrapper.
 *
 * After registration:
 *   - `PDF::loadView(...)->output()`       → PdfWrapper → PdfGeneratorService → Puppeteer
 *   - `SnappyPDF::loadView(...)->output()` → PdfWrapper → PdfGeneratorService → Puppeteer
 *   - Both fall back to Snappy (wkhtmltopdf) then DomPDF if Puppeteer is unreachable.
 *
 * The aliases themselves are updated in config/app.php.
 */
class PdfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fresh instance each time — PDF generation is stateful per build.
        $this->app->bind('alphadirect.pdf', fn () => new PdfWrapper());

        // Also override barryvdh/laravel-dompdf's binding so any code still using
        // `app('dompdf.wrapper')` or the default `PDF` facade resolution routes
        // through the Puppeteer chain. Safe because PdfWrapper is a duck-type
        // drop-in for the Dompdf wrapper.
        $this->app->bind('dompdf.wrapper', fn () => new PdfWrapper());

        // Same for snappy — covers `app('snappy.pdf.wrapper')` and the SnappyPDF
        // facade. PdfGeneratorService uses the original Snappy classes directly
        // for its own fallback (not via this binding) so no recursion.
        $this->app->bind('snappy.pdf.wrapper', fn () => new PdfWrapper());

        // Core services are singletons — safe because they hold only config,
        // not per-request state. Guzzle clients are instantiated per call.
        $this->app->singleton(PdfGeneratorService::class);
        $this->app->singleton(StorageService::class);
    }
}
