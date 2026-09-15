@if (count($cert->logos))
<div class="logos">
    @foreach ($cert->logos as $logo)
        <img src="{{ $logo['uri'] }}" alt="{{ $logo['name'] }}" style="width: {{ $logo['width_mm'] ?? 26 }}mm">
    @endforeach
</div>
@endif
