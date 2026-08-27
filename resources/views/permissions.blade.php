@extends('installer::layout')

@section('content')
    <div class="steps">
        <span class="done"></span><span class="done"></span><span class="active"></span><span></span><span></span>
    </div>

    <h1>بررسی دسترسی‌های فایل و پوشه</h1>
    <p class="subtitle">این مسیرها باید برای وب‌سرور قابل نوشتن باشند.</p>

    <ul class="check-list">
        @foreach ($paths as $path => $writable)
            <li>
                <span>{{ $path }}</span>
                <span class="{{ $writable ? 'ok' : 'fail' }}">{{ $writable ? '✔ قابل نوشتن' : '✘ غیرقابل نوشتن' }}</span>
            </li>
        @endforeach
    </ul>

    @unless ($allOk)
        <div class="alert">
            دسترسی نوشتن روی برخی مسیرها وجود ندارد. با دستور زیر آن را اصلاح کنید:
            <br><code>chmod -R 775 storage bootstrap/cache</code>
        </div>
    @endunless

    <div class="row">
        <a href="{{ route('installer.requirements') }}" class="btn secondary">→ قبلی</a>
        @if ($allOk)
            <a href="{{ route('installer.database') }}" class="btn">مرحله بعد ←</a>
        @else
            <a href="{{ route('installer.permissions') }}" class="btn secondary">بررسی مجدد</a>
        @endif
    </div>
@endsection
