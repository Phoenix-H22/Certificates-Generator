<?php

namespace App\Http\Controllers;



use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Barryvdh\Snappy\Facades\SnappyPdf as PDF;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PdfController extends Controller
{
    public function index() {
          return view('index');
      }

    public function exportPdff(Request $request) {
        //return view('certificate');
        $snappy = App::make('snappy.pdf');
        // a4
        $snappy->setOption('page-size', 'A4');
        $snappy->setOption('orientation', 'Landscape');
        //$snappy->setOption('page-width', '297mm'); // Replace 210mm with the width of your certificate
        //$snappy->setOption('page-height', '210mm');
        $snappy->setOption('encoding', 'UTF-8');
        $snappy->setOption('margin-top', '0mm');
        $snappy->setOption('margin-bottom', '0mm');
        $snappy->setOption('margin-left', '0mm');
        $snappy->setOption('margin-right', '0mm');
        $snappy->setOption('zoom', 1.56); // Scale down to 95% of the original size
        $snappy->setOption('disable-smart-shrinking', false);

        //To file
        $real_name = Auth::user()->name;
        $html = view('certificate', compact('real_name'))->render();
        // get the time now only
        // $time = Carbon::now()->format('i');
        $time = random_int(1000, 222000);
        $file_name = "test".$time.'.pdf';
        $file_path = public_path().'/pdf-docs/';
        $snappy->generateFromHtml($html, $file_path.$file_name);
        // i want to download the file directly and return to the home page after download
        $headers = array(
            'Content-Type: application/pdf',
          );
        return Response::download($file_path.$file_name,$file_name ,$headers);

    }
    public function exportPdf()
    {
        // Load and replace placeholders in the DOCX
        $templateProcessor = new TemplateProcessor(public_path('Certificate_template.docx'));
        $templateProcessor->setValue('{Name}', Auth::user()->name);
        $templateProcessor->setValue('{Title}', 'DR');
        // save the file
        // new file name
        $time = Carbon::now()->format('i');
        $new_file_name = "certificate".$time;
        $templateProcessor->saveAs(public_path('pdf-docs/'.$new_file_name.'.docx'));

        $outputDir = public_path('pdf-docs/');
        // PDF file path
        $pdfPath = public_path('pdf-docs/'.$new_file_name.'.pdf');


        // Check if LibreOffice is installed
        $processCheck = new Process(['which', 'libreoffice']);
        $processCheck->run();

        if (!$processCheck->isSuccessful()) {
            // Handle the error appropriately
            return response()->json(['error' => 'LibreOffice is not installed on the server.'], 500);
        }

        // Prepare the command
        $command = "libreoffice --headless --convert-to pdf --outdir {$outputDir} {$outputDir}{$new_file_name}.docx";

        // Run the command
        $process = Process::fromShellCommandline($command);
        $process->run();

        // Executes after the command finishes
        if (!$process->isSuccessful()) {
            // Handle the error if the conversion fails
            throw new ProcessFailedException($process);
        }

        // Return the response as download
        return response()->download($pdfPath);
    }
}
