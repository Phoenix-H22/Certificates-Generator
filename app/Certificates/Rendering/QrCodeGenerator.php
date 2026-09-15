<?php

namespace App\Certificates\Rendering;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** Inline SVG QR codes: no GD, no external image, crisp at any print size. */
final class QrCodeGenerator
{
    public function svg(string $content): string
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
        ]);

        return (new QRCode($options))->render($content);
    }
}
