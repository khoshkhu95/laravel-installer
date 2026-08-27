@extends('installer::layout')

@section('content')
    <div class="steps">
        <span class="done"></span><span class="active"></span><span></span><span></span><span></span>
    </div>

    <h1>بررسی پیش‌نیازهای سرور</h1>
    <p class="subtitle">مطمئن می‌شویم سرور شما آماده اجرای این پروژه است.</p>

    <ul class="check-list">
        <li>
            <span>نسخه PHP (حداقل {{ $requiredPhp }} — نسخه فعلی: {{ $currentPhp }})</span>
            <span class="{{ $phpOk ? 'ok' : 'fail' }}">{{ $phpOk ? '✔ موجود' : '✘ ناقص' }}</span>
        </li>
        @foreach ($extensions as $ext => $loaded)
            <li>
                <span>اکستنشن {{ $ext }}</span>
                <span class="{{ $loaded ? 'ok' : 'fail' }}">{{ $loaded ? '✔ فعال' : '✘ غیرفعال' }}</span>
            </li>
        @endforeach
    </ul>

    @unless ($allOk)
        <div class="alert">برخی از پیش‌نیازها فراهم نیست. لطفاً با پشتیبانی هاست خود تماس بگیرید و سپس صفحه را رفرش کنید.</div>
    @endunless

    <div class="row">
        <a href="{{ route('installer.welcome') }}" class="btn secondary">→ قبلی</a>
        @if ($allOk)
            <a href="{{ route('installer.permissions') }}" class="btn">مرحله بعد ←</a>
        @else
            <a href="{{ route('installer.requirements') }}" class="btn secondary">بررسی مجدد</a>
        @endif
    </div>
@endsection
