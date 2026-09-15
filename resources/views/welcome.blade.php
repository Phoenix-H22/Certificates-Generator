@extends('layouts.public')

@section('title', config('app.name') . ' — التحقق من الشهادات')

@section('content')
<section class="card">
    <h1>{{ config('app.name') }}</h1>
    <p class="lead">تحقق من صحة شهادة صادرة عن المركز عن طريق مسح رمز QR المطبوع عليها، أو بإدخال رقم الشهادة أدناه.</p>

    <form method="get" action="{{ url('/verify') }}" class="verify-form">
        <label for="code">رقم الشهادة أو كود التحقق</label>
        <input id="code" name="code" type="text" required autocomplete="off" placeholder="مثال: ABCDE-12345" dir="ltr">
        <button type="submit">تحقق</button>
    </form>

</section>
@endsection
