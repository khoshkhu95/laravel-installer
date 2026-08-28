<?php

namespace Taha20\LaravelInstaller\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class InstallerController extends Controller
{
    /**
     * مرحله ۱: صفحه خوش‌آمدگویی
     */
    public function welcome()
    {
        return view('installer::welcome');
    }

    /**
     * مرحله ۲: بررسی پیش‌نیازهای سرور (نسخه PHP و اکستنشن‌ها)
     */
    public function requirements()
    {
        $requiredPhp = config('installer.requirements.php_version');
        $phpOk = version_compare(PHP_VERSION, $requiredPhp, '>=');

        $extensions = collect(config('installer.requirements.extensions'))
            ->mapWithKeys(fn ($ext) => [$ext => extension_loaded($ext)]);

        $allOk = $phpOk && ! $extensions->contains(false);

        return view('installer::requirements', [
            'phpOk' => $phpOk,
            'requiredPhp' => $requiredPhp,
            'currentPhp' => PHP_VERSION,
            'extensions' => $extensions,
            'allOk' => $allOk,
        ]);
    }

    /**
     * مرحله ۳: بررسی پرمیشن پوشه‌ها و فایل‌ها
     */
    public function permissions()
    {
        $paths = collect(config('installer.permissions'))
            ->keys()
            ->mapWithKeys(function ($path) {
                $fullPath = base_path($path);
                $writable = File::exists($fullPath) && is_writable($fullPath);

                return [$path => $writable];
            });

        $allOk = ! $paths->contains(false);

        return view('installer::permissions', [
            'paths' => $paths,
            'allOk' => $allOk,
        ]);
    }

    /**
     * مرحله ۴: نمایش فرم اطلاعات دیتابیس
     */
    public function databaseForm()
    {
        return view('installer::database', [
            'drivers' => config('installer.database_drivers'),
            'old' => Session::get('installer.database', []),
        ]);
    }

