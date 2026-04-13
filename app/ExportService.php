<?php

namespace App;

use Illuminate\Database\Query\Builder;
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
     * Export data to CSV directly from a database query using chunking.
     * This method streams results in chunks to avoid loading all data into memory.
     *
     * @param  Builder|\Illuminate\Database\Eloquent\Builder  $query  Database query builder
     * @param  array<string, string>  $columns  Column headers (key => label)
     * @param  string  $filename  Output filename
     * @param  int  $chunkSize  Number of records per chunk (default 500)
     */
    public function exportCsvFromQuery($query, array $columns, string $filename = 'export.csv', int $chunkSize = 500): StreamedResponse
    {
        return Response::streamDownload(function () use ($query, $columns, $chunkSize) {
            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8 encoding
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Write header row
            fputcsv($output, array_values($columns));

            // Stream results in chunks to avoid memory issues
            $query->chunk($chunkSize, function ($results) use ($output, $columns) {
                foreach ($results as $row) {
                    $rowData = [];
                    // Convert object to array if needed
                    $rowArray = is_array($row) ? $row : (array) $row;

                    foreach (array_keys($columns) as $key) {
                        $value = $rowArray[$key] ?? '';
                        $rowData[] = $this->formatCsvValue($value);
                    }
                    fputcsv($output, $rowData);
                }
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export data to Excel format (.xlsx).
     *
     * @param  array<int, array<string, mixed>>  $data  Data to export
     * @param  array<string, string>  $columns  Column headers (key => label)
     * @param  string  $filename  Output filename
     * @param  string|null  $sheetName  Sheet name (max 31 characters)
     */
    public function exportExcel(array $data, array $columns, string $filename = 'export.xlsx', ?string $sheetName = null): StreamedResponse
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        // Set sheet name if provided
        if ($sheetName) {
            $sheet->setTitle(substr($sheetName, 0, 31));
        }

        // Set header style
        $headerStyle = [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFE0E0E0'],
            ],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];

        // Write header row
        $columnIndex = 1;
        foreach ($columns as $key => $label) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $label);
            $columnIndex++;
        }
        $sheet->getStyle('1:' . 1)->applyFromArray($headerStyle);

        // Write data rows
        $rowIndex = 2;
        foreach ($data as $row) {
            $columnIndex = 1;
            foreach (array_keys($columns) as $key) {
                $value = $row[$key] ?? '';
                $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $this->formatExcelValue($value));
                $columnIndex++;
            }
            $rowIndex++;
        }

        // Auto-size columns
        foreach (range(1, count($columns)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        // Set headers for download
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        return Response::streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export data to Excel from a collection.
     *
     * @param  Collection<int, array<string, mixed>>  $collection  Data to export
     * @param  array<string, string>  $columns  Column headers (key => label)
     * @param  string  $filename  Output filename
     * @param  string|null  $sheetName  Sheet name
     */
    public function exportCollectionExcel(Collection $collection, array $columns, string $filename = 'export.xlsx', ?string $sheetName = null): StreamedResponse
    {
        return $this->exportExcel($collection->toArray(), $columns, $filename, $sheetName);
    }

    /**
     * Export data to PDF format using DomPDF.
     *
     * @param  array<string, mixed>  $data  Data for the PDF
     * @param  string  $view  View to render
     * @param  string  $filename  Output filename
     * @param  string  $orientation  Page orientation (portrait or landscape)
     * @param  string  $paper  Paper size (a4, letter, legal, etc.)
     */
    public function exportPdf(array $data, string $view, string $filename = 'export.pdf', string $orientation = 'portrait', string $paper = 'a4'): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $data);
        $pdf->setPaper($paper, $orientation);

        return $pdf->stream($filename);
    }

    /**
     * Export a simple table to PDF.
     *
     * @param  array<int, array<string, mixed>>  $data  Data to export
     * @param  array<string, string>  $columns  Column headers (key => label)
     * @param  string  $filename  Output filename
     * @param  string  $title  Report title
     * @param  string  $orientation  Page orientation
     */
    public function exportTablePdf(array $data, array $columns, string $filename = 'export.pdf', string $title = 'Report', string $orientation = 'landscape'): \Symfony\Component\HttpFoundation\Response
    {
        $html = $this->generateTableHtml($data, $columns, $title);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHtml($html);
        $pdf->setPaper('a4', $orientation);

        return $pdf->stream($filename);
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
     * Save CSV file to storage.
     *
     * @param  array<int, array<string, mixed>>  $data  Data to export
     * @param  array<string, string>  $columns  Column headers (key => label)
     * @param  string  $path  File path in storage
     * @return string File path
     */
    public function saveCsv(array $data, array $columns, string $path): string
    {
        $csvContent = $this->generateCsvString($data, $columns);
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $csvContent);

        return $path;
    }

    /**
     * Save Excel file to storage.
     *
     * @param  array<int, array<string, mixed>>  $data  Data to export
     * @param  array<string, string>  $columns  Column headers (key => label)
     * @param  string  $path  File path in storage
     * @param  string|null  $sheetName  Sheet name
     * @return string File path
     */
    public function saveExcel(array $data, array $columns, string $path, ?string $sheetName = null): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        if ($sheetName) {
            $sheet->setTitle(substr($sheetName, 0, 31));
        }

        // Write header row
        $columnIndex = 1;
        foreach ($columns as $key => $label) {
            $sheet->setCellValueByColumnAndRow($columnIndex, 1, $label);
            $columnIndex++;
        }

        // Write data rows
        $rowIndex = 2;
        foreach ($data as $row) {
            $columnIndex = 1;
            foreach (array_keys($columns) as $key) {
                $value = $row[$key] ?? '';
                $sheet->setCellValueByColumnAndRow($columnIndex, $rowIndex, $this->formatExcelValue($value));
                $columnIndex++;
            }
            $rowIndex++;
        }

        // Auto-size columns
        foreach (range(1, count($columns)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        // Save to storage
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempFile);

        $content = file_get_contents($tempFile);
        \Illuminate\Support\Facades\Storage::disk('public')->put($path, $content);
        unlink($tempFile);

        return $path;
    }

    /**
     * Generate HTML table for PDF export.
     *
     * @param  array<int, array<string, mixed>>  $data  Data to export
     * @param  array<string, string>  $columns  Column headers (key => label)
     * @param  string  $title  Report title
     */
    private function generateTableHtml(array $data, array $columns, string $title = 'Report'): string
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
        tr:nth-child(even) { background-color: #f9f9f9; }
        .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <h1>' . e($title) . '</h1>
    <table>
        <thead>
            <tr>';

        foreach ($columns as $label) {
            $html .= '<th>' . e($label) . '</th>';
        }

        $html .= '</tr>
        </thead>
        <tbody>';

        foreach ($data as $row) {
            $html .= '<tr>';
            foreach (array_keys($columns) as $key) {
                $value = $row[$key] ?? '';
                $html .= '<td>' . e($this->formatCsvValue($value)) . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody>
    </table>
    <div class="footer">
        Generated on: ' . now()->format('Y-m-d H:i:s') . '
    </div>
</body>
</html>';

        return $html;
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
     * Format a value for Excel export.
     *
     * @param  mixed  $value  Value to format
     */
    private function formatExcelValue(mixed $value): string|int|float|\DateTimeInterface|null
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_numeric($value)) {
            return is_float($value) ? (float) $value : (int) $value;
        }

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toDateTime();
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
            'xlsx' => 'Excel Spreadsheet (.xlsx)',
            'pdf' => 'PDF (Portable Document Format)',
        ];
    }
}
