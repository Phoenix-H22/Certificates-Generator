@extends('layouts.public')

@section('title', 'نتيجة التحقق — ' . $organisation->name_ar)

@php
    $badge = match ($state) {
        'valid' => ['class' => 'valid', 'text' => 'شهادة صحيحة'],
        'revoked' => ['class' => 'revoked', 'text' => 'شهادة ملغاة'],
        'pending' => ['class' => 'not-found', 'text' => 'شهادة قيد الإصدار'],
        default => ['class' => 'not-found', 'text' => 'لم يتم العثور على الشهادة'],
    };

    $icons = [
        'valid' => '<path d="M20 6 9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>',
        'revoked' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="m5.6 5.6 12.8 12.8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
        'not-found' => '<path d="M12 3 2 20h20L12 3Z" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M12 10v4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="17" r="1.1" fill="currentColor"/>',
    ];
@endphp

@section('content')
<section class="card">
    @php $logos = \App\Models\BrandAsset::active()->ofType(\App\Enums\BrandAssetType::Logo)->ordered()->get(); @endphp
    @if ($logos->isNotEmpty())
        <div class="logos">
            @foreach ($logos as $logo)
                <img src="{{ $logo->url() }}" alt="{{ $logo->name }}">
            @endforeach
        </div>
    @endif

    <div class="status">
        <span class="badge {{ $badge['class'] }}">
            <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$badge['class']] !!}</svg>
            {{ $badge['text'] }}
        </span>
    </div>

    @if ($certificate === null)
        <p class="lead center">لا توجد شهادة مسجلة بهذا الرقم:</p>
        <p class="center"><bdi style="font-family:ui-monospace,monospace">{{ $identifier }}</bdi></p>
        <p class="muted center">تأكد من كتابة الكود كما هو مطبوع على الشهادة، أو امسح رمز QR مباشرة.</p>
    @else
        <div class="recipient">
            <div class="label">اسم صاحب الشهادة</div>
            <div class="name">{{ trim(($certificate->recipient_title ? $certificate->recipient_title . ' / ' : '') . $certificate->recipient_name) }}</div>
        </div>

        <div class="divider"></div>

        <ul class="details">
            @foreach ($details as $detail)
                <li><span>{{ $detail['label'] }}</span><strong>{{ $detail['value'] }}</strong></li>
            @endforeach
            <li><span>تاريخ الإصدار</span><strong>{{ \App\Certificates\Support\ArabicText::date($certificate->batch?->finished_at ?? $certificate->created_at) }}</strong></li>
            <li><span>رقم الشهادة</span><strong><bdi>{{ $certificate->uuid }}</bdi></strong></li>
            <li class="code"><span>كود التحقق</span><strong><bdi>{{ $certificate->code }}</bdi></strong></li>
            @if ($state === 'revoked' && $certificate->revoke_reason)
                <li><span>سبب الإلغاء</span><strong>{{ $certificate->revoke_reason }}</strong></li>
            @endif
        </ul>

        <p class="issued-by">صادرة عن {{ $organisation->name_ar }}@if ($organisation->parent_name_ar) — {{ $organisation->parent_name_ar }}@endif.</p>
        @if ($organisation->verification_footer)
            <p class="muted">{{ $organisation->verification_footer }}</p>
        @endif
    @endif

    <p class="again"><a href="{{ route('verify.search') }}">التحقق من شهادة أخرى</a></p>
</section>
@endsection
