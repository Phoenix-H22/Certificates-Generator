<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\GenerateCertificates;

class GenerateController extends Controller
{
    public function download_sample(Request $request)
    {
        $path = public_path('data.xlsx');
        return response()->download($path);
    }

    public function upload_sheet(Request $request)
    {
        $request->validate([
            'sheet' => 'required|mimes:xlsx'
        ]);
        $sheet = $request->file('sheet');

        $newName = 'cert_sheets' . rand(1, 10000) . '.xlsx';
        $sheet->move(public_path(), $newName);

        $newSheet = public_path($newName);
        GenerateCertificates::dispatch($newSheet);

        return back()->with('success','Sheet uploaded successfully');
    }
}
