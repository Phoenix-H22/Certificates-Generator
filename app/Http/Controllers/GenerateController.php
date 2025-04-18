<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\GenerateCertificates;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Rap2hpoutre\FastExcel\FastExcel;

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
            'sheet' => ['required','file','mimes:xlsx'],
        ]);

        // ① ensure /public/uploads exists
        File::ensureDirectoryExists(public_path('uploads'));

        // ② move the file there and grab its ABSOLUTE path
        $fileName   = 'cert_'.Str::uuid().'.xlsx';
        $fullPath   = $request->file('sheet')
            ->move(public_path('uploads'), $fileName)
            ->getPathname();

        // ③ dispatch with the full path
        GenerateCertificates::dispatch($fullPath);

        return back()->with('success', 'File accepted – certificates are being prepared.');
    }


}
