<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;

class SumselBabelImport implements ToCollection, WithHeadingRow
{
    public $filteredRows = [];

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        foreach ($collection as $index => $row) {
            // Skip empty rows or rows where tanggal is empty
            if (empty($row['tanggal']) || trim($row['tanggal']) === '') {
                continue;
            }

            // Skip Total row
            if (stripos($row['tanggal'], 'total') !== false) {
                continue;
            }

            // Skip page row
            if (stripos($row['tanggal'], 'page') !== false) {
                continue;
            }

            // Parse debit and credit values
            $debit = isset($row['debet_idr']) ? floatval(str_replace([',', '.'], ['', '.'], $row['debet_idr'])) : 0;
            $kredit = isset($row['kredit_idr']) ? floatval(str_replace([',', '.'], ['', '.'], $row['kredit_idr'])) : 0;

            // Calculate amount: negative for debit, positive for credit
            $amount = ($debit != 0 ? -1 * $debit : ($kredit != 0 ? $kredit : 0));

            // Convert tanggal from dd-mm-yyyy to Y-m-d format
            $tanggal = null;
            if (isset($row['tanggal']) && $row['tanggal']) {
                try {
                    // Handle dd-mm-yyyy format
                    $tanggal = Carbon::createFromFormat('d-m-Y', trim($row['tanggal']))->format('Y-m-d');
                } catch (\Exception $e) {
                    // If parsing fails, try other formats
                    try {
                        $tanggal = Carbon::parse($row['tanggal'])->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $tanggal = $row['tanggal'];
                    }
                }
            }

            // Get description
            $keterangan = isset($row['deskripsi']) ? trim($row['deskripsi']) : '';

            $this->filteredRows[] = [
                'DT_RowId'   => 'row_' . $index,
                'reference'  => isset($row['nomor']) ? trim($row['nomor']) : '',
                'tanggal'    => $tanggal,
                'keterangan' => $keterangan,
                'partner'    => '',
                'jumlah'     => $amount,
            ];
        }

        // Sort filteredRows by 'tanggal'
        usort($this->filteredRows, function ($a, $b) {
            return strcmp($a['tanggal'], $b['tanggal']);
        });
    }

    public function headingRow(): int
    {
        return 9;
    }

    public function toArray()
    {
        return $this->filteredRows;
    }
}
