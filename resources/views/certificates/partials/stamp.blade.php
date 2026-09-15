@if ($cert->hasStamp())
<div class="stamp {{ $positionClass ?? $cert->stampPosition() }}"
     style="width: {{ $cert->stamp['width_mm'] }}mm; opacity: {{ $cert->stamp['opacity'] }}; --stamp-rotate: {{ $cert->stamp['rotate'] }}deg;">
    <img src="{{ $cert->stamp['uri'] }}" alt="">
</div>
@endif
