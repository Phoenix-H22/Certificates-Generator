<?php

namespace App\Http\Controllers;


use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function index() {
          return view('index');
      }
    public function exportPdf() {
          $pdf = Pdf::loadView('certificate')->setPaper('legal','landscape'); // <--- load your view into theDOM wrapper;
          $path = public_path('pdf_docs/'); // <--- folder to store the pdf documents into the server;
          $fileName =  time().'.'. 'pdf' ; // <--giving the random filename,
          $pdf->save($path . '/' . $fileName);
          $generated_pdf_link = url('pdf_docs/'.$fileName);
          return response()->json($generated_pdf_link);
      }
}
