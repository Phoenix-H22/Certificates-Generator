@extends('layouts.public')

@section('title', 'محاولات كثيرة')

@section('content')
<section class="card">
    <p class="center"><span class="badge not-found">محاولات كثيرة جداً</span></p>
    <p class="lead center">تم تجاوز الحد المسموح من طلبات التحقق من هذا الجهاز. انتظر قليلاً ثم حاول مرة أخرى.</p>
    <p class="muted center"><a href="{{ url('/') }}">العودة للصفحة الرئيسية</a></p>
</section>
@endsection
