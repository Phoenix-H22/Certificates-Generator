<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\GenerateCertificates;
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
        $validated = $request->validate([
            'sheet' => ['required','file','mimes:xlsx']
        ]);

        $path = $validated['sheet']->storeAs('uploads', 'cert_'.Str::uuid().'.xlsx');

        // Fire the queue job with the storage path, not public path
        GenerateCertificates::dispatch($path);

        return back()->with('success', 'File accepted – certificates are being prepared.');
    }


}
