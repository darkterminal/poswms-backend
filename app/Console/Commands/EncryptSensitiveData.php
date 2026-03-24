<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\Webhook;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

#[Signature('app:encrypt-sensitive-data {--model= : Specific model to encrypt} {--batch=100 : Records per batch} {--dry-run : Test without saving}')]
#[Description('Gradually encrypt sensitive data in database for rollout')]
class EncryptSensitiveData extends Command
{
    /**
     * Models and their sensitive fields to encrypt.
     */
    private array $modelsToEncrypt = [
        Webhook::class => ['secret', 'headers'],
        Customer::class => ['tax_id', 'email', 'phone', 'settings'],
        Tenant::class => ['email', 'phone', 'settings'],
        Store::class => ['email', 'phone', 'settings'],
        Warehouse::class => ['email', 'phone', 'settings'],
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔐 Starting sensitive data encryption rollout...');
        $this->newLine();

        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch');
        $specificModel = $this->option('model');

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        $models = $specificModel
            ? [$specificModel => $this->modelsToEncrypt[$specificModel] ?? []]
            : $this->modelsToEncrypt;

        foreach ($models as $modelClass => $fields) {
            if (empty($fields)) {
                continue;
            }

            $this->processModel($modelClass, $fields, $batchSize, $dryRun);
        }

        $this->newLine();
        $this->info('✅ Encryption rollout completed!');

        return self::SUCCESS;
    }

    /**
     * Process a specific model class.
     */
    private function processModel(string $modelClass, array $fields, int $batchSize, bool $dryRun): void
    {
        $modelName = class_basename($modelClass);
        $this->info("Processing {$modelName}...");

        $totalRecords = $modelClass::count();

        if ($totalRecords === 0) {
            $this->warn("  No {$modelName} records found");
            $this->newLine();

            return;
        }

        $bar = $this->output->createProgressBar($totalRecords);
        $bar->start();

        $encryptedCount = 0;
        $skippedCount = 0;

        $modelClass::query()
            ->orderBy('id')
            ->chunk($batchSize, function ($records) use ($fields, $dryRun, $bar, &$encryptedCount, &$skippedCount) {
                foreach ($records as $record) {
                    $needsEncryption = false;

                    // Check if record needs encryption
                    foreach ($fields as $field) {
                        if ($record->$field && ! $this->isEncrypted($record, $field)) {
                            $needsEncryption = true;
                            break;
                        }
                    }

                    if ($needsEncryption) {
                        if (! $dryRun) {
                            // Touch the record to trigger encryption via casts
                            $record->touch();
                        }
                        $encryptedCount++;
                    } else {
                        $skippedCount++;
                    }

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine();
        $this->info("  ✓ Encrypted: {$encryptedCount}, Skipped (already encrypted): {$skippedCount}");
        $this->newLine();
    }

    /**
     * Check if a field value appears to be encrypted.
     *
     * Encrypted values are base64-encoded and typically start with "eyJ" (for JSON)
     * or other base64 characters. This is a heuristic check.
     */
    private function isEncrypted(Model $model, string $field): bool
    {
        $value = $model->getAttributes()[$field] ?? null;

        if (! $value || ! is_string($value)) {
            return false;
        }

        // Encrypted values are base64-encoded
        // They're typically longer and have base64 characteristics
        if (strlen($value) < 20) {
            return false;
        }

        // Check if it looks like base64
        if (! preg_match('/^[A-Za-z0-9+\/]+=*$/', $value)) {
            return false;
        }

        // Try to decode - if it fails, it's not valid base64 (likely plaintext)
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        // If it decodes to valid JSON or printable text, likely encrypted
        return true;
    }
}
