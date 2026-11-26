<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser;
use Spatie\PdfToText\Pdf;

class testController extends Controller
{
    public function index()
    {
        return view('payment');
    }

    public function upload(Request $request)
    {
        try {
            $request->validate([
                'pdf_file' => 'required|mimes:pdf'
            ]);

            $file = $request->file('pdf_file');
            $path = $file->getRealPath();

            // Verify file exists and is readable
            if (!file_exists($path) || !is_readable($path)) {
                Log::error('PDF file not accessible: ' . $path);
                return response()->json(['error' => 'PDF file not accessible'], 400);
            }

            // First attempt with Spatie's PDF to Text
            try {
                $text = (new Pdf())
                    ->setPdf($path)
                    ->text();
            } catch (\Exception $e) {
                Log::warning('Spatie PDF parser failed: ' . $e->getMessage());
                $text = '';
            }

            // Fallback to Smalot parser if Spatie returns empty
            if (empty(trim($text))) {
                try {
                    $parser = new Parser();
                    $pdf = $parser->parseFile($path);
                    $text = $pdf->getText();
                } catch (\Exception $e) {
                    Log::error('Smalot PDF parser failed: ' . $e->getMessage());
                    return response()->json(['error' => 'Could not extract text from PDF'], 500);
                }
            }

            // Verify we got text content
            if (empty(trim($text))) {
                Log::error('No text content extracted from PDF');
                return response()->json(['error' => 'No text content found in PDF'], 400);
            }

            Log::info('Extracted PDF text:', ['text' => $text]);

            // Extract header info
            try {
                $header = $this->extractHeader($text);
                if (empty($header['branch_batch']) && empty($header['date'])) {
                    Log::warning('Header extraction failed or incomplete', ['header' => $header]);
                }
            } catch (\Exception $e) {
                Log::error('Header extraction failed: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to extract header information'], 500);
            }

            // Extract table data
            try {
                $tableData = $this->extractTableData($text);
                if (empty($tableData['data'])) {
                    Log::warning('No table data extracted');
                    return response()->json(['error' => 'No table data found in PDF'], 400);
                }
            } catch (\Exception $e) {
                Log::error('Table data extraction failed: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to extract table data'], 500);
            }

            // Log successful extraction
            Log::info('PDF processed successfully', [
                'rows' => count($tableData['data']),
                'header' => $header
            ]);

