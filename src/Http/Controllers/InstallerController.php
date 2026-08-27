<?php

namespace Taha20\LaravelInstaller\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
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
     * تست خام اتصال به دیتابیس بدون دست‌کاری کانفیگ سراسری
     */
    protected function testDatabaseConnection(array $data): void
    {
        $connectionName = 'installer_test_connection';

        $config = match ($data['db_connection']) {
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

        config(["database.connections.{$connectionName}" => $config]);
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
            $migrator = app('migrator');
            $migrator->setConnection($db['db_connection']);

            if (! $migrator->repositoryExists()) {
                $migrator->getRepository()->createRepository();
            }

            $files = $migrator->getMigrationFiles(database_path('migrations'));
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
            $migrator = app('migrator');
            $migrator->setConnection($db['db_connection']);

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
     * مرحله ۵ / گام ۳: اجرای seeder ها پس از پایان تمام مایگریشن‌ها (در صورت فعال بودن در کانفیگ).
     * سیدرهای سنگین هم می‌توانند بعداً به همین روش تکه‌تکه شوند؛ در این نسخه یکجا اجرا می‌شوند.
     */
    public function migrateSeed(Request $request)
    {
        @set_time_limit(120);

        if (! Session::has('installer.database')) {
            return response()->json(['message' => 'اطلاعات دیتابیس یافت نشد.'], 422);
        }

        try {
            if (config('installer.run_seeders')) {
                Artisan::call('db:seed', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            return response()->json(['message' => 'خطا در اجرای seeder: ' . $e->getMessage()], 500);
        }

        Session::put('installer.migrated', true);
        Session::forget(['installer.pending_migrations', 'installer.migrations_total']);

        return response()->json(['seeded' => true]);
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
            'email' => 'nullable|email',
            'mobile' => 'required|string|min:11|max:11',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $userModel = config('installer.user_model');

        $userModel::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'],
            'password' => Hash::make($data['password']),
            'role' => 'admin',
        ]);

        // ساخت فایل قفل نصب
        File::put(config('installer.lock_file'), now()->toDateTimeString());

        Artisan::call('config:cache');
        Artisan::call('route:cache');

        Session::forget('installer');

        return redirect()->route('installer.finish');
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
