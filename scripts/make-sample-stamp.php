<?php

/**
 * Renders resources/brand-samples/stamp-asfec.png through Chromium so the
 * circular Arabic text is properly shaped (GD cannot shape Arabic).
 *
 *   php scripts/make-sample-stamp.php [chrome path]
 */
require __DIR__.'/../vendor/autoload.php';

use Spatie\Browsershot\Browsershot;

$root = dirname(__DIR__);
$font = 'file:///'.str_replace(DIRECTORY_SEPARATOR, '/', $root).'/public/fonts/cairo/Cairo-Variable.ttf';
$out = $root.'/resources/brand-samples/stamp-asfec.png';
$chrome = $argv[1] ?? getenv('CERT_CHROME_PATH') ?: null;

$html = <<<HTML
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8">
<style>
@font-face { font-family: 'Cairo'; src: url('$font') format('truetype'); font-weight: 200 1000; }
html, body { margin: 0; background: transparent; }
body { width: 700px; height: 700px; }
svg { width: 700px; height: 700px; font-family: 'Cairo', sans-serif; }
</style></head><body>
<svg viewBox="0 0 700 700" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <path id="top" d="M 110 350 A 240 240 0 0 1 590 350"/>
    <path id="bottom" d="M 110 350 A 240 240 0 0 0 590 350"/>
  </defs>
  <g fill="none" stroke="#1E46A0">
    <circle cx="350" cy="350" r="330" stroke-width="9"/>
    <circle cx="350" cy="350" r="312" stroke-width="3"/>
    <circle cx="350" cy="350" r="190" stroke-width="3"/>
  </g>
  <text fill="#1E46A0" font-size="44" font-weight="700" letter-spacing="1">
    <textPath href="#top" startOffset="50%" text-anchor="middle">المركز الإقليمي لتعليم الكبار</textPath>
  </text>
  <text fill="#1E46A0" font-size="40" font-weight="600">
    <textPath href="#bottom" startOffset="50%" text-anchor="middle">أسفك — سرس الليان</textPath>
  </text>
  <g fill="#1E46A0">
    <rect x="255" y="270" width="190" height="16" rx="3"/>
    <rect x="278" y="292" width="16" height="110"/><rect x="314" y="292" width="16" height="110"/>
    <rect x="350" y="292" width="16" height="110"/><rect x="386" y="292" width="16" height="110"/>
    <rect x="422" y="292" width="16" height="110"/>
    <rect x="245" y="408" width="210" height="16" rx="3"/>
    <polygon points="350,240 362,262 338,262"/>
    <circle cx="90" cy="350" r="7"/><circle cx="610" cy="350" r="7"/>
  </g>
</svg>
</body></html>
HTML;

$tmp = tempnam(sys_get_temp_dir(), 'stamp').'.html';
file_put_contents($tmp, $html);

$shot = (new Browsershot)
    ->setHtmlFromFilePath($tmp)
    ->newHeadless()
    ->setNodeModulePath($root.'/node_modules')
    ->setOption('args', ['--allow-file-access-from-files', '--disable-gpu'])
    ->windowSize(700, 700)
    ->deviceScaleFactor(1)
    ->hideBackground()
    ->waitUntilNetworkIdle()
    ->delay(300);

if ($chrome) {
    $shot->setChromePath($chrome);
}

$shot->save($out);
unlink($tmp);
echo "wrote $out (".round(filesize($out) / 1024)." KB)\n";
