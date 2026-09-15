@if (count($cert->signatures))
<div class="signatures">
    @foreach ($cert->signatures as $i => $signature)
        <div class="signature">
            @if ($signature['uri'])
                <img class="sig-image" src="{{ $signature['uri'] }}" alt="" style="width: {{ $signature['width_mm'] ?? 40 }}mm">
            @else
                <div class="sig-space"></div>
            @endif
            <div class="sig-line"></div>
            @if ($signature['role'])
                <div class="sig-role">{{ $signature['role'] }}</div>
            @endif
            <div class="sig-name">{{ $signature['name'] }}</div>

            @if ($cert->hasStamp() && $cert->stampPosition() === 'over-signature-' . ($i + 1))
                @include('certificates.partials.stamp', ['positionClass' => 'over-signature'])
            @endif
        </div>
    @endforeach
</div>
@endif
