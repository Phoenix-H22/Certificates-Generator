@extends('certificates.layout')

@section('styles')
<style>
    body {
        --paper: #FFFFFF;
        --ink: #1F2937;
        --gold: #B08D57;
        --grey: #4B5563;
        --rule: #0B2545;
        font-family: 'Cairo', sans-serif;
    }

    .band {
        position: relative;
        z-index: 2;
        height: 44mm;
        background: var(--accent);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 18mm;
    }
    .band::after {
        content: "";
        position: absolute;
        left: 0; right: 0; bottom: -1.6mm;
        height: 1.2mm;
        background: var(--gold);
    }
    .band .logos {
        background: #fff;
        border-radius: 3mm;
        padding: 3mm 6mm;
        gap: 8mm;
    }
    .band .logos img { max-height: 20mm; }
    .band .org-block { text-align: end; line-height: 1.35; }
    .band .org-block .parent { font-size: 12pt; opacity: 0.9; }
    .band .org-block .org { font-size: 15pt; font-weight: 700; }

    .content {
        display: flex;
        flex-direction: column;
        height: calc(210mm - 44mm);
        padding: 8mm 22mm 12mm;
    }

    .main {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2.5mm;
    }
    .title {
        font-family: 'Cairo', sans-serif;
        font-weight: 800;
        font-size: 32pt;
        color: var(--accent);
    }
    .title-rule { width: 40mm; height: 0.8mm; background: var(--gold); margin: 0 auto 1mm; }
    .body-text { font-family: 'Noto Naskh Arabic', serif; font-size: 14.5pt; width: 215mm; color: var(--grey); }
    .name-box { width: 225mm; height: 22mm; }
    .name { font-family: 'Cairo', sans-serif; font-weight: 700; color: var(--accent); }
    .closing-text { font-family: 'Noto Naskh Arabic', serif; font-size: 13.5pt; width: 220mm; color: var(--ink); }

    .footer { height: 40mm; display: flex; align-items: flex-end; }
    .footer .signatures { width: 100%; padding: 0 20mm; }
    .signature .sig-role { color: var(--accent); }
    .signature .sig-name { color: var(--grey); }

    .foot-strip {
        position: absolute;
        left: 0; right: 0; bottom: 0;
        height: 5mm;
        background: var(--accent);
        z-index: 2;
    }
    .qr.bottom-left, .qr.bottom-right { bottom: 9mm; }
</style>
@endsection

@section('design')
<div class="band">
    @include('certificates.partials.logos')
    <div class="org-block">
        @if ($cert->parentOrganisation)<div class="parent">{{ $cert->parentOrganisation }}</div>@endif
        <div class="org">{{ $cert->organisation }}</div>
    </div>
</div>

<div class="content">
    <main class="main">
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
<div class="foot-strip"></div>

@if ($cert->hasStamp() && ! str_starts_with($cert->stampPosition(), 'over-'))
    @include('certificates.partials.stamp')
@endif
@include('certificates.partials.qr')
@endsection
