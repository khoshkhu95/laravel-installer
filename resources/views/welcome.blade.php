@extends('installer::layout')

@section('content')
    <div class="steps">
        <span class="active"></span><span></span><span></span><span></span><span></span>
    </div>

    <h1>🚀 به نصب‌کننده {{config('installer.name')}} خوش آمدید</h1>
    <p class="subtitle">در چند مرحله ساده، پروژه را روی سرور خود راه‌اندازی می‌کنیم.</p>

    <p style="font-size:14px; color:#cbd5e1; line-height:1.9;">
        در ادامه بررسی می‌کنیم که سرور شما پیش‌نیازهای لازم را دارد، سپس اطلاعات دیتابیس را دریافت
        کرده، مایگریشن‌ها را اجرا می‌کنیم و در نهایت حساب کاربری مدیر سیستم را می‌سازیم.
    </p>

    <div class="row" style="justify-content:flex-end;">
        <a href="{{ route('installer.requirements') }}" class="btn">شروع نصب ←</a>
    </div>
@endsection
