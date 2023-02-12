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
        $name = Auth::user()->name;
        $html = view('certificate',compact('name'))->render();
        // get the time now only
        $time = Carbon::now()->format('H-i-s');

        $snappy->generateFromHtml($html, public_path().'/'.$name.$time.'.pdf');
        // i want to download the file directly and return to the home page after download
        return Response::download(public_path().'/'.$name.random_int("100","25300").'.pdf');



        // download

}
}
