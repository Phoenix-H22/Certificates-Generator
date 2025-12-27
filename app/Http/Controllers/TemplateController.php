<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function upload_template(Request $request)
    {
        $request->validate([
            'template' => 'required|mimes:docx'
        ]);
        $template = $request->file('template');

        // Attempt to move the new file into place
        try {
            $file_name = 'Certificate_template_'.Str::uuid().'.docx';
            $path = public_path();
            $template->move(public_path(), $file_name);
            $settings = Setting::first();
            if ($settings) {
                $settings->template_name = $file_name;
                $settings->save();
            } else {
                $new =  Setting::create([
                    'template_name' => $file_name
                ]);
            }

            return back()->with('success','Template uploaded successfully');
        } catch (Exception $e) {
            return back()->with('error', 'Error uploading template: ' . $e->getMessage());
        }
    }

    public function download_template(Request $request)
    {
        $settings = Setting::first();
        if ($settings) {
            $template = $settings->template_name;
            $path = public_path($template);
            return response()->download($path);
        } else {
            return back()->with('error', 'No template uploaded yet');
        }
    }
}
