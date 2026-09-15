<?php

namespace App\Certificates\Rendering;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** Inline SVG QR codes: no GD, no external image, crisp at any print size. */
final class QrCodeGenerator
{
    public function svg(string $content, string $colour = '#111111'): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'svgUseFillAttributes' => true,
            'drawLightModules' => false,
            'moduleValues' => [
                // dark modules
                QRCode::M_DATA_DARK => $colour,
                QRCode::M_FINDER_DARK => $colour,
                QRCode::M_FINDER_DOT => $colour,
                QRCode::M_ALIGNMENT_DARK => $colour,
                QRCode::M_TIMING_DARK => $colour,
                QRCode::M_FORMAT_DARK => $colour,
                QRCode::M_VERSION_DARK => $colour,
                QRCode::M_DARKMODULE => $colour,
                QRCode::M_SEPARATOR_DARK => $colour,
            ],
        ]);

        return (new QRCode($options))->render($content);
    }
}
