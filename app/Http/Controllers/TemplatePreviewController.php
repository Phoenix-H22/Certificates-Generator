<?php

namespace App\Http\Controllers;

use App\Certificates\Rendering\CertificateDataFactory;
use App\Certificates\Rendering\CertificateRenderer;
use App\Certificates\Rendering\RenderMode;
use App\Enums\TemplateDesign;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Admin-only live preview of a template with sample data.
 *
 *   ?design=formal_blue   try another design without saving
 *   ?name=...             custom recipient name (stress-test long names)
 *   ?pdf=1                real Chromium PDF, inline
 *   ?png=1                thumbnail PNG
 */
class TemplatePreviewController extends Controller
{
    public function __invoke(Request $request, Template $template, CertificateRenderer $renderer, CertificateDataFactory $factory): Response
    {
        if ($design = $request->query('design')) {
            $template->design = TemplateDesign::from((string) $design);
        }

        $data = $factory->sample($template, $request->query('name'));

        if ($request->boolean('pdf')) {
            return response($renderer->pdf($data), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="preview-'.$template->slug.'.pdf"',
            ]);
        }

        if ($request->boolean('png')) {
            return response($renderer->thumbnail($data), 200, ['Content-Type' => 'image/png']);
        }

        return response($renderer->html($data, RenderMode::Preview));
    }
}
