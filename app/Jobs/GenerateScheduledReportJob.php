<?php

namespace App\Jobs;

use App\Models\ReportTemplate;
use App\Models\SavedReport;
use App\Models\ScheduledReport;
use App\Models\ScheduledReportExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GenerateScheduledReportJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public ScheduledReport $scheduledReport) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $execution = null;

        try {
            $report = $this->scheduledReport;
            $template = $report->template;

            if (! $template) {
                throw new \Exception('Report template not found');
            }

            // Generate report data based on template type and filters
            $reportData = $this->generateReportData($template, $report->filters);

            // Export to configured format
            $format = $report->export_format ?? 'csv';
            $fileContent = $this->exportToFormat($reportData, $format, $template);

            // Save to saved_reports table
            $savedReport = SavedReport::create([
                'tenant_id' => $report->tenant_id,
                'template_id' => $template->id,
                'created_by' => $report->created_by,
                'name' => $report->name . ' - ' . now()->format('Y-m-d H:i:s'),
                'description' => 'Generated from scheduled report: ' . $report->name,
                'type' => $report->type,
                'filters' => $report->filters,
                'data' => $reportData,
                'export_format' => $format,
                'file_path' => null, // Could be stored in storage if needed
                'file_size' => strlen($fileContent),
            ]);

            // Create successful execution record
            $execution = ScheduledReportExecution::create([
                'scheduled_report_id' => $report->id,
                'tenant_id' => $report->tenant_id,
                'executed_at' => now(),
                'success' => true,
                'records_count' => count($reportData),
                'file_format' => $format,
                'file_size' => strlen($fileContent),
                'recipients_notified' => $report->recipients,
            ]);

            // Send email to recipients with attachment
            $this->sendReportEmail($report, $savedReport, $fileContent, $format);

            // Update next run time
            $report->updateNextRun();

            Log::info('Scheduled report generated successfully', [
                'scheduled_report_id' => $report->id,
                'records_count' => count($reportData),
            ]);
        } catch (\Exception $e) {
            Log::error('Scheduled report generation failed', [
                'scheduled_report_id' => $this->scheduledReport->id,
                'error' => $e->getMessage(),
            ]);

            // Create failed execution record
            if ($execution === null) {
                ScheduledReportExecution::create([
                    'scheduled_report_id' => $this->scheduledReport->id,
                    'tenant_id' => $this->scheduledReport->tenant_id,
                    'executed_at' => now(),
                    'success' => false,
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Generate report data based on template type and filters.
     */
    protected function generateReportData(ReportTemplate $template, ?array $filters): array
    {
        $type = $template->type;
        $tenantId = $this->scheduledReport->tenant_id;
        $filters = $filters ?? [];

        return match ($type) {
            'sales' => $this->generateSalesReport($tenantId, $filters),
            'inventory' => $this->generateInventoryReport($tenantId, $filters),
            'orders' => $this->generateOrdersReport($tenantId, $filters),
            'customers' => $this->generateCustomersReport($tenantId, $filters),
            default => [],
        };
    }

    /**
     * Generate sales report data.
     */
    protected function generateSalesReport(int $tenantId, array $filters): array
    {
        $query = DB::table('orders')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['confirmed', 'fulfilled']);

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        return $query
            ->selectRaw('
                id,
                order_number,
                status,
                subtotal,
                tax,
                discount,
                shipping,
                total,
                created_at
            ')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Generate inventory report data.
     */
    protected function generateInventoryReport(int $tenantId, array $filters): array
    {
        $query = DB::table('inventories')
            ->join('products', 'inventories.product_id', '=', 'products.id')
            ->where('inventories.tenant_id', $tenantId);

        if (isset($filters['warehouse_id'])) {
            $query->where('inventories.warehouse_id', $filters['warehouse_id']);
        }

        if (isset($filters['store_id'])) {
            $query->where('inventories.store_id', $filters['store_id']);
        }

        return $query
            ->selectRaw('
                inventories.id,
                products.name as product_name,
                products.sku,
                inventories.quantity,
                inventories.reserved,
                inventories.available,
                inventories.cost
            ')
            ->orderBy('products.name')
            ->get()
            ->toArray();
    }

    /**
     * Generate orders report data.
     */
    protected function generateOrdersReport(int $tenantId, array $filters): array
    {
        $query = DB::table('orders')
            ->where('tenant_id', $tenantId);

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->selectRaw('
                id,
                order_number,
                status,
                subtotal,
                tax,
                discount,
                shipping,
                total,
                created_at
            ')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Generate customers report data.
     */
    protected function generateCustomersReport(int $tenantId, array $filters): array
    {
        $query = DB::table('customers')
            ->where('tenant_id', $tenantId);

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        return $query
            ->selectRaw('
                id,
                name,
                email,
                phone,
                created_at
            ')
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    /**
     * Export report data to specified format.
     */
    protected function exportToFormat(array $reportData, string $format, ReportTemplate $template): string
    {
        if ($format === 'csv') {
            return $this->exportToCsv($reportData);
        }

        // For PDF or other formats, return JSON for now
        return json_encode($reportData, JSON_PRETTY_PRINT);
    }

    /**
     * Export report data to CSV format.
     */
    protected function exportToCsv(array $reportData): string
    {
        if (empty($reportData)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Write headers
        $headers = array_keys((array) $reportData[0]);
        fputcsv($output, $headers);

        // Write data
        foreach ($reportData as $row) {
            fputcsv($output, (array) $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Send report email to recipients with attachment.
     */
    protected function sendReportEmail(
        ScheduledReport $report,
        SavedReport $savedReport,
        string $fileContent,
        string $format
    ): void {
        $recipients = $report->recipients ?? [];

        if (empty($recipients)) {
            return;
        }

        // In a real implementation, you would use Laravel's Mail facade
        // with a Mailable class that attaches the report file
        // For now, we'll log the email sending action

        Log::info('Report email would be sent', [
            'recipients' => $recipients,
            'report_id' => $savedReport->id,
            'format' => $format,
        ]);

        // Example implementation (uncomment when Mailable is created):
        // foreach ($recipients as $email) {
        //     Mail::to($email)->send(new ReportReadyMail($savedReport, $fileContent, $format));
        // }
    }
}
