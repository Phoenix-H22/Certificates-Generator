{{-- Shared shell for every certificate design. A4 landscape, RTL, self-hosted fonts. --}}
<!DOCTYPE html>
<html lang="{{ $cert->useArabicNumerals() ? 'ar-EG' : 'ar' }}" dir="rtl">
<head>
<meta charset="utf-8">
<title>{{ $cert->titleText }} — {{ $cert->displayName() }}</title>
<style>
    @font-face { font-family: 'Amiri'; src: url('{{ $asset('fonts/amiri/Amiri-Regular.ttf') }}') format('truetype'); font-weight: 400; }
    @font-face { font-family: 'Amiri'; src: url('{{ $asset('fonts/amiri/Amiri-Bold.ttf') }}') format('truetype'); font-weight: 700; }
    @font-face { font-family: 'Cairo'; src: url('{{ $asset('fonts/cairo/Cairo-Variable.ttf') }}') format('truetype'); font-weight: 200 1000; }
    @font-face { font-family: 'Tajawal'; src: url('{{ $asset('fonts/tajawal/Tajawal-Regular.ttf') }}') format('truetype'); font-weight: 400; }
    @font-face { font-family: 'Tajawal'; src: url('{{ $asset('fonts/tajawal/Tajawal-Bold.ttf') }}') format('truetype'); font-weight: 700; }
    @font-face { font-family: 'Tajawal'; src: url('{{ $asset('fonts/tajawal/Tajawal-ExtraBold.ttf') }}') format('truetype'); font-weight: 800; }
    @font-face { font-family: 'Noto Naskh Arabic'; src: url('{{ $asset('fonts/noto-naskh-arabic/NotoNaskhArabic-Variable.ttf') }}') format('truetype'); font-weight: 400 700; }

    @page { size: A4 landscape; margin: 0; }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        width: 297mm;
        height: 210mm;
        overflow: hidden;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body {
        font-family: 'Cairo', 'Noto Naskh Arabic', sans-serif;
        color: var(--ink);
        background: var(--paper);
        line-height: 1.5;
        --accent: {{ $cert->accent() }};
        --name-font: '{{ $cert->layout['name_font'] ?? 'Amiri' }}', 'Amiri', serif;
    }

    .page {
        position: relative;
        width: 297mm;
        height: 210mm;
        overflow: hidden;
        background: var(--paper);
    }

    .page > .bg {
        position: absolute;
        inset: 0;
        z-index: 0;
        background-size: cover;
        background-position: center;
    }

    .content {
        position: relative;
        z-index: 2;
        height: 100%;
    }

    /* ---- shared blocks -------------------------------------------------- */

    .logos {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12mm;
    }
    .logos img { display: block; max-height: 22mm; object-fit: contain; }

    .title {
        font-family: var(--name-font);
        font-weight: 700;
        text-align: center;
        line-height: 1.25;
        margin: 0;
    }

    .body-text, .closing-text {
        text-align: center;
        margin: 0 auto;
        line-height: 1.7;
    }

    .name-box {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        overflow: hidden;
        margin: 0 auto;
    }
    .name {
        font-family: var(--name-font);
        font-weight: 700;
        line-height: 1.3;
        white-space: nowrap;
        display: inline-block;
        max-width: 100%;
    }

    .signatures {
        display: flex;
        justify-content: space-around;
        align-items: flex-end;
        gap: 10mm;
    }
    .signature {
        position: relative;
        text-align: center;
        min-width: 55mm;
    }
    .signature .sig-image {
        display: block;
        margin: 0 auto 1mm;
        max-height: 18mm;
        object-fit: contain;
    }
    .signature .sig-space { height: 14mm; }
    .signature .sig-line {
        border-top: 0.4mm solid var(--rule, currentColor);
        width: 55mm;
        margin: 0 auto 1.5mm;
        opacity: 0.6;
    }
    .signature .sig-role { font-size: 11pt; font-weight: 700; }
    .signature .sig-name { font-size: 12pt; }

    .stamp {
        position: absolute;
        z-index: 5;
        pointer-events: none;
    }
    .stamp img { display: block; width: 100%; height: auto; }
    .stamp.bottom-left   { left: 22mm; bottom: 30mm; }
    .stamp.bottom-center { left: 50%; bottom: 26mm; transform: translateX(-50%) rotate(var(--stamp-rotate)); }
    .stamp.bottom-right  { right: 22mm; bottom: 30mm; }
    .stamp.bottom-left, .stamp.bottom-right { transform: rotate(var(--stamp-rotate)); }
    .signature .stamp.over-signature {
        left: 50%;
        bottom: 14mm;
        transform: translateX(-50%) rotate(var(--stamp-rotate));
    }

    .qr {
        position: absolute;
        z-index: 6;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1mm;
        font-size: 7pt;
        line-height: 1.3;
        color: var(--ink);
    }
    .qr svg { display: block; width: {{ $cert->qrSizeMm() }}mm; height: {{ $cert->qrSizeMm() }}mm; }
    .qr .qr-code { font-family: 'Cairo', monospace; font-weight: 700; letter-spacing: 0.08em; font-size: 8.5pt; }
    .qr .qr-uuid { font-family: monospace; font-size: 6pt; opacity: 0.75; letter-spacing: 0.02em; }
    .qr .qr-hint { opacity: 0.8; }
    .qr.bottom-left  { left: 12mm; bottom: 10mm; }
    .qr.bottom-right { right: 12mm; bottom: 10mm; }
    .qr.top-left     { left: 12mm; top: 10mm; }
    .qr.top-right    { right: 12mm; top: 10mm; }

    bdi { unicode-bidi: isolate; }
</style>
@yield('styles')
</head>
<body class="design-{{ str_replace('_', '-', $cert->design->value) }}">
<div class="page">
    @if ($cert->background)
        <div class="bg" style="background-image:url('{{ $cert->background }}')"></div>
    @endif
    @yield('design')
</div>
@include('certificates.partials.fit-text')
</body>
</html>
