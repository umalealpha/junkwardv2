<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The claim-form library must be internally consistent: every form the seeder
 * points at must actually exist in the repo, or `publish-form-library` uploads a
 * dead reference and the send button attaches nothing.
 *
 * This reads the seeder SOURCE (not a booted app) so it runs anywhere, and
 * fails the moment someone adds a MAP row for a file that was never committed —
 * exactly the "slice is incomplete" class the review flagged.
 */
class ClaimFormLibraryCoverageTest extends TestCase
{
    private function seededFiles(): array
    {
        $src = (string) file_get_contents(__DIR__ . '/../../app/Console/Commands/SeedClaimFormLibrary.php');
        $start = strpos($src, 'private const MAP = [');
        $this->assertNotFalse($start, 'MAP not found in SeedClaimFormLibrary.');
        $block = substr($src, $start, strpos($src, '];', $start) - $start);

        preg_match_all("/'([a-z0-9-]+\\.pdf)'/", $block, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    public function test_every_seeded_form_file_exists_in_the_repo(): void
    {
        $dir = realpath(__DIR__ . '/../../resources/claim-forms');
        $this->assertNotFalse($dir, 'resources/claim-forms directory is missing.');

        $files = $this->seededFiles();
        $this->assertNotEmpty($files, 'The seeder MAP referenced no form files.');

        foreach ($files as $file) {
            $this->assertFileExists(
                $dir . '/' . $file,
                "The seeder maps a claim type to '{$file}', but that file is not in "
                . 'backend/resources/claim-forms/. publish-form-library would upload a dead '
                . 'reference and the send button would attach nothing.'
            );
        }
    }

    public function test_manifest_lists_every_repo_pdf(): void
    {
        $dir = realpath(__DIR__ . '/../../resources/claim-forms');
        $manifest = json_decode((string) file_get_contents($dir . '/manifest.json'), true);
        $this->assertIsArray($manifest, 'manifest.json is missing or invalid.');

        $listed = array_column($manifest, 'file');
        foreach (glob($dir . '/*.pdf') as $pdf) {
            $this->assertContains(
                basename($pdf),
                $listed,
                basename($pdf) . ' is in the repo but not listed in manifest.json.'
            );
        }
    }
}
