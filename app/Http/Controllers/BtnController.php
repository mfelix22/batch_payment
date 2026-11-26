<?php

namespace App\Http\Controllers;

use App\Imports\BtnImport;
use App\Models\Export;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class BtnController extends Controller
{
    public function index()
    {
        $filteredArray = [];
        $exported = false;
        $startDate = null;
        $endDate = null;
        $title = null;
        return view('btn', compact('filteredArray', 'exported', 'startDate', 'endDate', 'title'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fileExcel' => 'required|file|mimes:xlsx,xls,csv,txt',
        ], [
            'fileExcel.required' => 'Wajib memasukkan file Excel terlebih dahulu',
            'fileExcel.file' => 'File harus berupa file Excel',
            'fileExcel.mimes' => 'File harus berformat xlsx, xls, csv atau txt',
        ]);

        $import = new BtnImport;
        Excel::import($import, $request->file('fileExcel'));
        $filteredArray = $import->filteredRows;
        // dd($filteredArray);

        $tanggalArray = array_column($filteredArray, 'tanggal');
        $tanggalArray = array_filter($tanggalArray);
        $startDate = !empty($tanggalArray) ? min($tanggalArray) : null;
        $endDate = !empty($tanggalArray) ? max($tanggalArray) : null;

        // Overlap check
        try {
            $exported = Export::where(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate);
            })->where('bank', 'BTN')->exists();
        } catch (\Exception $e) {
            return back()->withErrors(['fileExcel' => 'Format file yang di import tidak sesuai dengan bank BTN']);
        }

        // Get title from file
        $collection = Excel::toCollection(null, $request->file('fileExcel'));
        $rows = $collection[0];
        $rekening = $rows[0][1] ?? null;
        $title = 'BTN_' . ltrim(preg_replace('/\D/', '', $rekening), '0');

        return view('btn', compact('filteredArray', 'exported', 'startDate', 'endDate', 'title'));
    }

    public function saveExport(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'start_date' => 'required|date',
                'end_date' => 'required|date'
            ]);

            Export::create([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'nama_file' => $request->title,
                'bank' => "BTN",
            ]);
            return response()->json(['status' => 'success', 'message' => 'Export berhasil disimpan']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
