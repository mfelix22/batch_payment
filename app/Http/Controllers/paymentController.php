<?php

namespace App\Http\Controllers;

use App\Models\bank;
use App\Models\transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
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

            // Get the uploaded file
            $file = $request->file('pdf_file');

            // Send file to PDF parser API
            $response = Http::withoutVerifying()  // Disable SSL verification
                ->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    'document.pdf'
                )
                ->post('https://pdf-parser.hartonomotor-group.com/extract');

            if (!$response->successful()) {
                Log::error('PDF API request failed:', [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return response()->json(['error' => 'PDF processing failed'], 500);
            }

            $data = $response->json();
            Log::debug('API Response:', ['data' => $data]);

            // Process the data for DataTables
            $processedData = $this->processApiResponse($data);

            return response()->json([
                'header' => $processedData['header'],
                'columns' => $processedData['columns'],
                'data' => $processedData['rows']
            ]);
        } catch (\Exception $e) {
            Log::error('PDF processing failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'PDF processing failed: ' . $e->getMessage()], 500);
        }
    }

    private function processApiResponse($data)
    {
        $result = [
            'header' => [
                'branch_batch' => '',
                'date' => '',
                'issuing_account' => ''
            ],
            'columns' => [
                'name',
                'Trans.Date',
                'Trans.Amount',
                'Acc. No.',
                'Bank Name',
                'BI Code',
                'Bank Branch',
                'Remark 1',
                'Remark 2',
                'Jenis',
                'Receiver Cust Type',
                'Receiver Cust Residence'
            ],
            'rows' => [],
            'total' => 0
        ];

        Log::debug('PDF data pages:', ['pageCount' => count($data)]);

        // Extract header information from first page
        $headerDate = '';
        if (isset($data[0]['text'])) {
            $lines = explode("\n", $data[0]['text']);
            
            // Extract header by searching for specific patterns
            $branchBatch = '';
            $dateStr = '';
            $issuingAccount = '';
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                // Look for batch/branch pattern (e.g., "BATCH/OUT/2026/0002" or "BCA Jakarta - 11471 : BATCH/OUT/2026/0003")
                if (preg_match('/BATCH[\/\-]OUT[\/\-]\d{4}[\/\-]\d{4}/', $line)) {
                    $branchBatch = preg_replace('/.*?(BATCH[\/\-]OUT[\/\-]\d{4}[\/\-]\d{4}).*/i', '$1', $line);
                }
                
                // Look for date pattern (e.g., "2026-01-05")
                if (preg_match('/\d{4}-\d{2}-\d{2}/', $line)) {
                    $dateStr = preg_replace('/.*?(\d{4}-\d{2}-\d{2}).*/','$1', $line);
                }
                
                // Look for issuing account pattern
                if (stripos($line, 'Issuing bank account') !== false) {
                    $issuingAccount = trim(str_replace('Issuing bank account :', '', $line));
                    $issuingAccount = trim(str_replace('Issuing bank account:', '', $issuingAccount));
                }
            }
            
            $result['header'] = [
                'branch_batch' => $branchBatch,
                'date' => $dateStr,
                'issuing_account' => $issuingAccount,
            ];
            
            Log::debug('Extracted header:', $result['header']);
        }

        // Loop through all pages in the PDF
        foreach ($data as $pageIndex => $page) {
            if (!isset($page['table'])) {
                Log::debug('No table found on page:', ['pageIndex' => $pageIndex]);
                continue;
            }

            $table = $page['table'];
            
            Log::debug('Processing page:', ['pageIndex' => $pageIndex, 'rowCount' => count($table)]);

            // Skip header row and process data rows
            // Check if last row is TOTAL row by looking for "TOTAL" text
            $startIndex = ($pageIndex === 0) ? 1 : 0; // Skip header only on first page
            $endIndex = count($table);
            
            if (isset($table[$endIndex - 1][0]) && stripos($table[$endIndex - 1][0], 'TOTAL') !== false) {
                $endIndex = $endIndex - 1; // Exclude TOTAL row
                Log::debug('Found TOTAL row on page, excluding it:', ['pageIndex' => $pageIndex]);
            }

            for ($i = $startIndex; $i < $endIndex; $i++) {
                $row = $table[$i];
                
                // Skip empty rows or header rows
                if (empty($row[0]) || stripos($row[0], 'Name') !== false) {
                    continue;
                }
                
                Log::debug('Processing row:', ['page' => $pageIndex, 'index' => $i, 'name' => $row[0] ?? 'N/A']);

                // Clean up amount
                $amount = preg_replace('/[^0-9]/', '', $row[8]);

                // Clean up customer name: remove commas/newlines, collapse whitespace
                $accountHolder = str_replace([",", "\r", "\n"], ' ', $row[5]);
                $accountHolder = preg_replace('/\s+/', ' ', trim($accountHolder));

                // Clean account number: keep digits and preserve (VA) label if present
                $rawAccount = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $row[6])));
                preg_match('/^(\d+)(\s*\(VA\))?/i', $rawAccount, $accMatches);
                $accountNo = isset($accMatches[1]) ? $accMatches[1] : $rawAccount;
                if (!empty($accMatches[2])) {
                    $accountNo .= ' (VA)';
                }

                // Cap remarks to 18 characters after stripping newlines; remove all spaces so segments connect
                $remark1Clean = preg_replace('/\s+/', '', str_replace(["\r", "\n"], '', $row[0]));
                $remark2Clean = preg_replace('/\s+/', '', str_replace(["\r", "\n"], '', $row[3]));
                $remark1 = substr($remark1Clean, 0, 18);
                $remark2 = substr($remark2Clean, 0, 18);

                // Clean transaction date: convert to DD/MM/YYYY format
                // Handle DD/MM/YYYY or D/M/YYYY format
                if (preg_match('#(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})#', $row[2], $matches)) {
                    $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    $year = $matches[3];
                    $transDate = $day .'/' . $month .'/'. $year;
                    
                    // Use the first transaction date as headerDate
                    if (empty($headerDate)) {
                        $headerDate = $transDate;
                    }
                } else {
                    // Fallback: just remove slashes
                    $transDate = str_replace(['/', '-'], '', $row[2]);
                }

                // Get BI Code and pad with zeros
                $bankCode = bank::where('nama_bank', $row[4])->value('nomor_bank');
                $bankCode = str_pad($bankCode, 7, '0', STR_PAD_LEFT);

                // Check if transaction exists
                $exists = transaksi::where('nama_transaksi', $row[0])->exists();
                
                // Check if BI Code is valid (not all zeros)
                $invalid = preg_match('/^0+$/', $bankCode) ? true : false;

                // Format new row
                $newRow = [
                    $accountHolder,             // Account Holder name
                    $transDate,                 // Transaction date (without slashes)
                    (int)$amount,               // Amount
                    $accountNo,                 // Account number (stripped of VA label)
                    $row[4],                    // Bank name
                    $bankCode,                  // BI Code
                    'JAKARTA',                  // Bank branch
                    $remark1,                   // Remark 1 (truncated)
                    $remark2,                   // Remark 2 (truncated)
                    ($row[4] === 'BCA' ? 'BCA' : 'LLG'), // Transaction type
                    ($row[4] === 'BCA' ? '1' : '2'),     // Customer type
                    'R',                        // Residence
                    'exists' => $exists,        // Add exists flag
                    'invalid' => $invalid       // Add invalid flag for bad BI Code
                ];

                $result['rows'][] = $newRow;
            }
        }

        // Update header date with first transaction date
        if (!empty($headerDate)) {
            $result['header']['date'] = $headerDate;
        }

        return $result;
    }

    private function processTransaction($row, $batch)
    {
        try {
            $remark1 = $row[7]; // Remark 1 from the row data

            Log::info('Processing transaction:', ['remark1' => $remark1]);

            // Use firstOrCreate to atomically check and insert, preventing race condition
            // duplicates when two requests run concurrently
            $created = false;
            $transaction = transaksi::firstOrCreate(
                ['nama_transaksi' => $remark1],
                ['batch' => $batch, 'date_create' => now()]
            );
            $created = $transaction->wasRecentlyCreated;

            if ($created) {
                Log::info('Transaction saved:', ['remark1' => $remark1]);
            } else {
                Log::info('Transaction already exists:', ['remark1' => $remark1]);
            }

            return $created;
        } catch (\Exception $e) {
            Log::error('Transaction processing failed:', [
                'error' => $e->getMessage(),
                'remark1' => $row[7],
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function export(Request $request)
    {
        try {
            $data = $request->json()->all();
            if (!isset($data['rows']) || !isset($data['issuing_account'])) {
                throw new \Exception('Invalid data format');
            }

            $bankAccount = $data['issuing_account'];
            $headerDate = $data['header_date'] ?? date('d/m/Y');
            $branchBatch = $data['branch_batch'] ?? null;
            $processedRows = [];
            $totalAmount = 0;

            // Process rows and calculate total
            foreach ($data['rows'] as $row) {
                // Skip if BI Code is all zeros (0000000 or similar)
                if (isset($row[5]) && preg_match('/^0+$/', $row[5])) {
                    Log::warning('Skipping row with all-zero BI Code:', ['row' => $row]);
                    continue;
                }
                
                if ($this->processTransaction($row, $branchBatch)) {
                    $processedRows[] = $row;
                    $totalAmount += intval($row[2]);
                }
            }

            $fileType = str_contains($bankAccount, 'BCA') ? 'xlsx' : 'csv';

            return response()->json([
                'data' => $processedRows,
                'fileType' => $fileType,
                'issuing_account' => $bankAccount,
                'total_amount' => $totalAmount,
                'header' => [
                    'date' => $headerDate,
                    // ...other header fields...
                ],
                'message' => sprintf(
                    'Processed %d new transactions',
                    count($processedRows)
                )
            ]);
        } catch (\Exception $e) {
            Log::error('Export failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
