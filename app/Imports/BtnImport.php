<?php

namespace App\Imports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class BtnImport implements ToCollection, WithHeadingRow, WithCustomCsvSettings
{
    public $filteredRows = [];
    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        // Remove the last 7 rows (footer)
        $filtered = $collection->slice(0, $collection->count() - 7);

        $filtered = $filtered->values(); // reset keys to 0-based

        foreach ($filtered as $index => $row) {
            // Skip the first data row (which is the "Debit"/"Credit" sub-header)
            if ($index === 0) {
                continue;
            }
            if (isset($row['post_date']) && $row['post_date']) {
                // Safe to use $row['post_date']
                $tanggal = \Carbon\Carbon::createFromFormat('d/m/Y', $row['post_date'])->format('Y-m-d');
            } else {
                // Handle missing post_date, e.g. skip or set default
                continue; // or $tanggal = null;
            }

            $debit = isset($row['amount']) ? floatval(str_replace(',', '', $row['amount'])) : 0;
            $kredit = isset($row['7']) ? floatval(str_replace(',', '', $row['7'])) : 0;
            $amount = ($debit != 0 ? -1 * $debit : ($kredit != 0 ? $kredit : 0));

            $this->filteredRows[] = [
                'tanggal'    => $tanggal,
                'keterangan' => $row['description'] ?? null,
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
        return 3; // Adjust this if your headings start on a different row
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',', // Change to ';' or "\t" if needed
        ];
    }
}
