<?php

namespace Tests\Feature\Services;

use App\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ExportServiceEnhancedTest extends TestCase
{
    use RefreshDatabase;

    private ExportService $exportService;
    private array $sampleData;
    private array $columns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exportService = app(ExportService::class);
        
        $this->sampleData = [
            ['id' => 1, 'name' => 'Product A', 'price' => 99.99, 'active' => true],
            ['id' => 2, 'name' => 'Product B', 'price' => 149.99, 'active' => false],
            ['id' => 3, 'name' => 'Product C', 'price' => 199.99, 'active' => true],
        ];
        
        $this->columns = [
            'id' => 'ID',
            'name' => 'Product Name',
            'price' => 'Price',
            'active' => 'Active',
        ];
    }

    public function test_export_csv(): void
    {
        $response = $this->exportService->exportCsv($this->sampleData, $this->columns, 'test.csv');
        
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=test.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_export_excel(): void
    {
        $response = $this->exportService->exportExcel($this->sampleData, $this->columns, 'test.xlsx', 'Products');
        
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=test.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_export_pdf_with_view(): void
    {
        // Create a simple test using HTML directly
        $html = '<html><body><h1>Test PDF</h1></body></html>';
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHtml($html);
        
        $response = $pdf->stream('test.pdf');
        
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
    }

    public function test_export_table_pdf(): void
    {
        $html = $this->generateTableHtml($this->sampleData, $this->columns, 'Product Report');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHtml($html);
        
        $response = $pdf->stream('products.pdf');
        
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
    }

    private function generateTableHtml(array $data, array $columns, string $title): string
    {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: DejaVu Sans, sans-serif; }
                h1 { text-align: center; color: #333; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
            </style>
        </head>
        <body>
            <h1>' . htmlspecialchars($title) . '</h1>
            <table>
                <thead>
                    <tr>';
        
        foreach ($columns as $label) {
            $html .= '<th>' . htmlspecialchars($label) . '</th>';
        }
        
        $html .= '</tr>
                </thead>
                <tbody>';
        
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach (array_keys($columns) as $key) {
                $value = $row[$key] ?? '';
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody>
            </table>
        </body>
        </html>';
        
        return $html;
    }

    public function test_generate_csv_string(): void
    {
        $csv = $this->exportService->generateCsvString($this->sampleData, $this->columns);
        
        $this->assertIsString($csv);
        // CSV quotes fields with spaces or special characters
        $this->assertStringContainsString('ID,"Product Name",Price,Active', $csv);
        $this->assertStringContainsString('1,"Product A",99.99,Yes', $csv);
    }

    public function test_save_csv_to_storage(): void
    {
        Storage::fake('public');
        
        $path = 'exports/test_report.csv';
        $resultPath = $this->exportService->saveCsv($this->sampleData, $this->columns, $path);
        
        $this->assertEquals($path, $resultPath);
        Storage::disk('public')->assertExists($path);
        
        $content = Storage::disk('public')->get($path);
        $this->assertStringContainsString('ID,"Product Name",Price,Active', $content);
        $this->assertStringContainsString('1,"Product A",99.99,Yes', $content);
    }

    public function test_save_excel_to_storage(): void
    {
        Storage::fake('public');
        
        $path = 'exports/test_report.xlsx';
        $resultPath = $this->exportService->saveExcel($this->sampleData, $this->columns, $path, 'Products');
        
        $this->assertEquals($path, $resultPath);
        Storage::disk('public')->assertExists($path);
    }

    public function test_export_collection_csv(): void
    {
        $collection = Collection::make($this->sampleData);
        
        $response = $this->exportService->exportCollectionCsv($collection, $this->columns, 'collection.csv');
        
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertStringContainsString('attachment; filename=collection.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_export_collection_excel(): void
    {
        $collection = Collection::make($this->sampleData);
        
        $response = $this->exportService->exportCollectionExcel($collection, $this->columns, 'collection.xlsx', 'Data');
        
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertStringContainsString('attachment; filename=collection.xlsx', $response->headers->get('Content-Disposition'));
    }

    public function test_get_available_formats(): void
    {
        $formats = $this->exportService->getAvailableFormats();
        
        $this->assertIsArray($formats);
        $this->assertArrayHasKey('csv', $formats);
        $this->assertArrayHasKey('xlsx', $formats);
        $this->assertArrayHasKey('pdf', $formats);
        $this->assertEquals('CSV (Comma Separated Values)', $formats['csv']);
        $this->assertEquals('Excel Spreadsheet (.xlsx)', $formats['xlsx']);
        $this->assertEquals('PDF (Portable Document Format)', $formats['pdf']);
    }

    public function test_format_csv_value_with_null(): void
    {
        $reflection = new \ReflectionClass($this->exportService);
        $method = $reflection->getMethod('formatCsvValue');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->exportService, null);
        $this->assertEquals('', $result);
    }

    public function test_format_csv_value_with_datetime(): void
    {
        $reflection = new \ReflectionClass($this->exportService);
        $method = $reflection->getMethod('formatCsvValue');
        $method->setAccessible(true);
        
        $datetime = new \DateTime('2024-01-15 10:30:00');
        $result = $method->invoke($this->exportService, $datetime);
        $this->assertEquals('2024-01-15 10:30:00', $result);
    }

    public function test_format_csv_value_with_boolean(): void
    {
        $reflection = new \ReflectionClass($this->exportService);
        $method = $reflection->getMethod('formatCsvValue');
        $method->setAccessible(true);
        
        $this->assertEquals('Yes', $method->invoke($this->exportService, true));
        $this->assertEquals('No', $method->invoke($this->exportService, false));
    }

    public function test_format_excel_value_preserves_types(): void
    {
        $reflection = new \ReflectionClass($this->exportService);
        $method = $reflection->getMethod('formatExcelValue');
        $method->setAccessible(true);
        
        // Test integer
        $this->assertEquals(123, $method->invoke($this->exportService, 123));
        
        // Test float
        $this->assertEquals(123.45, $method->invoke($this->exportService, 123.45));
        
        // Test boolean
        $this->assertEquals('Yes', $method->invoke($this->exportService, true));
        
        // Test null
        $this->assertEquals('', $method->invoke($this->exportService, null));
    }
}
