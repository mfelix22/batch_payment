<?php

namespace App\Http\Controllers;

use App\Imports\SumselBabelImport;
use App\Models\Export;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

class SumselBabelController extends Controller
{
    public function index()
    {
        $filteredArray = [];
        $exported = false;
        $startDate = null;
        $endDate = null;
        return view('sumselbabel', compact('filteredArray', 'exported', 'startDate', 'endDate'));
    }

    public function store(Request $request)
    {
        // Validate that fileExcel is present and is a file
        $request->validate([
            'fileExcel' => 'required|file|mimes:xlsx,xls,csv,txt',
        ], [
            'fileExcel.required' => 'Wajib memasukkan file Excel terlebih dahulu',
            'fileExcel.file' => 'File harus berupa file Excel',
            'fileExcel.mimes' => 'File harus berformat xlsx, xls, atau csv',
        ]);

        $import = new SumselBabelImport;

        try {
            Excel::import($import, $request->file('fileExcel'));
        } catch (\Exception $e) {
            return back()->withErrors(['fileExcel' => 'Format file yang di import tidak sesuai dengan bank Sumsel Babel: ' . $e->getMessage()]);
        }

        $filteredArray = $import->filteredRows;

        // Check if any data was imported
        if (empty($filteredArray)) {
            return back()->withErrors(['fileExcel' => 'Tidak ada data transaksi yang ditemukan dalam file']);
        }

        $tanggalArray = array_column($filteredArray, 'tanggal');
        $tanggalArray = array_filter($tanggalArray);
        $startDate = !empty($tanggalArray) ? min($tanggalArray) : null;
        $endDate = !empty($tanggalArray) ? max($tanggalArray) : null;

        $exported = false;

        if ($startDate && $endDate) {
            try {
                // Overlap check
                $exported = Export::where(function ($query) use ($startDate, $endDate) {
                    $query->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                })->where('bank', 'Sumsel Babel')->exists();
            } catch (\Exception $e) {
                return back()->withErrors(['fileExcel' => 'Error saat memeriksa export: ' . $e->getMessage()]);
            }
        }

        // Convert the Excel file to a collection
        $collection = Excel::toCollection(null, $request->file('fileExcel'));

        // Get the first sheet
        $rows = $collection[0];

        // Try to extract account number from the file
        // Based on the image, NOMOR REKENING should be in one of the early rows
        $rekening = null;
        foreach ($rows->slice(0, 5) as $row) {
            if (isset($row[0]) && strpos($row[0], 'NOMOR') !== false) {
                // Account number should be in the next column or same row
                $rekening = $row[1] ?? null;
                break;
            }
        }

        if (!$rekening) {
            $rekening = 'SUMSELBABEL';
        }

        $title = 'SumselBabel_' . preg_replace('/\D/', '', $rekening);

        return view('sumselbabel', compact('filteredArray', 'title', 'exported', 'startDate', 'endDate'));
    }

    public function saveExport(Request $request)
    {
        Export::create([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'nama_file' => $request->title,
            'bank' => "Sumsel Babel",
        ]);
        return response()->json(['status' => 'success']);
    }
}
