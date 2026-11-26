<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BriImport implements ToCollection, WithHeadingRow
{

    public $filteredRows = [];
    public $nomor_rekening = null;
    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        foreach ($collection as $row) {
            if ($this->nomor_rekening === null && isset($row['norek'])) {
                $this->nomor_rekening = $row['norek'];
            }

            $tanggal = null;
            if (isset($row['tgl_tran']) && $row['tgl_tran']) {
                $parts = explode(' ', $row['tgl_tran']);
                $tanggal = $parts[0]; // Take only the date part
            }

            // Handle debit/credit columns like MandiriImport
            $debit = isset($row['mutasi_debet']) ? floatval(str_replace(',', '', $row['mutasi_debet'])) : 0;
            $kredit = isset($row['mutasi_kredit']) ? floatval(str_replace(',', '', $row['mutasi_kredit'])) : 0;
            $amount = ($debit != 0 ? -1 * $debit : ($kredit != 0 ? $kredit : 0));

            $this->filteredRows[] = [
                'tanggal'    => $tanggal,
                'keterangan' => $row['desk_tran'] ?? null,
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
