@extends('certificates.layout')

@section('styles')
<style>
    body {
        --paper: #14161A;
        --ink: #F5EFE0;
        --gold: var(--accent);
        --rule: #D4AF37;
    }
    .page {
        background: radial-gradient(ellipse at 50% 40%, #23262D 0%, #14161A 70%);
    }

    .frame {
        position: absolute;
        inset: 9mm;
        border: 0.4mm solid var(--gold);
        z-index: 1;
    }
    .frame::before {
        content: "";
        position: absolute;
        inset: 2mm;
        border: 0.15mm solid var(--gold);
        opacity: 0.6;
    }
    .diamond {
        position: absolute;
        width: 5mm; height: 5mm;
        background: var(--gold);
        transform: rotate(45deg);
        z-index: 2;
    }
    .diamond.t { top: 6.5mm; left: 50%; margin-left: -2.5mm; }
    .diamond.b { bottom: 6.5mm; left: 50%; margin-left: -2.5mm; }

    .content {
        display: flex;
        flex-direction: column;
        padding: 18mm 26mm 16mm;
    }

    .header { height: 26mm; display: flex; align-items: center; justify-content: center; }
    .header .logos {
        background: #F5EFE0;
        border-radius: 2mm;
        padding: 2.5mm 6mm;
    }
    .header .logos img { max-height: 18mm; }

    .main {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3mm;
    }
    .org-line { margin: 0; font-size: 11.5pt; color: #C9B98A; text-align: center; }
    .title { font-size: 36pt; color: var(--gold); }
    .title-rule {
        width: 60mm; height: 0.35mm;
        background: linear-gradient(90deg, transparent, var(--gold), transparent);
        margin: 0 auto 1mm;
    }
    .body-text { font-size: 14pt; width: 210mm; color: #E5DDC8; }
    .name-box { width: 220mm; height: 22mm; }
    .name { color: #F5EFE0; text-shadow: 0 0 6mm rgba(212, 175, 55, 0.25); }
    .closing-text { font-size: 13pt; width: 215mm; color: #D8D0BC; }

    .footer { height: 36mm; display: flex; align-items: flex-end; }
    .footer .signatures { width: 100%; padding: 0 30mm; }
    .signature .sig-role { color: var(--gold); }
    .signature .sig-name { color: #E5DDC8; }
    .signature .sig-line { border-top-color: var(--gold); }

    .qr {
        background: #FFFFFF;
        color: #111111;
        padding: 2.5mm;
        border-radius: 2mm;
    }
    .qr.bottom-left { left: 15mm; bottom: 15mm; }
    .qr.bottom-right { right: 15mm; bottom: 15mm; }
    .footer .signatures { padding: 0 20mm 0 50mm; }
</style>
@endsection

@section('design')
<div class="frame"></div>
<div class="diamond t"></div>
<div class="diamond b"></div>

<div class="content">
    <header class="header">
        @include('certificates.partials.logos')
    </header>

    <main class="main">
        <p class="org-line">{{ $cert->parentOrganisation ? $cert->parentOrganisation.' — ' : '' }}{{ $cert->organisation }}</p>
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
