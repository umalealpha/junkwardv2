<?php

namespace Tests\Feature\SmartUw;

use AlphaDirect\Services\SmartUw\PhpScheduleExtractor;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Broker schedules do not arrive in one format. Before this, anything that was
 * not a workbook or a PDF was refused at the upload gate and the underwriter
 * retyped it. These cover the readers, not the AI: each one builds a real file
 * on disk and checks the segment that comes back.
 */
class ScheduleFileFormatsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::put('credential_vault_cache', [], 600);
        $this->dir = sys_get_temp_dir() . '/smartuw_fmt_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** Call a private reader directly — no provider, no network. */
    private function reader(string $method, ...$args)
    {
        $m = new ReflectionMethod(PhpScheduleExtractor::class, $method);
        $m->setAccessible(true);

        return $m->invoke(new PhpScheduleExtractor(), ...$args);
    }

    private function write(string $name, string $bytes): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $bytes);

        return $path;
    }

    public function test_the_gate_accepts_every_format_that_has_a_reader(): void
    {
        foreach (['xlsx', 'xls', 'pdf', 'csv', 'txt', 'tsv', 'md', 'docx',
                  'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'] as $ext) {
            $this->assertTrue(PhpScheduleExtractor::supports($ext), ".{$ext} should be readable");
            $this->assertTrue(PhpScheduleExtractor::supports('.' . strtoupper($ext)), "upper .{$ext}");
        }

        // No reader exists for these — they must stay refused at the gate
        // rather than failing later with an opaque provider error.
        $this->assertFalse(PhpScheduleExtractor::supports('xlsb'));
        $this->assertFalse(PhpScheduleExtractor::supports('doc'));
    }

    public function test_a_csv_is_read_as_one_text_segment(): void
    {
        $path = $this->write('schedule.csv', "SECTION,SUM INSURED,RATE,PREMIUM\nBuildings,3120000,0.00101,3151.20\n");

        $segments = $this->reader('textSegments', $path);

        $this->assertCount(1, $segments);
        $this->assertSame('text', $segments[0]['kind']);
        $this->assertStringContainsString('Buildings,3120000', $segments[0]['text']);
    }

    public function test_a_windows_1252_csv_keeps_its_figures_readable(): void
    {
        // Excel writes CSV in the ANSI codepage. Left as-is, the currency and
        // dash bytes come through as mojibake and a sum insured can be lost.
        $path = $this->write('ansi.csv', mb_convert_encoding(
            "DESCRIPTION,SUM\nOffice – Gaborone,\"1,250,000\"\n", 'Windows-1252', 'UTF-8'
        ));

        $text = $this->reader('textSegments', $path)[0]['text'];

        $this->assertTrue(mb_check_encoding($text, 'UTF-8'), 'segment must be valid UTF-8');
        $this->assertStringContainsString('Office – Gaborone', $text);
        $this->assertStringContainsString('1,250,000', $text);
    }

    public function test_an_empty_text_file_says_so(): void
    {
        $path = $this->write('blank.txt', "   \n  ");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/empty/i');
        $this->reader('textSegments', $path);
    }

    public function test_a_docx_gives_up_its_table_with_columns_intact(): void
    {
        $path = $this->write('schedule.docx', $this->minimalDocx());

        $segments = $this->reader('docxSegments', $path);
        $text = $segments[0]['text'];

        $this->assertSame('text', $segments[0]['kind']);
        $this->assertStringContainsString('GOODS IN TRANSIT', $text);
        // Cells must stay separated — run together, every column is lost.
        $this->assertStringContainsString("Estimated carry per annum\t4500000", $text);
        // Rows must stay on their own lines.
        $this->assertStringContainsString("\nLoad limit per load", $text);
    }

    public function test_a_corrupt_docx_fails_with_a_readable_message(): void
    {
        $path = $this->write('broken.docx', 'this is not a zip');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not be opened|corrupt/i');
        $this->reader('docxSegments', $path);
    }

    public function test_a_png_is_sent_as_is_and_a_gif_is_converted(): void
    {
        putenv('SMARTUW_ALLOW_SCANNED_PDF=1');
        $_ENV['SMARTUW_ALLOW_SCANNED_PDF'] = $_SERVER['SMARTUW_ALLOW_SCANNED_PDF'] = '1';

        try {
            $png = $this->write('shot.png', $this->onePixel('png'));
            $seg = $this->reader('imageSegments', $png, 'png')[0];
            $this->assertSame('image', $seg['kind']);
            $this->assertSame('image/png', $seg['mime']);
            $this->assertSame($png, $seg['file'], 'a PNG needs no conversion');

            // Gemini accepts neither GIF nor BMP, so those are converted first.
            $gif = $this->write('shot.gif', $this->onePixel('gif'));
            $seg = $this->reader('imageSegments', $gif, 'gif')[0];
            $this->assertSame('image/png', $seg['mime']);
            $this->assertNotSame($gif, $seg['file']);
            $this->assertSame('image/png', mime_content_type($seg['file']));
            @unlink($seg['file']);
        } finally {
            putenv('SMARTUW_ALLOW_SCANNED_PDF');
            unset($_ENV['SMARTUW_ALLOW_SCANNED_PDF'], $_SERVER['SMARTUW_ALLOW_SCANNED_PDF']);
        }
    }

    public function test_an_image_is_refused_unless_the_scan_opt_in_is_set(): void
    {
        // Nothing can pre-screen a picture for Omang / bank data, so an image
        // rides the same governance switch as a scanned PDF (AD-POL-AI-GOV-001).
        putenv('SMARTUW_ALLOW_SCANNED_PDF=0');
        $_ENV['SMARTUW_ALLOW_SCANNED_PDF'] = $_SERVER['SMARTUW_ALLOW_SCANNED_PDF'] = '0';

        try {
            $png = $this->write('shot.png', $this->onePixel('png'));
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/AD-POL-AI-GOV-001/');
            $this->reader('imageSegments', $png, 'png');
        } finally {
            putenv('SMARTUW_ALLOW_SCANNED_PDF');
            unset($_ENV['SMARTUW_ALLOW_SCANNED_PDF'], $_SERVER['SMARTUW_ALLOW_SCANNED_PDF']);
        }
    }

    public function test_a_huge_text_upload_is_capped_not_refused(): void
    {
        $path = $this->write('big.csv', str_repeat("A,1000,0.001,1\n", 30000));

        $text = $this->reader('textSegments', $path)[0]['text'];

        $this->assertLessThanOrEqual(200000, mb_strlen($text));
        $this->assertStringContainsString('A,1000', $text);
    }

    /** A real .docx: a zip holding one table in word/document.xml. */
    private function minimalDocx(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . '<w:p><w:r><w:t>GOODS IN TRANSIT</w:t></w:r></w:p>'
            . '<w:tbl>'
            . '<w:tr><w:tc><w:p><w:r><w:t>Estimated carry per annum</w:t></w:r></w:p></w:tc>'
            . '<w:tc><w:p><w:r><w:t>4500000</w:t></w:r></w:p></w:tc></w:tr>'
            . '<w:tr><w:tc><w:p><w:r><w:t>Load limit per load</w:t></w:r></w:p></w:tc>'
            . '<w:tc><w:p><w:r><w:t>500000</w:t></w:r></w:p></w:tc></w:tr>'
            . '</w:tbl></w:body></w:document>';

        $path = $this->dir . '/build.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types/>');
        $zip->addFromString('word/document.xml', $xml);
        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }

    /** Smallest valid image GD can produce in the given format. */
    private function onePixel(string $format): string
    {
        $im = imagecreatetruecolor(2, 2);
        ob_start();
        $format === 'gif' ? imagegif($im) : imagepng($im);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }
}
