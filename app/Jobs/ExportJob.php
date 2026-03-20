<?php

namespace App\Jobs;

use App\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param  string  $type  Export type (sales_revenue, orders, inventory, etc.)
     * @param  array<string, mixed>  $data  Data to export
     * @param  array<string, string>  $columns  Column headers
     * @param  string  $format  Export format (csv, pdf)
     * @param  int  $tenantId  Tenant ID
     * @param  int|null  $userId  User ID who requested the export
     */
    public function __construct(
        public string $type,
        public array $data,
        public array $columns,
        public string $format,
        public int $tenantId,
        public ?int $userId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ExportService $exportService): void
    {
        Log::info("Processing export job: {$this->type} for tenant {$this->tenantId}");

        $filename = $this->generateFilename();

        try {
            if ($this->format === 'csv') {
                $csvContent = $exportService->generateCsvString($this->data, $this->columns);

                // Store the file
                $path = "exports/{$this->tenantId}/{$filename}";
                Storage::disk('local')->put($path, $csvContent);

                Log::info("Export file created: {$path}");

                // Optionally notify user (implement notification system)
                // Notification::send($user, new ExportCompleted($path));
            } else {
                Log::warning("Unsupported export format: {$this->format}");
            }
        } catch (\Exception $e) {
            Log::error("Export job failed: {$e->getMessage()}", [
                'type' => $this->type,
                'tenant_id' => $this->tenantId,
            ]);
            throw $e;
        }
    }

    /**
     * Generate a unique filename for the export.
     */
    private function generateFilename(): string
    {
        $timestamp = now()->format('Y-m-d_His');
        $extension = $this->format === 'csv' ? 'csv' : 'pdf';

        return "{$this->type}_{$timestamp}.{$extension}";
    }

    /**
     * Get the tags for the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            "export:{$this->type}",
            "tenant:{$this->tenantId}",
        ];
    }
}
