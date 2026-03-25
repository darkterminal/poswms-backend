<?php

namespace App\Console\Commands;

use App\Models\Webhook;
use App\Services\UrlValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scan existing webhooks for SSRF vulnerabilities.
 *
 * This command scans all existing webhook URLs and identifies those that:
 * - Point to private/internal IP addresses
 * - Point to localhost or loopback addresses
 * - Point to cloud metadata endpoints
 * - Would be blocked by current SSRF protection
 *
 * Existing valid webhooks continue working but are flagged for review.
 */
class WebhookSsrfScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:scan-webhooks {--fix : Mark risky webhooks as inactive} {--json : Output results as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan existing webhook URLs for SSRF vulnerabilities and flag risky URLs';

    /**
     * The URL validation service instance.
     */
    private UrlValidationService $urlValidator;

    /**
     * Results array for tracking scan statistics.
     */
    private array $scanResults = [
        'total' => 0,
        'safe' => 0,
        'risky' => 0,
        'deactivated' => 0,
        'details' => [],
    ];

    /**
     * Execute the console command.
     */
    public function handle(UrlValidationService $urlValidator): int
    {
        $this->urlValidator = $urlValidator;

        $this->info('🔍 Starting webhook SSRF vulnerability scan...');
        $this->newLine();

        $webhooks = Webhook::with('tenant')->get();
        $this->scanResults['total'] = $webhooks->count();

        if ($webhooks->isEmpty()) {
            $this->info('✅ No webhooks found. Nothing to scan.');

            return Command::SUCCESS;
        }

        $this->info("📊 Found {$webhooks->count()} webhook(s) to scan...");
        $this->newLine();

        foreach ($webhooks as $webhook) {
            $this->scanWebhook($webhook);
        }

        $this->printReport();

        if ($this->option('json')) {
            $this->outputJson();
        }

        return $this->scanResults['risky'] > 0 ? Command::WARNING : Command::SUCCESS;
    }

    /**
     * Scan a single webhook URL.
     */
    private function scanWebhook(Webhook $webhook): void
    {
        $result = $this->urlValidator->validateUrl(
            $webhook->url,
            allowLegacy: true, // Allow existing URLs but flag them
            tenantId: $webhook->tenant_id,
            userId: null // System scan, no specific user
        );

        $webhookInfo = [
            'id' => $webhook->id,
            'tenant_id' => $webhook->tenant_id,
            'tenant_name' => $webhook->tenant?->name ?? 'Unknown',
            'name' => $webhook->name,
            'url' => $webhook->url,
            'active' => $webhook->active,
            'created_at' => $webhook->created_at?->toIso8601String(),
        ];

        if ($result['valid']) {
            $this->scanResults['safe']++;

            if (! $this->option('json')) {
                $this->line("✅ <fg=green>SAFE</>: {$webhook->name} ({$webhook->url})");
            }
        } else {
            $this->scanResults['risky']++;

            $riskDetails = [
                ...$webhookInfo,
                'risk_reason' => $result['error'] ?? 'Unknown risk',
                'risk_level' => $result['risk_level'] ?? 'high',
            ];

            $this->scanResults['details'][] = $riskDetails;

            if (! $this->option('json')) {
                $this->error("⚠️  <fg=red>RISKY</>: {$webhook->name} ({$webhook->url})");
                $this->error("    Reason: {$result['error']}");
            }

            // Log the risky webhook
            Log::warning('Webhook SSRF risk detected', [
                'webhook_id' => $webhook->id,
                'tenant_id' => $webhook->tenant_id,
                'url' => $webhook->url,
                'reason' => $result['error'],
                'risk_level' => $result['risk_level'] ?? 'high',
            ]);

            // Optionally deactivate risky webhooks
            if ($this->option('fix') && $webhook->active) {
                $webhook->update(['active' => false]);
                $this->scanResults['deactivated']++;

                if (! $this->option('json')) {
                    $this->warn('    🔒 Deactivated webhook');
                }

                Log::info('Webhook deactivated due to SSRF risk', [
                    'webhook_id' => $webhook->id,
                    'tenant_id' => $webhook->tenant_id,
                    'url' => $webhook->url,
                ]);
            }
        }
    }

    /**
     * Print the scan report to console.
     */
    private function printReport(): void
    {
        $this->newLine();
        $this->info('📈 ' . str_repeat('=', 60));
        $this->info('📊 SCAN REPORT');
        $this->info('📈 ' . str_repeat('=', 60));
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Webhooks', $this->scanResults['total']],
                ['✅ Safe', $this->scanResults['safe']],
                ['⚠️  Risky', $this->scanResults['risky']],
                ['🔒 Deactivated', $this->scanResults['deactivated']],
            ]
        );

        if ($this->scanResults['risky'] > 0) {
            $this->newLine();
            $this->warn('⚠️  WARNING: Risky webhooks detected!');
            $this->newLine();
            $this->warn('Recommended actions:');
            $this->warn('1. Review the risky webhooks listed above');
            $this->warn('2. Contact tenant administrators to update URLs');
            $this->warn('3. Run with --fix flag to automatically deactivate risky webhooks');
            $this->warn('4. Monitor audit logs for SSRF attempts');
            $this->newLine();
        } else {
            $this->info('✅ All webhooks passed SSRF validation!');
        }
    }

    /**
     * Output results as JSON.
     */
    private function outputJson(): void
    {
        $this->output->writeln(json_encode($this->scanResults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