            return response()->json([
                'header' => $header,
                'columns' => $tableData['columns'],
                'data' => $tableData['data']
            ]);
        } catch (\Exception $e) {
            Log::error('PDF processing failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'PDF processing failed: ' . $e->getMessage()], 500);
        }
    }

    private function extractHeader($text)
    {
        preg_match('/([A-Za-z]+)\s+([A-Za-z]+)\s*-\s*\d+\s*:/', $text, $branchMatch);
        preg_match('/BATCH\/OUT\/\d{4}\/\d+/', $text, $batchMatch);
        preg_match('/(\d{4}-\d{2}-\d{2})/', $text, $dateMatch);
        preg_match('/Issuing bank account\s*:\s*([0-9\- ]+)\s*-\s*(\w+)/', $text, $accountMatch);

        $branch_batch = '';
        if (!empty($branchMatch[0]) && !empty($batchMatch[0])) {
            $branch_batch = trim($branchMatch[0]) . ' ' . $batchMatch[0];
        }

        return [
            'branch_batch' => $branch_batch,
            'date' => $dateMatch[1] ?? $dateMatch[0] ?? '',
            'issuing_account' => isset($accountMatch[1], $accountMatch[2]) ? trim($accountMatch[1]) . ' - ' . $accountMatch[2] : '',
        ];
    }

    private function extractTableData($text)
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $tableStarted = false;
        $columns = ['Name', 'Customer', 'Date', 'Memo', 'Bank', 'Recipient Bank Account', 'Amount'];
        $data = [];
        $currentRow = null;
        $customerName = '';

        // Find table start (look for header row)
        foreach ($lines as $i => $line) {
            if (strpos($line, 'Name') !== false && strpos($line, 'Customer') !== false) {
                $tableStarted = true;
                continue;
            }

            if ($tableStarted) {
                $trimmedLine = trim($line);

                // Stop at total
                if (stripos($trimmedLine, 'TOTAL') === 0) {
                    break;
                }

                // Handle PB codes and data rows
                if (preg_match('/^PB[A-Z]+\/\d{4}\/\d{5}/', $trimmedLine)) {
                    if ($currentRow) {
                        $data[] = $currentRow;
                    }
                    $currentRow = $this->parseDataRow($trimmedLine, $lines, $i);
                }
            }
        }

        // Add final row
        if ($currentRow) {
            $data[] = $currentRow;
        }

        return [
            'columns' => $columns,
            'data' => $data
        ];
    }

    private function parseDataRow($line, $lines, $currentIndex)
    {
        $row = array_fill(0, 7, '');

        // Get code (Name)
        if (preg_match('/^(PB[A-Z]+\/\d{4}\/\d{5})(.*)/', $line, $matches)) {
            $row[0] = $matches[1];
            $customerName = trim($matches[2]);

            // Look ahead for additional customer name lines and date
            $i = $currentIndex + 1;
            while (isset($lines[$i]) && !preg_match('/^\d{2}\/\d{2}\/\d{4}/', $lines[$i])) {
                $customerName .= ' ' . trim($lines[$i]);
                $i++;
            }

            // Clean up customer name
            $row[1] = trim(preg_replace('/,(\s*PT)/', ' $1', $customerName));

            // Parse date and following lines
            if (isset($lines[$i])) {
                // Get date
                if (preg_match('/^(\d{2}\/\d{2}\/\d{4})(.*)/', $lines[$i], $dateMatch)) {
                    $row[2] = $dateMatch[1];

                    // Collect all details across multiple lines until next record or TOTAL
                    $detailLines = [];
                    $detailLines[] = trim($dateMatch[2]);
                    $j = $i + 1;
                    while (
                        isset($lines[$j]) &&
                        !preg_match('/^PB[A-Z]+\/\d{4}\/\d{5}/', $lines[$j]) &&
                        !preg_match('/^TOTAL/', $lines[$j])
                    ) {
                        $detailLines[] = trim($lines[$j]);
                        $j++;
                    }

                    // Combine detail lines
                    $details = implode(' ', array_filter($detailLines));

                    // Log combined details for debugging
                    Log::debug('Combined details:', ['details' => $details]);

                    // Try Mandiri format first with more flexible pattern
                    if (preg_match('/(?:(JO-\s*PML\/\d{4}\/\d+)|(\d+\s+THP\s+\d+\s+INST\.\d+))\s*(MANDIRI)\s*(\d+)\s*Rp\s*([\d,]+)/', $details, $fields)) {
                        $row[3] = trim($fields[1] ?: $fields[2]); // Memo (either JO-PML or THP format)
                        $row[4] = $fields[3];      // Bank
                        $row[5] = $fields[4];      // Account
                        $row[6] = 'Rp ' . $fields[5]; // Amount
                    }
                    // Try BCA/PERMATA format
                    elseif (preg_match('/([A-Z0-9\-\/]+(?:\/\d{2}\/\d{2}\/\d+)?)\s*((?:BCA|PERMATA))\s*(\d+)\s*Rp\s*([\d,]+)/', $details, $fields)) {
                        $row[3] = trim($fields[1]); // Memo
                        $row[4] = $fields[2];      // Bank
                        $row[5] = $fields[3];      // Account
                        $row[6] = 'Rp ' . $fields[4]; // Amount
                    }

                    // Log parsing results
                    Log::debug('Parsing results:', [
                        'original_details' => $details,
                        'parsed_row' => $row
                    ]);
                }
            }
        }

        return $row;
    }
}
