<?php

namespace App\Services;

use ZipArchive;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SimpleExcelService
{
    /**
     * Export data to XLSX using PhpSpreadsheet or native ZipArchive OpenXML
     */
    public static function exportXlsx(array $headers, array $rows, string $filename = 'export.xlsx', string $sheetTitle = 'Data'): StreamedResponse
    {
        // 1. If PhpSpreadsheet is available, use it
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($sheetTitle, 0, 31));

            // Headers
            $colIndex = 1;
            foreach ($headers as $headerText) {
                $cellCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . '1';
                $sheet->setCellValue($cellCoord, $headerText);
                $colIndex++;
            }

            // Header styling
            $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $headerRange = "A1:{$lastColLetter}1";
            $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE));
            $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF0073C6');
            $sheet->getStyle($headerRange)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(28);

            // Data rows
            $rowIndex = 2;
            foreach ($rows as $row) {
                $colIdx = 1;
                foreach ($row as $val) {
                    $cellCoord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx) . $rowIndex;
                    $sheet->setCellValueExplicit($cellCoord, (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    $colIdx++;
                }
                $rowIndex++;
            }

            for ($i = 1; $i <= count($headers); $i++) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension($colLetter)->setAutoSize(true);
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'max-age=0',
            ]);
        }

        // 2. Fallback: Pure native XLSX using ZipArchive (Zero external dependency)
        if (class_exists(ZipArchive::class)) {
            $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_');
            $zip = new ZipArchive();
            if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // [Content_Types].xml
                $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                    . '<Default Extension="xml" ContentType="application/xml"/>'
                    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                    . '</Types>';
                $zip->addFromString('[Content_Types].xml', $contentTypes);

                // _rels/.rels
                $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                    . '</Relationships>';
                $zip->addFromString('_rels/.rels', $rootRels);

                // xl/_rels/workbook.xml.rels
                $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                    . '</Relationships>';
                $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

                // xl/workbook.xml
                $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                    . '<sheets>'
                    . '<sheet name="' . htmlspecialchars(substr($sheetTitle, 0, 31), ENT_XML1) . '" sheetId="1" r:id="rId1"/>'
                    . '</sheets>'
                    . '</workbook>';
                $zip->addFromString('xl/workbook.xml', $wb);

                // xl/worksheets/sheet1.xml
                $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
                    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                    . '<sheetData>';

                // Header Row
                $sheetXml .= '<row r="1">';
                $colIdx = 1;
                foreach ($headers as $h) {
                    $colLetter = self::colLetter($colIdx);
                    $escaped = htmlspecialchars((string)$h, ENT_XML1);
                    $sheetXml .= '<c r="' . $colLetter . '1" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
                    $colIdx++;
                }
                $sheetXml .= '</row>';

                // Data Rows
                $rowNum = 2;
                foreach ($rows as $r) {
                    $sheetXml .= '<row r="' . $rowNum . '">';
                    $colIdx = 1;
                    foreach ($r as $val) {
                        $colLetter = self::colLetter($colIdx);
                        $escaped = htmlspecialchars((string)$val, ENT_XML1);
                        $sheetXml .= '<c r="' . $colLetter . $rowNum . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
                        $colIdx++;
                    }
                    $sheetXml .= '</row>';
                    $rowNum++;
                }

                $sheetXml .= '</sheetData></worksheet>';
                $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
                $zip->close();

                return response()->streamDownload(function () use ($tempFile) {
                    readfile($tempFile);
                    @unlink($tempFile);
                }, $filename, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'Cache-Control' => 'max-age=0',
                ]);
            }
        }

        // 3. Absolute Fallback: CSV with UTF-8 BOM
        $csvFilename = str_replace('.xlsx', '.csv', $filename);
        return self::exportCsv($headers, $rows, $csvFilename);
    }

    /**
     * Export data to CSV
     */
    public static function exportCsv(array $headers, array $rows, string $filename = 'export.csv'): StreamedResponse
    {
        $headersResp = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($output, $headers, ';');
            foreach ($rows as $r) {
                fputcsv($output, $r, ';');
            }
            fclose($output);
        }, 200, $headersResp);
    }

    /**
     * Parse uploaded file (XLSX, XLS, CSV) into array of rows
     */
    public static function parseFile(string $filePath, string $extension): array
    {
        $ext = strtolower($extension);

        // 1. Try PhpSpreadsheet if available
        if (in_array($ext, ['xlsx', 'xls']) && class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $rawRows = $sheet->toArray(null, true, true, false);
                $rows = [];
                foreach ($rawRows as $r) {
                    if (empty($r)) continue;
                    $hasContent = false;
                    foreach ($r as $c) {
                        if ($c !== null && trim((string)$c) !== '') {
                            $hasContent = true;
                            break;
                        }
                    }
                    if ($hasContent) {
                        $rows[] = array_map(fn($v) => $v !== null ? trim((string)$v) : '', $r);
                    }
                }
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                // fallback to native zip parser
            }
        }

        // 2. Native XLSX parser using ZipArchive + SimpleXML
        if ($ext === 'xlsx' && class_exists(ZipArchive::class)) {
            $zip = new ZipArchive();
            if ($zip->open($filePath) === true) {
                // Read shared strings if any
                $sharedStrings = [];
                $ssXml = $zip->getFromName('xl/sharedStrings.xml');
                if ($ssXml) {
                    $xml = simplexml_load_string($ssXml);
                    if ($xml && isset($xml->si)) {
                        foreach ($xml->si as $si) {
                            $sharedStrings[] = (string)($si->t ?? $si->r->t ?? '');
                        }
                    }
                }

                // Read sheet1.xml
                $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                $zip->close();

                if ($sheetXml) {
                    $xml = simplexml_load_string($sheetXml);
                    if ($xml && isset($xml->sheetData->row)) {
                        $rows = [];
                        foreach ($xml->sheetData->row as $rowNode) {
                            $row = [];
                            foreach ($rowNode->c as $cell) {
                                $type = (string)($cell['t'] ?? '');
                                $val = (string)($cell->v ?? '');
                                if ($type === 's') {
                                    $val = $sharedStrings[(int)$val] ?? '';
                                } elseif ($type === 'inlineStr') {
                                    $val = (string)($cell->is->t ?? '');
                                }
                                $row[] = trim($val);
                            }
                            if (!empty(array_filter($row, fn($v) => $v !== ''))) {
                                $rows[] = $row;
                            }
                        }
                        if (!empty($rows)) {
                            return $rows;
                        }
                    }
                }
            }
        }

        // 3. Fallback: CSV Parsing with auto-delimiter detection
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return [];
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ';';
        if (substr_count($firstLine, ',') > substr_count($firstLine, ';')) {
            $delimiter = ',';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ';')) {
            $delimiter = "\t";
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty($data)) continue;
            $hasContent = false;
            foreach ($data as $c) {
                if ($c !== null && trim((string)$c) !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if ($hasContent) {
                $rows[] = array_map(fn($v) => $v !== null ? trim((string)$v) : '', $data);
            }
        }
        fclose($handle);

        return $rows;
    }

    private static function colLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $index = (int)(($index - $mod) / 26);
        }
        return $letter;
    }
}
