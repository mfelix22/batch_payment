<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class BcaImport implements WithMultipleSheets
{
    public $filteredRows = [];

    public function sheets(): array
    {
        // Pass $this to the anonymous class so it can set filteredRows
        $parent = $this;
        return [
            0 => new class($parent) implements ToCollection, WithHeadingRow {
                private $parent;
                public function __construct($parent)
                {
                    $this->parent = $parent;
                }

                public function headingRow(): int
                {
                    return 7;
                }

                public function collection(Collection $collection)
                {
                    // Remove the last 4 rows
                    $filtered = $collection->slice(0, $collection->count() - 4);

                    // dd($filtered->first());

                    $result = $filtered->map(function ($row) {
                        $tanggal = null;
                        if (isset($row['tanggal_transaksi']) && $row['tanggal_transaksi']) {
                            if (is_numeric($row['tanggal_transaksi'])) {
                                // Convert Excel serial number to Y-m-d
                                $tanggal = Date::excelToDateTimeObject($row['tanggal_transaksi'])->format('Y-m-d');
                            } else {
                                // Try to parse as d/m/Y
                                try {
                                    $tanggal = Carbon::createFromFormat('d/m/Y', $row['tanggal_transaksi'])->format('Y-m-d');
                                } catch (\Exception $e) {
                                    $tanggal = $row['tanggal_transaksi']; // fallback
                                }
                            }
                        }

                        $jumlah = null;
                        $tipe = null;
                        if (isset($row['jumlah'])) {
                            $jumlahStr = trim($row['jumlah']);
                            $tipe = strtoupper(substr($jumlahStr, -2)); // Get last 2 chars, e.g. 'DB' or 'CR'

                            $jumlah = (float) str_replace(',', '', $jumlahStr);

                            // If 'CR', add minus sign in front, if 'DB', keep as is
                            if ($tipe === 'DB') {
                                $jumlah = $jumlah * -1;
                            }
                        } else {
                            $jumlah = null;
                        }

                        return [
                            'tanggal' => $tanggal,
                            'keterangan' => $row['keterangan'] ?? null,
                            'jumlah' => $jumlah,
                            'tipe' => $tipe,
                        ];
                    })->toArray();

                    // Sort by 'tanggal'
                    // usort($result, function($a, $b) {
                    //     return strcmp($a['tanggal'], $b['tanggal']);
                    // });

                    // Set the result in the parent import class
                    $this->parent->filteredRows = $result;
                }
            },
        ];
    }
}
