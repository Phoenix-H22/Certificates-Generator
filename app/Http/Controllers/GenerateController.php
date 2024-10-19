<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\GenerateCertificates;
use Illuminate\Support\Facades\Storage;
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
            'sheet' => 'required|mimes:xlsx',
        ]);

        $sheet = $request->file('sheet');
        $newName = 'cert_sheets' . rand(1, 10000) . '.xlsx';
        $tempPath = $sheet->storeAs('temp', $newName); // Temporarily store the file to validate
        $newSheet = storage_path('app') . '/' . $tempPath; // Full path to the temp file

        // Load the sheet to validate it
        $rows = (new FastExcel)->import($newSheet);

        // Check if the headers are present
        $headers = $rows->first();
        if (!$headers || !isset($headers['Name'], $headers['Title'], $headers['Email'])) {
            // Delete the temp file
            Storage::delete($tempPath);
            return back()->with('error', 'Sheet must have Name, Title, and Email headers.');
        }
        $errors = [];
        $errors_logic = [];

        // Validate each row
        foreach ($rows as $row) {
            // Check for empty row
            if (!isset($row['Name'], $row['Title'], $row['Email']) ||
                empty($row['Name']) || empty($row['Title']) || empty($row['Email'])) {
                // Delete the temp file
                Storage::delete($tempPath);
                $errors[] = 'Empty row found';
            }

            // Validate email format
            if (!filter_var(trim($row['Email']), FILTER_VALIDATE_EMAIL)) {
                // Delete the temp file
                Storage::delete($tempPath);

                $errors_logic[] = 'Invalid email format for ' . $row['Name'] . ' with email ' . $row['Email'];
            //     delete the row
            }
        }
        if (isset($errors) && count($errors) > 0) {
            return back()->with('error', implode('<br>', $errors));
        }

        // Move the file to public path if it's valid
        $sheet->move(public_path(), $newName);

        // Dispatch job to process the sheet
        GenerateCertificates::dispatch(public_path($newName));

        // Delete the temp file
        Storage::delete($tempPath);

        if (isset($errors_logic) && count($errors_logic) > 0) {
            return back()->with('error', implode('<br>', $errors_logic));
        }else{
            return back()->with('success', 'Sheet uploaded and validated successfully');
        }
    }

}
