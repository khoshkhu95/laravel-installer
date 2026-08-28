@extends('installer::layout')

@section('content')
    <div class="steps">
        <span class="done"></span><span class="done"></span><span class="done"></span><span class="done"></span><span
            class="done"></span>
    </div>

    <h1>ساخت حساب مدیر سیستم</h1>
    <p class="subtitle">این حساب برای ورود اولیه به پنل مدیریت استفاده می‌شود.</p>

    @if ($errors->any())
        <div class="alert">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('installer.admin.store') }}">
        @csrf

        <label>نام و نام خانوادگی</label>
        <input type="text" name="name" value="{{ old('name') }}">

        <label>ایمیل</label>
        <input type="email" name="email" value="{{ old('email') }}">

        <div class="grid-2">
            <div>
                <label>رمز عبور</label>
                <input type="password" name="password">
            </div>
            <div>
                <label>تکرار رمز عبور</label>
                <input type="password" name="password_confirmation">
            </div>
        </div>

        <div class="row">
            <span></span>
            <button type="submit" class="btn">اتمام نصب ✔</button>
        </div>
    </form>
@endsection
