@extends('certificates.layout')

@section('styles')
<style>
    body {
        --paper: #FFFFFF;
        --ink: #1F2937;
        --mist: #F1F5F9;
        --rule: #94A3B8;
        font-family: 'Cairo', sans-serif;
    }

    .bar {
        position: absolute;
        top: 0;
        bottom: 0;
        inset-inline-start: 0;
        width: 16mm;
        background: var(--accent);
        z-index: 1;
    }
    .bar-pattern {
        position: absolute;
        top: 0;
        bottom: 0;
        inset-inline-start: 16mm;
        width: 22mm;
        z-index: 1;
        opacity: 0.12;
        background-color: var(--accent);
        -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Cpath d='M20 0 L40 20 L20 40 L0 20 Z M20 8 L32 20 L20 32 L8 20 Z' fill='black' fill-rule='evenodd'/%3E%3C/svg%3E") 0 0/10mm 10mm repeat;
        mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40' viewBox='0 0 40 40'%3E%3Cpath d='M20 0 L40 20 L20 40 L0 20 Z M20 8 L32 20 L20 32 L8 20 Z' fill='black' fill-rule='evenodd'/%3E%3C/svg%3E") 0 0/10mm 10mm repeat;
    }

    .content {
        display: flex;
        flex-direction: column;
        padding: 16mm 24mm 14mm 52mm; /* wide start-side gutter for the bar */
        padding-inline-start: 52mm;
        padding-inline-end: 24mm;
    }

    .header {
        height: 24mm;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .header .logos { justify-content: flex-start; gap: 8mm; }
    .header .logos img { max-height: 20mm; }
    .org-block { text-align: end; font-size: 10.5pt; color: #475569; line-height: 1.4; }
    .org-block strong { display: block; color: var(--ink); font-size: 12pt; }

    .main {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        text-align: start;
        gap: 3mm;
    }
    .kicker {
        font-size: 11pt;
        letter-spacing: 0.18em;
        color: var(--accent);
        font-weight: 700;
        margin: 0;
    }
    .title {
        font-family: 'Tajawal', 'Cairo', sans-serif;
        font-weight: 800;
        font-size: 36pt;
        text-align: start;
        color: var(--ink);
    }
    .body-text, .closing-text { text-align: start; margin: 0; }
    .body-text { font-size: 13.5pt; color: #475569; }
    .closing-text { font-size: 13pt; max-width: 215mm; }

    .name-box { width: 200mm; height: 24mm; justify-content: flex-start; margin: 1mm 0; }
    .name {
        font-family: 'Tajawal', 'Cairo', sans-serif;
        font-weight: 800;
        color: var(--accent);
        padding-bottom: 1.5mm;
        border-bottom: 1mm solid var(--mist);
    }

    .footer { height: 36mm; display: flex; align-items: flex-end; }
    .footer .signatures { width: 100%; justify-content: flex-start; gap: 24mm; padding-inline-end: 60mm; }
    .signature .sig-line { border-top-color: var(--rule); }

    .qr {
        background: var(--mist);
        padding: 3mm;
        border-radius: 2.5mm;
    }
    .qr.bottom-left { left: 24mm; bottom: 12mm; }
    .qr.bottom-right { right: 24mm; bottom: 12mm; }
</style>
@endsection

@section('design')
<div class="bar"></div>
<div class="bar-pattern"></div>

<div class="content">
    <header class="header">
        @include('certificates.partials.logos')
        <div class="org-block">
            <strong>{{ $cert->organisation }}</strong>
            @if ($cert->parentOrganisation){{ $cert->parentOrganisation }}@endif
        </div>
    </header>

    <main class="main">
        <p class="kicker">{{ $cert->issuedAt }}</p>
        <h1 class="title">{{ $cert->titleText }}</h1>
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
