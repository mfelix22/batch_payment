<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BniImport implements ToCollection, WithHeadingRow, WithCustomCsvSettings
{
    public $filteredRows = [];
    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        foreach ($collection as $index => $row) {
            // Skip header row if necessary
            // dd($row);
            // if ($row['post_date'] === 'Tanggal' || empty($row['post_date'])) {
            //     continue;
            // }

            $debit = floatval(str_replace(',', '', $row['debit']));
            $kredit = floatval(str_replace(',', '', $row['credit']));
            $amount = ($debit != 0 ? -1 * $debit : ($kredit != 0 ? $kredit : 0));

            // Convert tanggal to Y-m-d format
            $tanggal = null;
            if (isset($row['post_date']) && $row['post_date']) {
                $parts = explode(' ', $row['post_date']);
                $tanggal = $parts[0]; // Take only the date part

                // Fix 2-digit year to 4-digit
                if (preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $tanggal)) {
                    // Convert 30/04/25 to 30/04/2025
                    $tanggal = preg_replace_callback('/^(\d{2}\/\d{2}\/)(\d{2})$/', function ($m) {
                        return $m[1] . '20' . $m[2];
                    }, $tanggal);
                }
            }

            $formattedTanggal = $tanggal ? \DateTime::createFromFormat('d/m/Y', $tanggal)->format('Y-m-d') : null;

            $this->filteredRows[] = [
                'tanggal'    => $formattedTanggal,
                'keterangan' => $row['description'],
                'jumlah'     => $amount,
            ];
        }

        // Sort filteredRows by 'tanggal'
        // usort($this->filteredRows, function ($a, $b) {
        //     return strcmp($a['tanggal'], $b['tanggal']);
        // });
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',', // Change to ';' or "\t" if needed
        ];
    }
}
