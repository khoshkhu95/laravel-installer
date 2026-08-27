@extends('installer::layout')

@section('content')
    <div style="text-align:center;">
        <div style="font-size:56px; margin-bottom:12px;">✅</div>
        <h1>نصب با موفقیت انجام شد!</h1>
        <p class="subtitle">پروژه شما آماده استفاده است.</p>

        <a href="{{ url('/') }}" class="btn">رفتن به سایت ←</a>
    </div>
@endsection
