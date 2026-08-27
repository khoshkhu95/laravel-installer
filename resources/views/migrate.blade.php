@extends('installer::layout')

@section('content')
    <div class="steps">
        <span class="done"></span><span class="done"></span><span class="done"></span><span class="done"></span><span class="active"></span>
    </div>

    <h1>اجرای مایگریشن‌ها</h1>
    <p class="subtitle">
        برای جلوگیری از تایم‌اوت سرور، مایگریشن‌ها یکی‌یکی و از طریق درخواست‌های جداگانه اجرا می‌شوند.
    </p>

    <div class="progress-track">
        <div class="progress-bar" id="progress-bar"></div>
    </div>
    <p class="progress-text" id="progress-text">آماده اجرا</p>

    <div class="alert" id="error-box" style="display:none;"></div>

    <div class="row">
        <a href="{{ route('installer.database') }}" class="btn secondary" id="back-btn">→ قبلی</a>
        <button type="button" class="btn" id="start-btn" onclick="startMigration()">اجرای مایگریشن ←</button>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        async function postJson(url) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            });

            let body = {};
            try { body = await res.json(); } catch (e) { /* پاسخ خالی */ }

            if (!res.ok) {
                throw new Error(body.message || 'خطای ناشناخته‌ای در سرور رخ داد (کد ' + res.status + ')');
            }

            return body;
        }

        function setProgress(percent, text) {
            document.getElementById('progress-bar').style.width = percent + '%';
            document.getElementById('progress-text').textContent = text;
        }

        function setBusy(busy) {
            const startBtn = document.getElementById('start-btn');
            const backBtn = document.getElementById('back-btn');
            startBtn.disabled = busy;
            backBtn.classList.toggle('disabled-link', busy);
        }

        function showError(message) {
            const box = document.getElementById('error-box');
            box.textContent = message;
            box.style.display = 'block';
        }

        async function startMigration() {
            const errorBox = document.getElementById('error-box');
            errorBox.style.display = 'none';
            setBusy(true);
            setProgress(0, 'در حال آماده‌سازی و بررسی اتصال دیتابیس...');

            try {
                // گام ۱: نوشتن env و ساخت لیست مایگریشن‌های در انتظار
                const prepare = await postJson('{{ route("installer.migrate.prepare") }}');
                const total = prepare.total;

                if (total === 0) {
                    setProgress(100, 'هیچ مایگریشن جدیدی برای اجرا وجود نداشت.');
                } else {
                    let done = 0;

                    // گام ۲: حلقه اجرای تک‌به‌تک مایگریشن‌ها تا خالی شدن صف
                    // هر فراخوانی fetch یک درخواست HTTP جدا و کوتاه است، بنابراین تایم‌اوت رخ نمی‌دهد
                    while (true) {
                        const step = await postJson('{{ route("installer.migrate.step") }}');

                        if (step.done) {
                            break;
                        }

                        done++;
                        const percent = Math.round((done / total) * 100);
                        setProgress(percent, 'اجرای ' + done + ' از ' + total + ': ' + step.migration);
                    }
                }

                // گام ۳: اجرای seeder ها (در صورت فعال بودن)
                setProgress(100, 'در حال اجرای seeder ها...');
                await postJson('{{ route("installer.migrate.seed") }}');

                setProgress(100, 'مایگریشن با موفقیت انجام شد. در حال انتقال...');

                setTimeout(function () {
                    window.location.href = '{{ route("installer.admin") }}';
                }, 600);

            } catch (e) {
                showError(e.message);
                setBusy(false);
            }
        }
    </script>
@endsection
