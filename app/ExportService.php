<?php

namespace App;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Export data to CSV format.
     *
     * @param  array<int, array<string, mixed>>  $data  Data to export
     * @param  array<string, string>  $columns  Column headers (key => label)
     * @param  string  $filename  Output filename
     */
    public function exportCsv(array $data, array $columns, string $filename = 'export.csv'): StreamedResponse
    {
        return Response::streamDownload(function () use ($data, $columns) {
            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8 encoding
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Write header row
            fputcsv($output, array_values($columns));

            // Write data rows
            foreach ($data as $row) {
                $rowData = [];
                foreach (array_keys($columns) as $key) {
                    $value = $row[$key] ?? '';
                    $rowData[] = $this->formatCsvValue($value);
                }
                fputcsv($output, $rowData);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export data to CSV from a collection.
     *
     * @param  Collection<int, array<string, mixed>>  $collection  Data to export
     * @param  array<string, string>  $columns  Column headers (key => label)
     * @param  string  $filename  Output filename
     */
    public function exportCollectionCsv(Collection $collection, array $columns, string $filename = 'export.csv'): StreamedResponse
    {
        return $this->exportCsv($collection->toArray(), $columns, $filename);
    }

    /**
     * Export data to PDF format.
     *
     * @param  array<string, mixed>  $data  Data for the PDF
     * @param  string  $view  View to render
     * @param  string  $filename  Output filename
     */
    public function exportPdf(array $data, string $view, string $filename = 'export.pdf'): StreamedResponse
    {
        // For now, return HTML that can be printed as PDF
        // In production, you would use a library like DomPDF or Snappy
        $html = view($view, $data)->render();

        return Response::make($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Generate CSV content as string.
     *
     * @param  array<int, array<string, mixed>>  $data  Data to export
     * @param  array<string, string>  $columns  Column headers (key => label)
     */
    public function generateCsvString(array $data, array $columns): string
    {
        $output = fopen('php://temp', 'r+');

        // Add BOM for UTF-8 encoding
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write header row
        fputcsv($output, array_values($columns));

        // Write data rows
        foreach ($data as $row) {
            $rowData = [];
            foreach (array_keys($columns) as $key) {
                $value = $row[$key] ?? '';
                $rowData[] = $this->formatCsvValue($value);
            }
            fputcsv($output, $rowData);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Format a value for CSV export.
     *
     * @param  mixed  $value  Value to format
     */
    private function formatCsvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Get available export formats.
     *
     * @return array<string, string>
     */
    public function getAvailableFormats(): array
    {
        return [
            'csv' => 'CSV (Comma Separated Values)',
            'pdf' => 'PDF (Portable Document Format)',
        ];
    }
}
