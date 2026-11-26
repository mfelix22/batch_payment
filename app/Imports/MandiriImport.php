<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MandiriImport implements ToCollection, WithHeadingRow
{
    public $filteredRows = [];

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        foreach ($collection as $index => $row) {
            // Skip header row if necessary
            // if ($row['value_date'] === 'Tanggal' || empty($row['value_date'])) {
            //     continue;
            // }

            $debit = isset($row['debit']) ? floatval(str_replace(',', '', $row['debit'])) : 0;
            $credit = isset($row['credit']) ? floatval(str_replace(',', '', $row['credit'])) : 0;
            $amount = ($debit != 0 ? -1 * $debit : ($credit != 0 ? $credit : 0));

            // Convert tanggal to Y-m-d format
            $valueDate = isset($row['value_date']) ? $row['value_date'] : null;
            // Split by space to remove time component, take only the date part
            if ($valueDate) {
                $parts = explode(' ', $valueDate);
                $valueDate = $parts[0];
            }
            $tanggal = $valueDate ? \DateTime::createFromFormat('d/m/Y', $valueDate, new \DateTimeZone('Asia/Jakarta')) : false;
            $formattedTanggal = $tanggal ? $tanggal->format('Y-m-d') : null;
            $keterangan = isset($row['description']) ? trim($row['description']) : '';
            $keterangan = preg_replace('/\s+/', ' ', $keterangan); // Remove extra spaces

            $this->filteredRows[] = [
                'DT_RowId'   => 'row_' . $index, // $index is the row number
                'reference'  => '',
                'tanggal'    => $formattedTanggal,
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
        return 1;
    }

    public function toArray()
    {
        return $this->filteredRows;
    }
}
