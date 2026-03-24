<?php

declare(strict_types = 1);

namespace App\Console\Commands;

use App\Services\VulnerabilityAuditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('security:audit-dependencies {--composer : Scan only Composer dependencies} {--npm : Scan only NPM dependencies} {--json : Output results as JSON} {--fail-on-critical : Exit with error code if critical vulnerabilities found}')]
#[Description('Scan PHP and JavaScript dependencies for known security vulnerabilities')]
class AuditDependencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:audit-dependencies
                            {--composer : Scan only Composer dependencies}
                            {--npm : Scan only NPM dependencies}
                            {--json : Output results as JSON}
                            {--fail-on-critical : Exit with error code if critical vulnerabilities found}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan PHP and JavaScript dependencies for known security vulnerabilities (OWASP A06:2021)';

    private VulnerabilityAuditService $auditService;

    /**
     * Execute the console command.
     */
    public function handle(VulnerabilityAuditService $auditService): int
    {
        $this->auditService = $auditService;

        $this->info('🔍 Starting dependency vulnerability audit...');
        $this->newLine();

        $composerOnly = $this->option('composer');
        $npmOnly = $this->option('npm');
        $jsonOutput = $this->option('json');
        $failOnCritical = $this->option('fail-on-critical');

        try {
            $results = [
                'composer' => null,
                'npm' => null,
            ];

            // Scan Composer dependencies
            if (! $npmOnly) {
                $this->info('Scanning PHP dependencies (Composer)...');
                $results['composer'] = $this->auditService->scanComposerDependencies();
                $this->displayResults('composer', $results['composer'], $jsonOutput);
            }

            // Scan NPM dependencies
            if (! $composerOnly) {
                $this->newLine();
                $this->info('Scanning JavaScript dependencies (NPM)...');
                $results['npm'] = $this->auditService->scanNpmDependencies();
                $this->displayResults('npm', $results['npm'], $jsonOutput);
            }

            // Generate summary
            $totalVulnerabilities = ($results['composer']['total_count'] ?? 0) + ($results['npm']['total_count'] ?? 0);
            $hasCritical = ($results['composer']['critical_count'] ?? 0) > 0 || ($results['npm']['critical_count'] ?? 0) > 0;

            $this->newLine();
            $this->displaySummary($results, $totalVulnerabilities, $hasCritical, $jsonOutput);

            // Log the audit execution
            Log::info('Dependency vulnerability audit completed', [
                'composer_vulnerabilities' => $results['composer']['total_count'] ?? 0,
                'npm_vulnerabilities' => $results['npm']['total_count'] ?? 0,
                'total_vulnerabilities' => $totalVulnerabilities,
                'has_critical' => $hasCritical,
            ]);

            // Exit with error code if critical vulnerabilities found and flag is set
            if ($failOnCritical && $hasCritical) {
                $this->error('❌ Critical vulnerabilities detected!');

                return self::FAILURE;
            }

            if ($totalVulnerabilities > 0) {
                $this->warn('⚠️  Vulnerabilities detected. Review the report above.');

                return self::SUCCESS;
            }

            $this->info('✅ No known vulnerabilities detected.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Audit failed: ' . $e->getMessage());
            Log::error('Dependency vulnerability audit failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Display scan results in appropriate format.
     */
    private function displayResults(string $source, array $results, bool $jsonOutput): void
    {
        if ($jsonOutput) {
            return; // JSON output handled in summary
        }

        if (! $results['success']) {
            $this->error("Failed to scan {$source} dependencies");

            return;
        }

        if ($results['total_count'] === 0) {
            $this->info("  ✅ No vulnerabilities found in {$source} packages");

            return;
        }

        $this->warn("  ⚠️  {$results['total_count']} vulnerability/ies found in {$source} packages:");

        $severityColors = [
            'critical' => 'error',
            'high' => 'error',
            'moderate' => 'comment',
            'medium' => 'comment',
            'low' => 'info',
            'info' => 'info',
        ];

        foreach ($results['vulnerabilities'] as $vulnerability) {
            $package = $vulnerability['packageName'] ?? $vulnerability['name'] ?? 'unknown';
            $severity = strtolower($vulnerability['severity'] ?? 'moderate');
            $title = $vulnerability['title'] ?? 'No title';

            $severityLabel = match ($severity) {
                'critical' => '🔴 CRITICAL',
                'high' => '🔴 HIGH',
                'moderate', 'medium' => '🟡 MODERATE',
                'low' => '🟢 LOW',
                default => '⚪ INFO',
            };

            $this->line("    {$severityLabel} {$package}: {$title}");

            if (isset($vulnerability['vulnerableVersionRange'])) {
                $this->line("      Vulnerable: {$vulnerability['vulnerableVersionRange']}");
            }

            if (isset($vulnerability['firstPatchedVersion'])) {
                $this->line("      Patched: {$vulnerability['firstPatchedVersion']}");
            }

            $this->newLine();
        }
    }

    /**
     * Display summary of all audit results.
     */
    private function displaySummary(array $results, int $totalVulnerabilities, bool $hasCritical, bool $jsonOutput): void
    {
        if ($jsonOutput) {
            $output = [
                'timestamp' => now()->toIso8601String(),
                'success' => true,
                'summary' => [
                    'total_vulnerabilities' => $totalVulnerabilities,
                    'has_critical' => $hasCritical,
                ],
                'composer' => $results['composer'],
                'npm' => $results['npm'],
            ];

            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->line(str_repeat('=', 70));
        $this->info('AUDIT SUMMARY');
        $this->line(str_repeat('=', 70));

        if ($results['composer']) {
            $this->line(sprintf(
                '  Composer:  %d vulnerabilities (C:%d H:%d M:%d L:%d)',
                $results['composer']['total_count'],
                $results['composer']['critical_count'],
                $results['composer']['high_count'],
                $results['composer']['moderate_count'],
                $results['composer']['low_count']
            ));
        }

        if ($results['npm']) {
            $this->line(sprintf(
                '  NPM:       %d vulnerabilities (C:%d H:%d M:%d L:%d I:%d)',
                $results['npm']['total_count'],
                $results['npm']['critical_count'],
                $results['npm']['high_count'],
                $results['npm']['moderate_count'],
                $results['npm']['low_count'],
                $results['npm']['info_count'] ?? 0
            ));
        }

        $this->line(str_repeat('=', 70));

        if ($hasCritical) {
            $this->error('  STATUS: 🔴 CRITICAL - Immediate action required');
        } elseif ($totalVulnerabilities > 0) {
            $this->warn("  STATUS: 🟡 WARNING - {$totalVulnerabilities} total vulnerability/ies detected");
        } else {
            $this->info('  STATUS: 🟢 SECURE - No known vulnerabilities');
        }

        $this->line(str_repeat('=', 70));
    }
}
