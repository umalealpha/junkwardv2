<?php

namespace AlphaDirect\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Drop-in replacement for Barryvdh\DomPDF\Facade and Barryvdh\Snappy\Facades\SnappyPdf.
 *
 * Resolves to a fresh PdfWrapper instance on each call, so multiple concurrent
 * PDF builds in the same request don't clobber each other's state.
 *
 * @method static \AlphaDirect\Pdf\PdfWrapper loadView(string $view, array $data = [], array $mergeData = [])
 * @method static \AlphaDirect\Pdf\PdfWrapper loadHTML(string $html, ?string $encoding = null)
 * @method static \AlphaDirect\Pdf\PdfWrapper loadFile(string $path)
 * @method static \AlphaDirect\Pdf\PdfWrapper setPaper($paper, string $orientation = 'portrait')
 * @method static \AlphaDirect\Pdf\PdfWrapper setOrientation(string $orientation)
 * @method static \AlphaDirect\Pdf\PdfWrapper setOption(string $name, $value)
 * @method static \AlphaDirect\Pdf\PdfWrapper setOptions(array $options)
 * @method static string output()
 * @method static \Illuminate\Http\Response stream(string $filename = 'document.pdf', array $options = [])
 * @method static \Illuminate\Http\Response download(string $filename = 'document.pdf')
 * @method static \AlphaDirect\Pdf\PdfWrapper save(string $path, bool $overwrite = false)
 */
class Pdf extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'alphadirect.pdf';
    }

    /**
     * Resolve a fresh instance every time to avoid cross-request state leakage.
     */
    protected static function resolveFacadeInstance($name)
    {
        return app()->make($name);
    }
}
