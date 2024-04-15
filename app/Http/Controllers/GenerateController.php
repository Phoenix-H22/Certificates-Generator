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

    //     use GenerateCertificate job to generate certificates
        GenerateCertificates::dispatch($sheet);

        return back()->with('success','Sheet uploaded successfully');
    }
}
