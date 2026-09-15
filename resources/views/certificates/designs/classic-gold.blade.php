@extends('certificates.layout')

@section('styles')
<style>
    body {
        --paper: #FBF7EE;
        --ink: #2B2B2B;
        --maroon: #7A1F2B;
        --rule: #B08D57;
    }

    .frame-outer {
        position: absolute;
        inset: 7mm;
        border: 1.3mm solid var(--accent);
        z-index: 1;
    }
    .frame-inner {
        position: absolute;
        inset: 10.5mm;
        border: 0.35mm solid var(--accent);
        z-index: 1;
    }
    .corner {
        position: absolute;
        width: 26mm;
        height: 26mm;
        z-index: 1;
        color: var(--accent);
    }
    .corner.tl { top: 5mm; left: 5mm; }
    .corner.tr { top: 5mm; right: 5mm; transform: scaleX(-1); }
    .corner.bl { bottom: 5mm; left: 5mm; transform: scaleY(-1); }
    .corner.br { bottom: 5mm; right: 5mm; transform: scale(-1, -1); }

    .content {
        display: flex;
        flex-direction: column;
        padding: 16mm 22mm 14mm;
    }

    .header { height: 26mm; display: flex; align-items: center; justify-content: center; }
    .header .logos img { max-height: 24mm; }

    .main {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3mm;
    }

    .org-line {
        font-size: 12.5pt;
        color: var(--maroon);
        font-weight: 700;
        margin: 0;
        text-align: center;
    }

    .title {
        font-size: 34pt;
        color: var(--maroon);
        letter-spacing: 0.01em;
    }
    .title-rule {
        width: 70mm;
        height: 0.5mm;
        background: linear-gradient(90deg, transparent, var(--accent), transparent);
        margin: 0 auto 1mm;
    }

    .body-text { font-size: 14pt; width: 210mm; }

    .name-box { width: 220mm; height: 22mm; }
    .name { color: #8A6A2E; }

    .closing-text { font-size: 13pt; width: 215mm; color: #3A3A3A; }

    .footer { height: 38mm; display: flex; align-items: flex-end; }
    .footer .signatures { width: 100%; padding: 0 30mm; }
    .signature .sig-role { color: var(--maroon); }
</style>
@endsection

@section('design')
<div class="frame-outer"></div>
<div class="frame-inner"></div>

@php
    $corner = '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">'
        .'<path d="M6 94 C6 50 20 20 60 8 M6 94 C 40 94 70 82 92 6" opacity="0.9"/>'
        .'<path d="M14 86 C14 52 30 30 58 18" opacity="0.6"/>'
        .'<circle cx="60" cy="8" r="3" fill="currentColor" stroke="none"/><circle cx="92" cy="6" r="3" fill="currentColor" stroke="none"/>'
        .'<path d="M28 60 c8 -10 18 -12 26 -6 c-8 -2 -16 2 -20 10 z" fill="currentColor" stroke="none" opacity="0.8"/>'
        .'</svg>';
@endphp
<div class="corner tl">{!! $corner !!}</div>
<div class="corner tr">{!! $corner !!}</div>
<div class="corner bl">{!! $corner !!}</div>
<div class="corner br">{!! $corner !!}</div>

<div class="content">
    <header class="header">
        @include('certificates.partials.logos')
    </header>

    <main class="main">
        @if ($cert->parentOrganisation)
            <p class="org-line">{{ $cert->parentOrganisation }} — {{ $cert->organisation }}</p>
        @else
            <p class="org-line">{{ $cert->organisation }}</p>
        @endif

        <h1 class="title">{{ $cert->titleText }}</h1>
        <div class="title-rule"></div>

        <p class="body-text">{{ $cert->bodyText }}</p>

        <div class="name-box">
            <span class="name" data-fit data-fit-min="18" style="font-size: {{ $cert->nameFontPt }}pt">{{ $cert->displayName() }}</span>
        </div>

        <p class="closing-text">{{ $cert->closingText }}</p>
    </main>

    <footer class="footer">
        @include('certificates.partials.signatures')
    </footer>
</div>

@if ($cert->hasStamp() && ! str_starts_with($cert->stampPosition(), 'over-'))
    @include('certificates.partials.stamp')
@endif
@include('certificates.partials.qr')
@endsection
