@extends('installer::layout')

@section('content')
    <div class="steps">
        <span class="done"></span><span class="done"></span><span class="done"></span><span class="active"></span><span></span>
    </div>

    <h1>تنظیمات دیتابیس</h1>
    <p class="subtitle">اطلاعات اتصال به پایگاه داده را وارد کنید.</p>

    @if ($errors->any())
        <div class="alert">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('installer.database.store') }}" id="db-form">
        @csrf

        <label>نوع دیتابیس</label>
        <select name="db_connection" id="db_connection" onchange="toggleFields()">
            @foreach ($drivers as $key => $label)
                <option value="{{ $key }}" @selected(old('db_connection', $old['db_connection'] ?? 'mysql') == $key)>
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <label id="db_database_label">نام دیتابیس</label>
        <input type="text" name="db_database" id="db_database"
               value="{{ old('db_database', $old['db_database'] ?? '') }}">

        <div id="server-fields">
            <div class="grid-2">
                <div>
                    <label>هاست</label>
                    <input type="text" name="db_host" id="db_host" value="{{ old('db_host', $old['db_host'] ?? '127.0.0.1') }}">
                </div>
                <div>
                    <label>پورت</label>
                    <input type="text" name="db_port" id="db_port" value="{{ old('db_port', $old['db_port'] ?? '3306') }}">
                </div>
            </div>

            <div class="grid-2">
                <div>
                    <label>نام کاربری</label>
                    <input type="text" name="db_username" id="db_username" value="{{ old('db_username', $old['db_username'] ?? '') }}">
                </div>
                <div>
                    <label>رمز عبور</label>
                    <input type="password" name="db_password" id="db_password" value="{{ old('db_password', '') }}">
                </div>
            </div>
        </div>

        <div class="row">
            <a href="{{ route('installer.permissions') }}" class="btn secondary">→ قبلی</a>
            <button type="submit" class="btn">تست اتصال و ادامه ←</button>
        </div>
    </form>

    <script>
        function toggleFields() {
            const type = document.getElementById('db_connection').value;
            const isSqlite = type === 'sqlite';
            const serverFields = document.getElementById('server-fields');
            const label = document.getElementById('db_database_label');
            const dbField = document.getElementById('db_database');

            serverFields.style.display = isSqlite ? 'none' : 'block';

            ['db_host', 'db_port', 'db_username', 'db_password'].forEach(function (id) {
                document.getElementById(id).disabled = isSqlite;
            });

            if (isSqlite) {
                label.textContent = 'مسیر فایل دیتابیس';
                if (!dbField.value) {
                    dbField.value = 'database/database.sqlite';
                }
            } else {
                label.textContent = 'نام دیتابیس';
            }
        }

        toggleFields();
    </script>
@endsection
