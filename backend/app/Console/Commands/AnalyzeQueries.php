<?php

namespace AlphaDirect\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AnalyzeQueries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queries:analyze 
                            {--fix : Generate fixed query suggestions}
                            {--output= : Output file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze queries that join on policyNumber and suggest optimizations';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Analyzing queries that use policyNumber joins...');
        
        $issues = [];
        
        // Scan PHP files for problematic patterns
        $files = File::allFiles(app_path());
        
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            
            $content = File::get($file->getPathname());
            $lines = explode("\n", $content);
            
            foreach ($lines as $lineNum => $line) {
                // Find joins on policyNumber
                if (preg_match('/join.*policyNumber|policyNumber.*join/i', $line)) {
                    $issues[] = [
                        'file' => $file->getRelativePathname(),
                        'line' => $lineNum + 1,
                        'code' => trim($line),
                        'type' => 'join_on_policyNumber'
                    ];
                }
                
                // Find where clauses on policyNumber
                if (preg_match('/where.*policyNumber.*=|whereHas.*policyNumber/i', $line)) {
                    $issues[] = [
                        'file' => $file->getRelativePathname(),
                        'line' => $lineNum + 1,
                        'code' => trim($line),
                        'type' => 'where_on_policyNumber'
                    ];
                }
            }
        }
        
        $this->info("Found " . count($issues) . " potential issues");
        
        // Group by file
        $grouped = [];
        foreach ($issues as $issue) {
            $grouped[$issue['file']][] = $issue;
        }
        
        $output = [];
        $output[] = "# Query Analysis Report";
        $output[] = "Generated: " . date('Y-m-d H:i:s');
        $output[] = "";
        $output[] = "## Summary";
        $output[] = "- Total issues found: " . count($issues);
        $output[] = "- Files affected: " . count($grouped);
        $output[] = "";
        
        foreach ($grouped as $file => $fileIssues) {
            $output[] = "## File: {$file}";
            $output[] = "";
            
            foreach ($fileIssues as $issue) {
                $output[] = "### Line {$issue['line']}: {$issue['type']}";
                $output[] = "```php";
                $output[] = $issue['code'];
                $output[] = "```";
                
                if ($this->option('fix')) {
                    $suggestion = $this->generateFix($issue);
                    if ($suggestion) {
                        $output[] = "**Suggested Fix:**";
                        $output[] = "```php";
                        $output[] = $suggestion;
                        $output[] = "```";
                    }
                }
                
                $output[] = "";
            }
        }
        
        $outputText = implode("\n", $output);
        
        if ($outputFile = $this->option('output')) {
            File::put($outputFile, $outputText);
            $this->info("Report saved to: {$outputFile}");
        } else {
            $this->line($outputText);
        }
        
        return 0;
    }
    
    /**
     * Generate fix suggestion for an issue
     */
    private function generateFix(array $issue): ?string
    {
        $code = $issue['code'];
        
        // Fix join patterns
        if (preg_match('/join\([\'"](\w+)[\'"],\s*[\'"](\w+)\.policyNumber[\'"],\s*[\'"]policies\.policyNumber[\'"]\)/i', $code, $matches)) {
            $table = $matches[1];
            $alias = $matches[2];
            return "->join('{$table}', '{$alias}.policy_id', '=', 'policies.id')";
        }
        
        if (preg_match('/join\([\'"](\w+)[\'"],\s*[\'"](\w+)\.policyNumber[\'"],\s*[\'"]=\'[\'"],\s*[\'"]policies\.policyNumber[\'"]\)/i', $code, $matches)) {
            $table = $matches[1];
            $alias = $matches[2];
            return "->join('{$table}', '{$alias}.policy_id', '=', 'policies.id')";
        }
        
        // Fix leftJoin patterns
        if (preg_match('/leftJoin\([\'"](\w+)[\'"],\s*[\'"](\w+)\.policyNumber[\'"],\s*[\'"]policies\.policyNumber[\'"]\)/i', $code, $matches)) {
            $table = $matches[1];
            $alias = $matches[2];
            return "->leftJoin('{$table}', '{$alias}.policy_id', '=', 'policies.id')";
        }
        
        return null;
    }
}
