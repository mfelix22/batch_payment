<?php

namespace App\Http\Controllers;

use App\Imports\BsiImport;
use App\Models\Export;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelReader;

class BsiController extends Controller
{
    public function index()
    {
        $filteredArray = [];
        $exported = false;
        $startDate = null;
        $endDate = null;
        $title = null;
        return view('bsi', compact('filteredArray', 'exported', 'startDate', 'endDate', 'title'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fileExcel' => 'required|file|mimes:xlsx,xls,csv,txt,html',
        ], [
            'fileExcel.required' => 'Wajib memasukkan file Excel terlebih dahulu',
            'fileExcel.file' => 'File harus berupa file Excel',
            'fileExcel.mimes' => 'File harus berformat xlsx, xls, csv atau txt',
        ]);
        libxml_use_internal_errors(true); // Suppress warnings

        $dom = new \DOMDocument();
        $loaded = $dom->loadHTMLFile($request->file('fileExcel')->getRealPath());

        if (!$loaded) {
            // Handle the error, e.g., show a custom message
            return back()->withErrors(['fileExcel' => 'File HTML tidak valid atau mengandung karakter yang tidak didukung.']);
        }

        // Optionally, clear libxml errors
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        $filteredArray = [];

        $rekening = null;
        foreach ($tables as $table) {
            foreach ($table->getElementsByTagName('tr') as $tr) {
                $text = trim($tr->textContent);
                // Find the row that contains "Rekening"
                if (stripos($text, 'Rekening') !== false) {
                    $rekening = preg_replace('/\D/', '', $text);
                    break 2; // Stop after finding the first match
                }
            }
        }
        // dd($rekening);

        // Read the uploaded file as HTML
        $html = file_get_contents($request->file('fileExcel')->getRealPath());

        // Use DOMDocument to extract the second table
        $import = new BsiImport;
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true); // Suppress HTML5 warnings
        $dom->loadHTML($html);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length > 1) {
            $secondTable = $dom->saveHTML($tables->item(1));
            // Save $secondTable to a temp file
            $tmpFile = tempnam(sys_get_temp_dir(), 'bsi_html');
            file_put_contents($tmpFile, $secondTable);

            // Now import using Maatwebsite Excel
            Excel::import($import, $tmpFile, null, \Maatwebsite\Excel\Excel::HTML);
            // dd($import->filteredRows); // <-- This will show your imported data
            unlink($tmpFile);
        } else {
            // Handle error: data table not found
        }

        $filteredArray = $import->filteredRows;
        // dd($filteredArray);

        $tanggalArray = array_column($filteredArray, 'tanggal');
        // dd($tanggalArray);
        $tanggalArray = array_filter($tanggalArray);
        $startDate = !empty($tanggalArray) ? min($tanggalArray) : null;
        $endDate = !empty($tanggalArray) ? max($tanggalArray) : null;

        try {
            // Overlap check
            $exported = Export::where(function ($query) use ($startDate, $endDate) {
                $query->where('start_date', '<=', $endDate)
                    ->where('end_date', '>=', $startDate);
            })->where('bank', 'BSI')->exists();
        } catch (\Exception $e) {
            return back()->withErrors(['fileExcel' => 'Format file yang di import tidak sesuai dengan bank BSI']);
        }

        $title = 'BSI_' . preg_replace('/\D/', '', $rekening);
        // dd($title);

        return view('bsi', compact('filteredArray', 'exported', 'startDate', 'endDate', 'title'));
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
                'bank' => "BSI",
            ]);
            return response()->json(['status' => 'success', 'message' => 'Export berhasil disimpan']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
