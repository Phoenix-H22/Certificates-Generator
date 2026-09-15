{{-- Mandatory on every design: QR → verify URL, plus the short code and UUID for manual lookup. --}}
<div class="qr {{ $cert->qrPosition() }}">
    {!! $cert->qrSvg !!}
    <bdi class="qr-code">{{ $cert->code }}</bdi>
    <bdi class="qr-uuid">{{ $cert->uuid }}</bdi>
    <span class="qr-hint">للتحقق: <bdi>{{ parse_url($cert->verifyUrl, PHP_URL_HOST) }}/verify</bdi></span>
</div>
