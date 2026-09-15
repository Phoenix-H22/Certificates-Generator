@extends('layouts.public')

@section('title', 'نتيجة التحقق — ' . $organisation->name_ar)

@php
    $badge = match ($state) {
        'valid' => ['class' => 'valid', 'text' => 'شهادة صحيحة'],
        'revoked' => ['class' => 'revoked', 'text' => 'شهادة ملغاة'],
        'pending' => ['class' => 'not-found', 'text' => 'شهادة قيد الإصدار'],
        default => ['class' => 'not-found', 'text' => 'لم يتم العثور على الشهادة'],
    };
@endphp

@section('content')
<section class="card">
    <div class="logos">
        @foreach (\App\Models\BrandAsset::active()->ofType(\App\Enums\BrandAssetType::Logo)->ordered()->get() as $logo)
            <img src="{{ $logo->url() }}" alt="{{ $logo->name }}">
        @endforeach
    </div>

    <p style="text-align:center"><span class="badge {{ $badge['class'] }}">{{ $badge['text'] }}</span></p>

    @if ($certificate === null)
        <p class="lead" style="text-align:center">لا توجد شهادة مسجلة بهذا الرقم: <bdi>{{ $identifier }}</bdi></p>
        <p class="lead" style="text-align:center">تأكد من كتابة الكود كما هو مطبوع على الشهادة، أو امسح رمز QR مباشرة.</p>
    @else
        <ul class="details">
            <li><span>اسم صاحب الشهادة</span><strong>{{ trim(($certificate->recipient_title ? $certificate->recipient_title . ' / ' : '') . $certificate->recipient_name) }}</strong></li>
            @foreach ($details as $detail)
                <li><span>{{ $detail['label'] }}</span><strong>{{ $detail['value'] }}</strong></li>
            @endforeach
            <li><span>القالب</span><strong>{{ $certificate->template?->name }}</strong></li>
            <li><span>تاريخ الإصدار</span><strong>{{ \App\Certificates\Support\ArabicText::date($certificate->batch?->finished_at ?? $certificate->created_at) }}</strong></li>
            <li><span>رقم الشهادة</span><bdi>{{ $certificate->uuid }}</bdi></li>
            <li><span>كود التحقق</span><bdi>{{ $certificate->code }}</bdi></li>
            @if ($state === 'revoked' && $certificate->revoke_reason)
                <li><span>سبب الإلغاء</span><strong>{{ $certificate->revoke_reason }}</strong></li>
            @endif
        </ul>
        <p class="muted">صادرة عن {{ $organisation->name_ar }}@if ($organisation->parent_name_ar) — {{ $organisation->parent_name_ar }}@endif.</p>
        @if ($organisation->verification_footer)
            <p class="muted">{{ $organisation->verification_footer }}</p>
        @endif
    @endif

    <p class="muted"><a href="{{ route('verify.search') }}">التحقق من شهادة أخرى</a></p>
</section>
@endsection
