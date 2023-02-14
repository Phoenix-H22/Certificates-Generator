<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;

class PdfController extends Controller
{
    public function index() {
          return view('index');
      }

    public function exportPdf(Request $request) {
        $snappy = App::make('snappy.pdf');
        //To file
        $name = base64_encode(Auth::user()->name);
        $html = view('certificate',compact('name'))->render();
        // get the time now only
        // $time = Carbon::now()->format('i');
        $time = random_int(1000, 222000);
        $file_name = $name.$time.'.pdf';
        $file_path = public_path().'/pdf-docs/';
        $snappy->generateFromHtml($html, $file_path.$file_name);
        // i want to download the file directly and return to the home page after download
        $headers = array(
            'Content-Type: application/pdf',
          );
        return Response::download($file_path.$file_name,$file_name ,$headers);



        // download

}
}