    /**
     * مرحله ۴: ذخیره اطلاعات دیتابیس بعد از تست اتصال موفق
     */
    public function databaseStore(Request $request)
    {
        $driver = $request->input('db_connection', 'mysql');

        $rules = [
            'db_connection' => 'required|in:mysql,pgsql,sqlite',
        ];

        if ($driver !== 'sqlite') {
            $rules += [
                'db_host' => 'required|string',
                'db_port' => 'required|numeric',
                'db_database' => 'required|string',
                'db_username' => 'required|string',
                'db_password' => 'nullable|string',
            ];
        } else {
            $rules += [
                'db_database' => 'required|string',
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        // تست اتصال به دیتابیس قبل از ذخیره نهایی
        try {
            $this->testDatabaseConnection($data);
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->withErrors(['db_connection' => 'اتصال به دیتابیس ناموفق بود: ' . $e->getMessage()]);
        }

        Session::put('installer.database', $data);

        return redirect()->route('installer.migrate');
    }

    /**
     * جمع‌آوری تمام مسیرهای مایگریشن پروژه: مسیر پیش‌فرض database/migrations،
     * مسیرهای دستی تعریف‌شده در کانفیگ (migration_paths)، و مسیرهای ماژول‌ها که
     * از روی الگوهای glob کانفیگ (migration_module_globs) به‌صورت خودکار کشف می‌شوند.
     *
     * این متد لازم است چون در حالت اجرای تکه‌تکه (AJAX)، مستقیم با Migrator کار
     * می‌کنیم و از دستور معمول `artisan migrate` که مسیرهای ثبت‌شده توسط
     * Service Providerهای ماژول‌ها را خودکار می‌شناسد، استفاده نمی‌کنیم؛
     * بنابراین باید مسیرها را صریحاً خودمان پیدا کنیم.
     */
    protected function resolveMigrationPaths(): array
    {
        $paths = config('installer.migration_paths', [database_path('migrations')]);

        foreach (config('installer.migration_module_globs', []) as $pattern) {
            $matches = glob($pattern, GLOB_ONLYDIR) ?: [];
            $paths = array_merge($paths, $matches);
        }

        // فقط مسیرهایی که واقعاً وجود دارند و پوشه هستند نگه داشته می‌شوند
        return collect($paths)
            ->filter(fn ($path) => File::isDirectory($path))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * ساخت آرایه کانفیگ اتصال دیتابیس از روی داده‌های فرم (بدون وابستگی به .env)
     */
    protected function buildDatabaseConfigArray(array $data): array
    {
        return match ($data['db_connection']) {
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $data['db_database'],
            ],
            default => [
                'driver' => $data['db_connection'],
                'host' => $data['db_host'],
                'port' => $data['db_port'],
                'database' => $data['db_database'],
                'username' => $data['db_username'],
                'password' => $data['db_password'] ?? '',
                'charset' => 'utf8mb4',
            ],
        };
    }

    /**
     * تنظیم مقادیر دیتابیس به‌صورت صریح در کانفیگ runtime همین درخواست.
     *
     * نوشتن در فایل .env به‌تنهایی کافی نیست، چون لاراول تنظیمات دیتابیس را
     * موقع بوت‌شدن (ابتدای همین درخواست) از .env خوانده و در حافظه نگه داشته؛
     * config:clear فقط فایل کش را پاک می‌کند و روی مقادیر runtime همین درخواست
     * تأثیری ندارد. برای همین باید کانکشن مقصد را همین‌جا صریحاً بازنویسی کنیم
     * تا Migrator از مقدار درست (نه مقدار قدیمی/پیش‌فرض قبل از نصب) استفاده کند.
     */
    protected function applyRuntimeDatabaseConfig(array $db): string
    {
        $connectionName = $db['db_connection'];

        config(["database.connections.{$connectionName}" => $this->buildDatabaseConfigArray($db)]);
        config(['database.default' => $connectionName]);

        DB::purge($connectionName);

        return $connectionName;
    }

    /**
     * تست خام اتصال به دیتابیس بدون دست‌کاری کانفیگ سراسری
     */
    protected function testDatabaseConnection(array $data): void
    {
        $connectionName = 'installer_test_connection';

        config(["database.connections.{$connectionName}" => $this->buildDatabaseConfigArray($data)]);
        DB::purge($connectionName);
        DB::connection($connectionName)->getPdo();
        DB::purge($connectionName);
    }

    /**
     * مرحله ۵: صفحه اجرای مایگریشن
     */
    public function migrateForm()
    {
        if (! Session::has('installer.database')) {
            return redirect()->route('installer.database');
        }

        return view('installer::migrate');
    }

    /**
     * مرحله ۵ / گام ۱: نوشتن env، پاک کردن کش کانفیگ و ساخت لیست مایگریشن‌های در انتظار اجرا.
     * این متد از طریق AJAX صدا زده می‌شود و فقط "آماده‌سازی" را انجام می‌دهد، نه اجرای مایگریشن‌ها را.
     */
    public function migratePrepare(Request $request)
    {
        $db = Session::get('installer.database');

        if (! $db) {
            return response()->json(['message' => 'اطلاعات دیتابیس یافت نشد. لطفاً به مرحله قبل برگردید.'], 422);
        }

        // نوشتن اطلاعات دیتابیس در فایل .env
        $envData = ['DB_CONNECTION' => $db['db_connection']];

        if ($db['db_connection'] === 'sqlite') {
            $envData['DB_DATABASE'] = $db['db_database'];
        } else {
            $envData += [
                'DB_HOST' => $db['db_host'],
                'DB_PORT' => $db['db_port'],
                'DB_DATABASE' => $db['db_database'],
                'DB_USERNAME' => $db['db_username'],
                'DB_PASSWORD' => $db['db_password'] ?? '',
            ];
        }

        $this->updateEnvFile($envData);

        // اطمینان از وجود APP_KEY
        if (empty(env('APP_KEY'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        // بعد از نوشتن env حتماً کانفیگ کش‌شده پاک شود، وگرنه کانکشن قدیمی استفاده می‌شود
        Artisan::call('config:clear');

        try {
            // نوشتن .env به‌تنهایی روی کانفیگ همین درخواست اثر نمی‌گذارد؛
            // پس مقادیر دیتابیس را صریحاً در runtime همین درخواست هم ست می‌کنیم
            $connectionName = $this->applyRuntimeDatabaseConfig($db);

            $migrator = app('migrator');
            $migrator->setConnection($connectionName);

            if (! $migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }

            $files = $migrator->getMigrationFiles($this->resolveMigrationPaths());
            $ran = $migrator->getRepository()->getRan();

            // فقط فایل‌هایی که هنوز اجرا نشده‌اند نگه داشته می‌شوند؛ ترتیب فایل‌ها حفظ می‌شود
            $pending = array_diff_key($files, array_flip($ran));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'خطا در اتصال به دیتابیس: ' . $e->getMessage()], 500);
        }

        // ذخیره نقشه [نام مایگریشن => مسیر کامل فایل] در Session برای مصرف توسط گام‌های بعدی
        Session::put('installer.pending_migrations', $pending);
        Session::put('installer.migrations_total', count($pending));

        return response()->json([
            'total' => count($pending),
        ]);
    }

    /**
     * مرحله ۵ / گام ۲: اجرای فقط یک فایل مایگریشن در هر درخواست.
     * فرانت‌اند این متد را به صورت حلقه‌ای (یکی پس از دیگری) صدا می‌زند تا لیست خالی شود.
     * چون هر درخواست فقط یک فایل را اجرا می‌کند، ریسک تایم‌اوت سرور به‌شدت کاهش می‌یابد.
     */
    public function migrateStep(Request $request)
    {
        // اجازه بده اسکریپت طولانی‌تر از حد معمول اجرا شود (در صورتی که میزبان اجازه دهد)
        @set_time_limit(120);

        $db = Session::get('installer.database');
        $pending = Session::get('installer.pending_migrations', []);

        if (! $db) {
            return response()->json(['message' => 'اطلاعات دیتابیس یافت نشد.'], 422);
        }

        if (empty($pending)) {
            return response()->json(['done' => true]);
        }

        // برداشتن اولین مایگریشن از صف (حفظ ترتیب تاریخی فایل‌ها)
        $name = array_key_first($pending);
        $path = $pending[$name];
        unset($pending[$name]);
        Session::put('installer.pending_migrations', $pending);

        try {
            $connectionName = $this->applyRuntimeDatabaseConfig($db);

            $migrator = app('migrator');
            $migrator->setConnection($connectionName);

            // پاس دادن مسیر دقیق فایل (نه پوشه) باعث می‌شود Migrator فقط همین یکی را اجرا کند
            $migrator->run([$path]);
        } catch (\Throwable $e) {
            // فایل ناموفق را به ابتدای صف برمی‌گردانیم تا کاربر بتواند بعد از رفع مشکل دوباره تلاش کند
            Session::put('installer.pending_migrations', [$name => $path] + $pending);

            return response()->json([
                'message' => "خطا در اجرای مایگریشن «{$name}»: " . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'done' => false,
            'migration' => $name,
            'remaining' => count($pending),
            'total' => Session::get('installer.migrations_total', 0),
        ]);
    }

    /**
     * مرحله ۵ / گام ۳: ساخت لیست کلاس‌های Seeder برای اجرا (از روی کانفیگ).
     * فقط کلاس‌هایی که واقعاً وجود دارند (class_exists) نگه داشته می‌شوند تا اگر
     * ماژولی حذف شده باشد یا کلاسش موجود نباشد، نصب متوقف نشود.
     */
    public function migrateSeedPrepare(Request $request)
    {
        if (! Session::has('installer.database')) {
            return response()->json(['message' => 'اطلاعات دیتابیس یافت نشد.'], 422);
        }

        if (! config('installer.run_seeders')) {
            Session::put('installer.pending_seeders', []);

            return response()->json(['total' => 0]);
        }

        $classes = collect(config('installer.seeder_classes', []))
            ->filter(fn ($class) => class_exists($class))
            ->values()
            ->all();

        Session::put('installer.pending_seeders', $classes);

        return response()->json(['total' => count($classes)]);
    }

    /**
     * مرحله ۵ / گام ۴: اجرای فقط یک کلاس Seeder در هر درخواست — دقیقاً با همان
     * فلسفه‌ی اجرای تکه‌تکه‌ی مایگریشن‌ها، تا Seeder سنگین یک ماژول باعث
     * تایم‌اوت نشود. مناسب پروژه‌های ماژولار که چند Seeder مستقل دارند.
     */
    public function migrateSeedStep(Request $request)
    {
        @set_time_limit(120);

        $db = Session::get('installer.database');
        $pending = Session::get('installer.pending_seeders', []);

        if (! $db) {
            return response()->json(['message' => 'اطلاعات دیتابیس یافت نشد.'], 422);
        }

        if (empty($pending)) {
            Session::put('installer.migrated', true);
            Session::forget(['installer.pending_migrations', 'installer.migrations_total', 'installer.pending_seeders']);

            return response()->json(['done' => true]);
        }

        $class = array_shift($pending);
        Session::put('installer.pending_seeders', $pending);

        try {
            $this->applyRuntimeDatabaseConfig($db);
            Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
        } catch (\Throwable $e) {
            // Seeder ناموفق را به ابتدای صف برمی‌گردانیم تا بعد از رفع مشکل دوباره تلاش شود
            array_unshift($pending, $class);
            Session::put('installer.pending_seeders', $pending);

            return response()->json([
                'message' => "خطا در اجرای Seeder «{$class}»: " . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'done' => false,
            'seeder' => $class,
            'remaining' => count($pending),
        ]);
    }

    /**
     * مرحله ۶: فرم ساخت حساب ادمین
     */
    public function adminForm()
    {
        if (! Session::get('installer.migrated')) {
            return redirect()->route('installer.database');
        }

        return view('installer::admin');
    }

    /**
     * مرحله ۶: ذخیره ادمین و اتمام نصب (ساخت فایل قفل)
     */
    public function adminStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $userModel = config('installer.user_model');

        // فیلدهای اضافی که پروژه مشخص کرده (برای ستون‌های NOT NULL سفارشی جدول users)
        $extra = config('installer.admin_extra_fields', []);

        if (is_callable($extra)) {
            $extra = $extra();
        }

        $payload = array_merge($extra, [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        try {
            $userModel::create($payload);
            if (method_exists($userModel,'assignRole')){
                $userModel->assignRole(config('installer.admin_role_name'));
            }
        } catch (QueryException $e) {
            return back()->withInput()->withErrors([
                'admin' => $this->explainDatabaseError($e),
            ]);
        }

        // ساخت فایل قفل نصب — این خط مهم‌تر از کش کردن کانفیگ است و نباید به آن وابسته باشد
        File::put(config('installer.lock_file'), now()->toDateTimeString());

        // کش کردن کانفیگ/روت صرفاً یک بهینه‌سازی سرعت است، نه یک مرحله ضروری نصب.
        // بعضی پکیج‌های شخص ثالث (مثل eloquent-sluggable) ممکن است مقداری از نوع
        // Closure در کانفیگ خود داشته باشند که اصلاً قابل cache کردن نیست (محدودیت خود لاراول).
        // اگر این اتفاق بیفتد نباید کل نصب متوقف شود؛ فقط از کش صرف‌نظر می‌کنیم.
        try {
            Artisan::call('config:cache');
        } catch (\Throwable $e) {
            // کانفیگ کش نشد (مثلاً به‌خاطر یک Closure غیرقابل‌سریالایز در یکی از پکیج‌ها)؛
            // بی‌خطر است، فقط یعنی لاراول هر بار .env را مستقیم می‌خواند (کمی کندتر، نه خراب)
            Artisan::call('config:clear');
        }

        try {
            Artisan::call('route:cache');
        } catch (\Throwable $e) {
            Artisan::call('route:clear');
        }

        Session::forget('installer');

        return redirect()->route('installer.finish');
    }

    /**
     * تبدیل پیام خام خطای دیتابیس (مثل Integrity constraint violation) به یک
     * پیام قابل‌فهم که مشخص می‌کند کدام ستون مشکل‌دار است و چطور رفعش کنیم.
     */
    protected function explainDatabaseError(QueryException $e): string
    {
        $raw = $e->getMessage();

        // الگوی رایج MySQL: Column 'role_id' cannot be null
        if (preg_match("/Column '([^']+)' cannot be null/i", $raw, $m)) {
            $column = $m[1];

            return "ستون «{$column}» در جدول users مقدار خالی (NULL) نمی‌پذیرد اما در فرم ساخت ادمین وجود ندارد. "
                . "مقدار پیش‌فرض آن را در فایل config/installer.php داخل کلید admin_extra_fields مشخص کن، "
                . "مثلاً: 'admin_extra_fields' => ['{$column}' => مقدار_مناسب].";
        }

        // الگوی رایج PostgreSQL: null value in column "role_id" violates not-null constraint
        if (preg_match('/null value in column "([^"]+)"/i', $raw, $m)) {
            $column = $m[1];

            return "ستون «{$column}» در جدول users مقدار خالی (NULL) نمی‌پذیرد اما در فرم ساخت ادمین وجود ندارد. "
                . "مقدار پیش‌فرض آن را در فایل config/installer.php داخل کلید admin_extra_fields مشخص کن، "
                . "مثلاً: 'admin_extra_fields' => ['{$column}' => مقدار_مناسب].";
        }

        // سایر خطاهای احتمالی (مثلاً foreign key نامعتبر) بدون تشخیص دقیق ستون
        return 'خطا در ذخیره حساب ادمین (احتمالاً یک ستون اجباری در جدول users مقداردهی نشده است): '
            . $raw;
    }

    /**
     * مرحله ۷: صفحه پایانی
     */
    public function finish()
    {
        return view('installer::finish');
    }

    /**
     * به‌روزرسانی کلید/مقدارها در فایل .env بدون از بین بردن بقیه محتوا
     */
    protected function updateEnvFile(array $data): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            File::put($envPath, '');
        }

        $content = File::get($envPath);

        foreach ($data as $key => $value) {
            $value = (string) $value;
            $escaped = preg_match('/\s/', $value) ? '"' . addslashes($value) . '"' : $value;

            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $content);
            } else {
                $content .= "\n{$key}={$escaped}";
            }
        }

        File::put($envPath, $content);
    }
}
