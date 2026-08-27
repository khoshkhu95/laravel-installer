<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نصب‌کننده - @yield('title', 'خوش‌آمدید')</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Tahoma, Vazirmatn, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: #1e293b;
            border-radius: 16px;
            padding: 36px;
            width: 100%;
            max-width: 640px;
            box-shadow: 0 20px 50px rgba(0,0,0,.35);
        }
        h1 { font-size: 22px; margin-bottom: 6px; }
        p.subtitle { color: #94a3b8; margin-top: 0; margin-bottom: 24px; }
        .steps {
            display: flex;
            gap: 6px;
            margin-bottom: 28px;
        }
        .steps span {
            flex: 1;
            height: 4px;
            border-radius: 4px;
            background: #334155;
        }
        .steps span.active { background: #6366f1; }
        .steps span.done { background: #22c55e; }
        label { display: block; margin-bottom: 6px; font-size: 14px; color: #cbd5e1; }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #334155;
            background: #0f172a;
            color: #e2e8f0;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .btn {
            display: inline-block;
            background: #6366f1;
            color: #fff;
            border: none;
            padding: 11px 26px;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: #4f46e5; }
        .btn.secondary { background: #334155; }
        .row { display: flex; justify-content: space-between; align-items: center; margin-top: 24px; }
        .check-list { list-style: none; padding: 0; margin: 0 0 24px; }
        .check-list li {
            display: flex;
            justify-content: space-between;
            padding: 10px 14px;
            border-radius: 8px;
            background: #0f172a;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .ok { color: #22c55e; font-weight: bold; }
        .fail { color: #ef4444; font-weight: bold; }
        .alert {
            background: #7f1d1d;
            color: #fecaca;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 16px; }
        .progress-track {
            background: #0f172a;
            border-radius: 8px;
            height: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-bar {
            background: #6366f1;
            height: 100%;
            width: 0%;
            transition: width .25s ease;
        }
        .progress-text {
            font-size: 13px;
            color: #94a3b8;
            margin: 0 0 20px;
            min-height: 18px;
        }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn.disabled-link { pointer-events: none; opacity: .5; }
    </style>
</head>
<body>
    <div class="card">
        @yield('content')
    </div>
</body>
</html>
