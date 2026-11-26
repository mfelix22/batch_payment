<?php

namespace App\Http\Controllers;

use App\Imports\BniImport;
use App\Models\Export;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BniController extends Controller
{
    public function index()
    {
        $filteredArray = [];
        $exported = false;
        $startDate = null;
        $endDate = null;
        return view('bni', compact('filteredArray', 'exported', 'startDate', 'endDate'));
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

        $import = new BniImport;
        Excel::import($import, $request->file('fileExcel'));
        $filteredArray = $import->filteredRows;

        $tanggalArray = array_column($filteredArray, 'tanggal');
        $tanggalArray = array_filter($tanggalArray);
        $startDate = !empty($tanggalArray) ? min($tanggalArray) : null;
        $endDate = !empty($tanggalArray) ? max($tanggalArray) : null;

        try {
            // Overlap check
            $exported = Export::where(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate);
            })->where('bank', 'BNI')->exists();
        } catch (\Exception $e) {
            return back()->withErrors(['fileExcel' => 'Format file yang di import tidak sesuai dengan bank BNI']);
        }

        // Convert the CSV file to a collection
        $collection = Excel::toCollection(null, $request->file('fileExcel'));

        // Get the first sheet (CSV has only one "sheet")
        $rows = $collection[0];

        // Cell A3 = Row 3, Column A = [2][0] (zero-based index)
        $rekening = $rows[2][0] ?? null;
        $title = 'BNI_' . preg_replace('/\D/', '', $rekening);

        return view('bni', compact('filteredArray', 'title', 'exported', 'startDate', 'endDate'));
    }

    public function saveExport(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date'
            ], [
                'title.required' => 'Nama File Export wajib diisi'
            ]);

            Export::create([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'nama_file' => $request->title,
                'bank' => "BNI",
            ]);
            return response()->json(['status' => 'success', 'message' => 'Export berhasil disimpan']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
