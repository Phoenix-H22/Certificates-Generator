@extends('layouts.public')

@section('title', 'التحقق من شهادة — ' . $organisation->name_ar)

@section('content')
<section class="card">
    <h1>التحقق من صحة شهادة</h1>
    <p class="lead">أدخل رقم الشهادة (UUID) أو كود التحقق القصير المطبوع أسفل رمز QR.</p>

    <form method="get" action="{{ route('verify.search') }}" class="verify-form">
        <label for="code">رقم الشهادة أو كود التحقق</label>
        <input id="code" name="code" type="text" required autocomplete="off" placeholder="مثال: ABCDE-12345" dir="ltr" autofocus>
        <button type="submit">تحقق</button>
    </form>
</section>
@endsection
