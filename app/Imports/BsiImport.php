<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BsiImport implements ToCollection, WithHeadingRow
{
    public $filteredRows = [];

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            // Split tanggal by space to remove time, take only the date part
            $tanggal = null;
            if (isset($row['waktu_transaksi']) && $row['waktu_transaksi']) {
                $parts = explode(' ', $row['waktu_transaksi']);
                $tanggal = $parts[0]; // Take only the date part
            }
            $formattedTanggal = $tanggal ? \DateTime::createFromFormat('d-m-Y', $tanggal)->format('Y-m-d') : null;

            // Handle debit/credit columns like MandiriImport
            $debit = isset($row['debet']) ? floatval(str_replace(',', '', $row['debet'])) : 0;
            $kredit = isset($row['kredit']) ? floatval(str_replace(',', '', $row['kredit'])) : 0;
            $amount = ($debit != 0 ? -1 * $debit : ($kredit != 0 ? $kredit : 0));

            $this->filteredRows[] = [
                'tanggal'    => $formattedTanggal,
                'keterangan' => $row['deskripsi'] ?? null,
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
}
